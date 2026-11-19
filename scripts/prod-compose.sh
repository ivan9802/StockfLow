#!/usr/bin/env bash
# Docker Compose de production, avec ses deux fichiers de configuration.
#
#   scripts/prod-compose.sh ps
#   scripts/prod-compose.sh exec app php artisan stock:verify
#   scripts/prod-compose.sh logs --since 1h web app
#
# .env.production : configuration de l'application, transmise au conteneur app.
# .env.secrets    : DB_ROOT_PASSWORD (conteneur db seulement) et ADMIN_PASSWORD (premier
#                   déploiement seulement). Jamais transmis au conteneur app.
# Voir docs/07-deployment/deployment.md, section 3.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

[ -f .env.production ] || { echo ".env.production manquant (voir docs/07-deployment/deployment.md, section 3)." >&2; exit 1; }
[ -f .env.secrets ] || { echo ".env.secrets manquant (modèle : docker/env.secrets.example)." >&2; exit 1; }

exec docker compose -f compose.prod.yaml --env-file .env.production --env-file .env.secrets "$@"
