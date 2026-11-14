#!/usr/bin/env bash
# Déploiement ou mise à jour de StockFlow en production.
#
#   scripts/deploy.sh [version]      (défaut : identifiant court du commit courant)
#
# Étapes : sauvegarde, construction de l'image, maintenance, migrations, caches,
# redémarrage, contrôles. En cas d'échec après la sauvegarde, voir la procédure de
# retour arrière dans docs/07-deployment/deployment.md.
set -euo pipefail

COMPOSE="docker compose -f compose.prod.yaml --env-file .env.production"
export APP_VERSION="${1:-$(git rev-parse --short HEAD)}"

[ -f .env.production ] || { echo ".env.production manquant (voir .env.example)." >&2; exit 1; }
grep -q '^APP_ENV=production' .env.production || { echo "APP_ENV doit valoir production." >&2; exit 1; }
grep -q '^APP_DEBUG=false' .env.production || { echo "APP_DEBUG doit valoir false en production." >&2; exit 1; }

echo "1/7 Sauvegarde avant déploiement"
if $COMPOSE ps --status running db >/dev/null 2>&1 && [ -n "$($COMPOSE ps -q db)" ]; then
    scripts/backup.sh ./backups
else
    echo "    Première installation : rien à sauvegarder."
fi

echo "2/7 Construction de l'image stockflow-app:$APP_VERSION"
$COMPOSE build app

echo "3/7 Démarrage de la base"
$COMPOSE up -d db

echo "4/7 Mise en maintenance"
$COMPOSE run --rm --no-deps app php artisan down --retry=60 2>/dev/null || true

echo "5/7 Migrations"
$COMPOSE run --rm app php artisan migrate --force
$COMPOSE run --rm app php artisan db:seed --class=ReferenceDataSeeder --force

echo "6/7 Redémarrage et caches"
$COMPOSE up -d --remove-orphans
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache
$COMPOSE exec -T app php artisan up

echo "7/7 Contrôles après déploiement"
$COMPOSE exec -T app php artisan stock:verify
code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:${APP_PORT:-8080}/up")
[ "$code" = "200" ] || { echo "ERREUR : /up répond $code" >&2; exit 1; }
code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:${APP_PORT:-8080}/login")
[ "$code" = "200" ] || { echo "ERREUR : /login répond $code" >&2; exit 1; }

echo "Déploiement de la version $APP_VERSION terminé."
