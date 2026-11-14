#!/usr/bin/env bash
# Déploiement ou mise à jour de StockFlow en production.
#
#   scripts/deploy.sh [version]      (défaut : identifiant court du commit courant)
#
# Étapes : contrôles, sauvegarde, construction des images, maintenance, migrations,
# redémarrage, caches, contrôles après déploiement. En cas d'échec après la sauvegarde,
# voir la procédure de retour arrière dans docs/07-deployment/deployment.md.
set -euo pipefail

COMPOSE="docker compose -f compose.prod.yaml --env-file .env.production"
export APP_VERSION="${1:-$(git rev-parse --short HEAD)}"

[ -f .env.production ] || { echo ".env.production manquant (voir .env.example)." >&2; exit 1; }
grep -q '^APP_ENV=production' .env.production || { echo "APP_ENV doit valoir production." >&2; exit 1; }
grep -q '^APP_DEBUG=false' .env.production || { echo "APP_DEBUG doit valoir false en production." >&2; exit 1; }
grep -q '^APP_KEY=base64:' .env.production || { echo "APP_KEY manquante (php artisan key:generate --show)." >&2; exit 1; }

# Le port des contrôles est celui que Compose publie, lu dans .env.production.
APP_PORT="$(grep -E '^APP_PORT=' .env.production | cut -d= -f2 || true)"
APP_PORT="${APP_PORT:-8080}"

app_running() { [ -n "$($COMPOSE ps -q --status running app 2>/dev/null)" ]; }

echo "1/7 Sauvegarde avant déploiement"
if [ -n "$($COMPOSE ps -q --status running db 2>/dev/null)" ]; then
    scripts/backup.sh ./backups
else
    echo "    Première installation : rien à sauvegarder."
fi

echo "2/7 Construction des images stockflow-app et stockflow-web:$APP_VERSION"
$COMPOSE build app web

echo "3/7 Mise en maintenance de la version en service"
if app_running; then
    $COMPOSE exec -T app php artisan down --retry=60
else
    echo "    Aucune version en service."
fi
$COMPOSE up -d db

echo "4/7 Migrations avec la nouvelle image"
$COMPOSE run --rm app php artisan migrate --force
$COMPOSE run --rm app php artisan db:seed --class=ReferenceDataSeeder --force

echo "5/7 Démarrage de la nouvelle version"
# Les conteneurs recréés partent sans le fichier de maintenance : le service reprend ici.
$COMPOSE up -d --remove-orphans

echo "6/7 Caches de configuration, de routes et de vues"
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo "7/7 Contrôles après déploiement"
$COMPOSE exec -T app php artisan stock:verify
for path in /up /login; do
    code="000"
    for _ in $(seq 1 30); do
        code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$APP_PORT$path" || true)
        [ "$code" = "200" ] && break
        sleep 2
    done
    [ "$code" = "200" ] || { echo "ERREUR : $path répond $code" >&2; exit 1; }
    echo "    $path : 200"
done

echo "Déploiement de la version $APP_VERSION terminé."
