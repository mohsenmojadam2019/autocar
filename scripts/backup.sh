#!/usr/bin/env sh
set -eu
STAMP=$(date +%Y%m%d-%H%M%S)
DIR=${BACKUP_DIR:-/var/backups/autocar}
mkdir -p "$DIR"
mysqldump -h "${DB_HOST}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" | gzip > "$DIR/db-$STAMP.sql.gz"
tar -czf "$DIR/storage-$STAMP.tar.gz" -C /var/www/html storage/app/public
find "$DIR" -type f -mtime +14 -delete
