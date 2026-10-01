#!/bin/sh
# Restore a backup made by backup-now. See README, "Restoring from a backup".
#
#   backup-restore <stamp> <age identity file> [--live]
#
#   <stamp>     e.g. 20261002T013000Z; the files madeeasy-<stamp>-* must be
#               in /backups/restore (copy them there from the destination).
#   <identity>  the age PRIVATE key file (copied in only for the restore,
#               deleted afterwards).
#
# Without --live (a restore DRILL, safe at any time): the database goes into
# a scratch database "<DB_DATABASE>_restore_check" and the files into
# /backups/restore/files; row counts and file counts are printed so they can
# be compared with the live site. Nothing live is touched.
#
# With --live (disaster recovery): the live database is REPLACED and the
# files are written back into the app_storage / public_media volumes. Stop
# app, worker and scheduler first (README) and run this from the one-off
# "backup-restore" container, which mounts those volumes writable.
set -eu
set -o pipefail

STAMP="${1:?usage: backup-restore <stamp> <age identity file> [--live]}"
IDENTITY="${2:?usage: backup-restore <stamp> <age identity file> [--live]}"
MODE="${3:-drill}"
DIR=/backups/restore
NAME="madeeasy-${STAMP}"
: "${DB_HOST:=db}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:?}"
: "${DB_USERNAME:?}"
: "${DB_PASSWORD:?}"
export PGPASSWORD="${DB_PASSWORD}"

log() { echo "restore [$(date -u +%FT%TZ)] $*"; }

cd "${DIR}"
[ -r "${IDENTITY}" ] || { log "identity file ${IDENTITY} not readable"; exit 1; }

log "checking checksums"
sha256sum -c "${NAME}.sha256"

if [ "${MODE}" = "--live" ]; then
    TARGET_DB="${DB_DATABASE}"
    FILES_ROOT=/data
    [ -w /data/app_storage ] || { log "the volumes are read-only here: use the backup-restore service (README)"; exit 1; }
    log "LIVE restore into database ${TARGET_DB} and the storage volumes"
else
    TARGET_DB="${DB_DATABASE}_restore_check"
    FILES_ROOT="${DIR}/files"
    log "drill: database ${TARGET_DB}, files under ${FILES_ROOT}"
    psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d postgres -v ON_ERROR_STOP=1 -q \
        -c "SET client_min_messages = warning" -c "DROP DATABASE IF EXISTS \"${TARGET_DB}\"" -c "CREATE DATABASE \"${TARGET_DB}\""
    rm -rf "${FILES_ROOT}"
    mkdir -p "${FILES_ROOT}"
fi

log "restoring database"
age -d -i "${IDENTITY}" "${NAME}-db.dump.age" \
    | pg_restore -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${TARGET_DB}" \
        --clean --if-exists --no-owner --exit-on-error --single-transaction

log "restoring files"
if [ "${MODE}" = "--live" ]; then
    find /data/app_storage/app/private -mindepth 1 -delete 2>/dev/null || true
    find /data/public_media -mindepth 1 -delete 2>/dev/null || true
fi
age -d -i "${IDENTITY}" "${NAME}-files.tar.gz.age" | tar -C "${FILES_ROOT}" -xzf -
if [ "${MODE}" = "--live" ]; then
    # php-fpm runs as www-data (uid 82 in the Alpine image).
    chown -R 82:82 /data/app_storage/app/private /data/public_media
fi

log "restored database ${TARGET_DB}:"
psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${TARGET_DB}" -At -F ' ' -c "
    SELECT 'users', count(*) FROM users UNION ALL
    SELECT 'orders', count(*) FROM orders UNION ALL
    SELECT 'order_documents', count(*) FROM order_documents UNION ALL
    SELECT 'payments', count(*) FROM payments UNION ALL
    SELECT 'result_checker_pins', count(*) FROM result_checker_pins UNION ALL
    SELECT 'posts', count(*) FROM posts UNION ALL
    SELECT 'migrations', count(*) FROM migrations"
log "files: $(find "${FILES_ROOT}/app_storage/app/private" -type f | wc -l) private, $(find "${FILES_ROOT}/public_media" -type f | wc -l) public"

if [ "${MODE}" != "--live" ]; then
    log "drill finished. Compare the counts with the live site, then: DROP DATABASE \"${TARGET_DB}\"; rm -rf ${FILES_ROOT}"
fi
