#!/bin/sh
# One backup run: database + uploaded files, encrypted, copied off the server.
#
#   docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backup backup-now
#
# Produces, in /backups/local (the "backups" volume) and at the destination:
#   madeeasy-<stamp>-db.dump.age      pg_dump custom format, age-encrypted
#   madeeasy-<stamp>-files.tar.gz.age storage/app/private (customer documents)
#                                     + the public disk (Updates images)
#   madeeasy-<stamp>.sha256           checksums of the two encrypted files
#
# Encryption uses age PUBLIC keys (BACKUP_AGE_RECIPIENTS). The matching
# private key is NOT on the server, so someone who takes the server or the
# destination still can't read the backups. Keep that key safe and offline.
#
# BACKUP_TARGET: scp | s3 | local ("local" leaves them on this server only;
# fine for a test, NOT a real off-server backup).
set -eu
set -o pipefail

: "${BACKUP_AGE_RECIPIENTS:?BACKUP_AGE_RECIPIENTS must be set}"
: "${DB_HOST:=db}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:?}"
: "${DB_USERNAME:?}"
: "${DB_PASSWORD:?}"
TARGET="${BACKUP_TARGET:-local}"
KEEP_LOCAL_DAYS="${BACKUP_KEEP_LOCAL_DAYS:-7}"
LOCAL_DIR=/backups/local
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NAME="madeeasy-${STAMP}"

log() { echo "backup [$(date -u +%FT%TZ)] $*"; }
fail() { log "FAILED: $*"; exit 1; }

recipient_args=""
for recipient in ${BACKUP_AGE_RECIPIENTS}; do
    recipient_args="${recipient_args} -r ${recipient}"
done

mkdir -p "${LOCAL_DIR}"
cd "${LOCAL_DIR}"

log "start ${NAME} (target ${TARGET})"

export PGPASSWORD="${DB_PASSWORD}"
# shellcheck disable=SC2086
pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" --format=custom --no-owner \
    | age ${recipient_args} > "${NAME}-db.dump.age.partial" \
    || fail "database dump"
mv "${NAME}-db.dump.age.partial" "${NAME}-db.dump.age"

# shellcheck disable=SC2086
tar -C /data -czf - app_storage/app/private public_media \
    | age ${recipient_args} > "${NAME}-files.tar.gz.age.partial" \
    || fail "files archive"
mv "${NAME}-files.tar.gz.age.partial" "${NAME}-files.tar.gz.age"

sha256sum "${NAME}-db.dump.age" "${NAME}-files.tar.gz.age" > "${NAME}.sha256"
log "encrypted: $(du -h "${NAME}-db.dump.age" | cut -f1) database, $(du -h "${NAME}-files.tar.gz.age" | cut -f1) files"

FILES="${NAME}-db.dump.age ${NAME}-files.tar.gz.age ${NAME}.sha256"

case "${TARGET}" in
    scp)
        : "${BACKUP_SCP_DESTINATION:?BACKUP_SCP_DESTINATION must be user@host:/path}"
        # shellcheck disable=SC2086
        scp -q -P "${BACKUP_SCP_PORT:-22}" -i /backup-ssh/id_ed25519 \
            -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=/backup-ssh/known_hosts \
            ${FILES} "${BACKUP_SCP_DESTINATION%/}/" \
            || fail "scp to ${BACKUP_SCP_DESTINATION}"
        ;;
    s3)
        : "${BACKUP_S3_BUCKET:?BACKUP_S3_BUCKET must be set}"
        : "${BACKUP_S3_ACCESS_KEY_ID:?}"
        : "${BACKUP_S3_SECRET_ACCESS_KEY:?}"
        export AWS_ACCESS_KEY_ID="${BACKUP_S3_ACCESS_KEY_ID}"
        export AWS_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET_ACCESS_KEY}"
        export AWS_DEFAULT_REGION="${BACKUP_S3_REGION:-us-east-1}"
        endpoint_args=""
        [ -n "${BACKUP_S3_ENDPOINT:-}" ] && endpoint_args="--endpoint-url ${BACKUP_S3_ENDPOINT}"
        for file in ${FILES}; do
            # shellcheck disable=SC2086
            aws s3 cp --only-show-errors ${endpoint_args} "${file}" "s3://${BACKUP_S3_BUCKET}/${BACKUP_S3_PREFIX:-madeeasy}/${file}" \
                || fail "upload of ${file} to s3://${BACKUP_S3_BUCKET}"
        done
        ;;
    local)
        log "WARNING: BACKUP_TARGET=local, so this backup is only on this server."
        ;;
    *)
        fail "unknown BACKUP_TARGET '${TARGET}' (scp, s3 or local)"
        ;;
esac

find "${LOCAL_DIR}" -maxdepth 1 -name 'madeeasy-*' -type f -mtime "+${KEEP_LOCAL_DAYS}" -delete
date -u +%FT%TZ > /backups/last-success
echo "${NAME} ${TARGET}" >> /backups/last-success
log "done ${NAME}"
