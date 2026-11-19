#!/usr/bin/env bash
# Sauvegarde de StockFlow : base de données et fichiers téléversés (images produits).
#
#   scripts/backup.sh [dossier_de_destination]     (défaut : ./backups)
#
# Produit deux fichiers horodatés et supprime les sauvegardes de plus de RETENTION_DAYS jours.
# À planifier chaque nuit (cron), voir docs/07-deployment/backup-restore.md.
set -euo pipefail
# Les sauvegardes contiennent toute la base : lisibles par leur seul propriétaire.
umask 077

COMPOSE="$(dirname "${BASH_SOURCE[0]}")/prod-compose.sh"
DEST="${1:-./backups}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
STAMP="$(date +%Y-%m-%d_%H-%M)"

mkdir -p "$DEST"

# --single-transaction : copie cohérente d'InnoDB sans bloquer les ventes en cours.
# Mot de passe passé par variable d'environnement, pas sur la ligne de commande.
$COMPOSE exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --triggers --set-gtid-purged=OFF "$MYSQL_DATABASE"' \
    | gzip > "$DEST/stockflow-db_$STAMP.sql.gz"

$COMPOSE exec -T app tar -czf - -C /var/www/html/storage app > "$DEST/stockflow-files_$STAMP.tar.gz"

# Une sauvegarde vide ou tronquée ne doit pas passer inaperçue.
gzip -t "$DEST/stockflow-db_$STAMP.sql.gz"
if ! gunzip -c "$DEST/stockflow-db_$STAMP.sql.gz" | tail -n 1 | grep -q "Dump completed"; then
    echo "ERREUR : la sauvegarde de la base est incomplète." >&2
    exit 1
fi

find "$DEST" -name 'stockflow-*' -type f -mtime "+$RETENTION_DAYS" -delete

echo "Sauvegarde terminée : $DEST/stockflow-db_$STAMP.sql.gz et $DEST/stockflow-files_$STAMP.tar.gz"
