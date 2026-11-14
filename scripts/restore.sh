#!/usr/bin/env bash
# Restauration de StockFlow à partir d'une sauvegarde produite par backup.sh.
#
#   scripts/restore.sh backups/stockflow-db_AAAA-MM-JJ_HH-MM.sql.gz [backups/stockflow-files_....tar.gz]
#
# La base actuelle est REMPLACÉE. L'application passe en maintenance pendant l'opération.
set -euo pipefail

COMPOSE="docker compose -f compose.prod.yaml --env-file .env.production"
DB_DUMP="${1:?Indiquez le fichier de sauvegarde de la base (.sql.gz)}"
FILES_ARCHIVE="${2:-}"

[ -f "$DB_DUMP" ] || { echo "Fichier introuvable : $DB_DUMP" >&2; exit 1; }
gzip -t "$DB_DUMP"

read -r -p "La base de production va être remplacée par $DB_DUMP. Taper RESTAURER pour confirmer : " answer
[ "$answer" = "RESTAURER" ] || { echo "Abandon."; exit 1; }

$COMPOSE exec -T app php artisan down --retry=60

gunzip -c "$DB_DUMP" | $COMPOSE exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'

if [ -n "$FILES_ARCHIVE" ]; then
    [ -f "$FILES_ARCHIVE" ] || { echo "Archive introuvable : $FILES_ARCHIVE" >&2; exit 1; }
    $COMPOSE exec -T app tar -xzf - -C /var/www/html/storage < "$FILES_ARCHIVE"
fi

# Contrôle immédiat : l'historique restauré doit redonner les niveaux de stock.
$COMPOSE exec -T app php artisan stock:verify
$COMPOSE exec -T app php artisan up

echo "Restauration terminée."
