#!/bin/sh
# Entrypoint of the "backup" service: runs backup-now on BACKUP_SCHEDULE.
#
# busybox crond starts jobs with an empty environment, so the container's
# environment (DB_*, BACKUP_*, from .env) is saved to a file each job loads.
# Job output goes to this process's stdout: `docker compose logs backup`.
set -eu

: "${BACKUP_SCHEDULE:=30 2 * * *}"
export TZ="${BACKUP_TZ:-Africa/Lagos}"

if [ -z "${BACKUP_AGE_RECIPIENTS:-}" ]; then
    echo "backup: BACKUP_AGE_RECIPIENTS is empty; refusing to start (backups must be encrypted)." >&2
    exit 1
fi

export -p > /run/backup.env
chmod 600 /run/backup.env

mkdir -p /etc/crontabs
echo "${BACKUP_SCHEDULE} . /run/backup.env && /usr/local/bin/backup-now > /proc/1/fd/1 2>&1" > /etc/crontabs/root

echo "backup: scheduled \"${BACKUP_SCHEDULE}\" (${TZ}), target ${BACKUP_TARGET:-local}."
exec crond -f -l 6 -c /etc/crontabs
