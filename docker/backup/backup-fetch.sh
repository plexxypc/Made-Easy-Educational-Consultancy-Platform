#!/bin/sh
# Copy one backup set from the configured destination into /backups/restore,
# ready for backup-restore. See README, "Restoring from a backup".
#
#   backup-fetch            list what the destination holds
#   backup-fetch <stamp>    fetch madeeasy-<stamp>-* (e.g. 20261002T013000Z)
set -eu

STAMP="${1:-}"
TARGET="${BACKUP_TARGET:-local}"
DIR=/backups/restore
mkdir -p "${DIR}"

ssh_opts="-P ${BACKUP_SCP_PORT:-22} -i /backup-ssh/id_ed25519 -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=/backup-ssh/known_hosts"

s3() {
    export AWS_ACCESS_KEY_ID="${BACKUP_S3_ACCESS_KEY_ID:?}"
    export AWS_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET_ACCESS_KEY:?}"
    export AWS_DEFAULT_REGION="${BACKUP_S3_REGION:-us-east-1}"
    if [ -n "${BACKUP_S3_ENDPOINT:-}" ]; then
        aws --endpoint-url "${BACKUP_S3_ENDPOINT}" "$@"
    else
        aws "$@"
    fi
}

if [ -z "${STAMP}" ]; then
    case "${TARGET}" in
        scp)
            host="${BACKUP_SCP_DESTINATION%%:*}"
            path="${BACKUP_SCP_DESTINATION#*:}"
            # shellcheck disable=SC2086
            ssh -p "${BACKUP_SCP_PORT:-22}" -i /backup-ssh/id_ed25519 -o BatchMode=yes -o StrictHostKeyChecking=yes \
                -o UserKnownHostsFile=/backup-ssh/known_hosts "${host}" "ls -1 ${path%/}/ | grep '\.sha256$'"
            ;;
        s3) s3 s3 ls "s3://${BACKUP_S3_BUCKET:?}/${BACKUP_S3_PREFIX:-madeeasy}/" | grep '\.sha256$' ;;
        *) ls -1 /backups/local | grep '\.sha256$' ;;
    esac
    exit 0
fi

for file in "madeeasy-${STAMP}-db.dump.age" "madeeasy-${STAMP}-files.tar.gz.age" "madeeasy-${STAMP}.sha256"; do
    case "${TARGET}" in
        # shellcheck disable=SC2086
        scp) scp -q ${ssh_opts} "${BACKUP_SCP_DESTINATION%/}/${file}" "${DIR}/" ;;
        s3) s3 s3 cp --only-show-errors "s3://${BACKUP_S3_BUCKET:?}/${BACKUP_S3_PREFIX:-madeeasy}/${file}" "${DIR}/${file}" ;;
        *) cp "/backups/local/${file}" "${DIR}/" ;;
    esac
done

cd "${DIR}" && sha256sum -c "madeeasy-${STAMP}.sha256"
echo "fetched madeeasy-${STAMP} into ${DIR}"
