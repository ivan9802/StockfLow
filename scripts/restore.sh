#!/usr/bin/env bash
# Restauration de StockFlow à partir d'une sauvegarde produite par backup.sh.
#
#   scripts/restore.sh backups/stockflow-db_AAAA-MM-JJ_HH-MM.sql.gz [backups/stockflow-files_....tar.gz]
#
# La base actuelle est REMPLACÉE. L'application passe en maintenance pendant l'opération.
set -euo pipefail

COMPOSE="$(dirname "${BASH_SOURCE[0]}")/prod-compose.sh"
DB_DUMP="${1:?Indiquez le fichier de sauvegarde de la base (.sql.gz)}"
FILES_ARCHIVE="${2:-}"

[ -f "$DB_DUMP" ] || { echo "Fichier introuvable : $DB_DUMP" >&2; exit 1; }
gzip -t "$DB_DUMP"

read -r -p "La base de production va être remplacée par $DB_DUMP. Taper RESTAURER pour confirmer : " answer
[ "$answer" = "RESTAURER" ] || { echo "Abandon."; exit 1; }

$COMPOSE exec -T app php artisan down --retry=60

# La base est vidée avant l'import : une table créée après la sauvegarde (migration
# d'une version plus récente) bloquerait sinon les déploiements suivants.
$COMPOSE exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "DROP DATABASE \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`$MYSQL_DATABASE\`.* TO \`$MYSQL_USER\`@\`%\`;"'
gunzip -c "$DB_DUMP" | $COMPOSE exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot "$MYSQL_DATABASE"'

if [ -n "$FILES_ARCHIVE" ]; then
    [ -f "$FILES_ARCHIVE" ] || { echo "Archive introuvable : $FILES_ARCHIVE" >&2; exit 1; }
    $COMPOSE exec -T app tar -xzf - -C /var/www/html/storage < "$FILES_ARCHIVE"
fi

# Contrôle immédiat : l'historique restauré doit redonner les niveaux de stock.
# En cas d'écart, l'application reste volontairement en maintenance.
if ! $COMPOSE exec -T app php artisan stock:verify; then
    echo "ERREUR : la base restaurée est incohérente. L'application reste en maintenance." >&2
    exit 1
fi
$COMPOSE exec -T app php artisan up

echo "Restauration terminée."
