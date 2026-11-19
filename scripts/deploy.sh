#!/usr/bin/env bash
# Déploiement ou mise à jour de StockFlow en production.
#
#   scripts/deploy.sh [--local-rehearsal] [version]   (version par défaut : commit courant)
#
# Étapes : contrôles, sauvegarde, construction des images, maintenance, migrations,
# redémarrage, caches, contrôles après déploiement. En cas d'échec après la sauvegarde,
# voir la procédure de retour arrière dans docs/07-deployment/deployment.md.
#
# Configuration : .env.production (application) et .env.secrets (mot de passe root de
# MySQL, mot de passe initial de l'administrateur), voir docs/07-deployment/deployment.md.
#
# --local-rehearsal : répétition sur un poste, en HTTP. Lève seulement les contrôles
# APP_URL en https et SESSION_SECURE_COOKIE=true. Jamais sur un serveur réel.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

REHEARSAL=0
VERSION_ARG=""
for arg in "$@"; do
    case "$arg" in
        --local-rehearsal) REHEARSAL=1 ;;
        -*) echo "Option inconnue : $arg" >&2; exit 2 ;;
        *) VERSION_ARG="$arg" ;;
    esac
done

COMPOSE="scripts/prod-compose.sh"
export APP_VERSION="${VERSION_ARG:-$(git rev-parse --short HEAD)}"

fail() { echo "ERREUR : $1" >&2; exit 1; }

# Dernière valeur d'une variable dans un fichier .env, sans guillemets ni retour chariot.
value_of() {
    grep -E "^$1=" "$2" 2>/dev/null | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" | tr -d '\r' || true
}

# ---------------------------------------------------------------- Contrôles

[ -f .env.production ] || fail ".env.production manquant (voir docs/07-deployment/deployment.md, section 3)."
[ -f .env.secrets ] || fail ".env.secrets manquant (modèle : docker/env.secrets.example)."

# Les deux fichiers contiennent des secrets : ni l'un ni l'autre ne doit pouvoir partir dans Git.
for f in .env.production .env.secrets; do
    if git ls-files --error-unmatch "$f" >/dev/null 2>&1; then
        fail "$f est suivi par Git. Retirez-le (git rm --cached $f) et changez les mots de passe qu'il contient."
    fi
    git check-ignore -q "$f" || fail "$f n'est pas ignoré par Git : ajoutez la ligne « $f » à .gitignore."
done

[ "$(value_of APP_ENV .env.production)" = "production" ] || fail "APP_ENV doit valoir production."
[ "$(value_of APP_DEBUG .env.production)" = "false" ] || fail "APP_DEBUG doit valoir false en production."
value_of APP_KEY .env.production | grep -q '^base64:.' || fail "APP_KEY manquante (php artisan key:generate --show)."

# Ces deux variables ne doivent pas être lisibles par PHP : leur place est .env.secrets.
for name in DB_ROOT_PASSWORD ADMIN_PASSWORD; do
    if grep -qE "^$name=" .env.production; then
        fail "$name figure dans .env.production, que lit le conteneur app. Déplacez la ligne dans .env.secrets."
    fi
done

# Mots de passe d'exemple ou vides.
DB_PASSWORD_VALUE="$(value_of DB_PASSWORD .env.production)"
DB_ROOT_PASSWORD_VALUE="$(value_of DB_ROOT_PASSWORD .env.secrets)"
case "$DB_PASSWORD_VALUE" in
    ""|secret|root-secret|password) fail "DB_PASSWORD est vide ou vaut une valeur d'exemple : générez-le (openssl rand -hex 24)." ;;
esac
case "$DB_ROOT_PASSWORD_VALUE" in
    ""|secret|root-secret|password) fail "DB_ROOT_PASSWORD (.env.secrets) est vide ou vaut une valeur d'exemple : générez-le (openssl rand -hex 24)." ;;
esac
[ "$DB_PASSWORD_VALUE" != "$DB_ROOT_PASSWORD_VALUE" ] || fail "DB_PASSWORD et DB_ROOT_PASSWORD doivent être différents."

APP_URL_VALUE="$(value_of APP_URL .env.production)"
SECURE_COOKIE_VALUE="$(value_of SESSION_SECURE_COOKIE .env.production)"
if [ "$REHEARSAL" -eq 1 ]; then
    echo "!!! RÉPÉTITION LOCALE (--local-rehearsal) : contrôles HTTPS et cookie Secure levés."
    echo "!!! APP_URL=$APP_URL_VALUE, SESSION_SECURE_COOKIE=${SECURE_COOKIE_VALUE:-absent}. Ne jamais utiliser sur un serveur réel."
else
    case "$APP_URL_VALUE" in
        https://*) ;;
        *) fail "APP_URL doit commencer par https:// (valeur actuelle : ${APP_URL_VALUE:-vide}). Répétition sur un poste : --local-rehearsal." ;;
    esac
    [ "$SECURE_COOKIE_VALUE" = "true" ] || fail "SESSION_SECURE_COOKIE doit valoir true (valeur actuelle : ${SECURE_COOKIE_VALUE:-absente}). Répétition sur un poste : --local-rehearsal."
fi

# Le port des contrôles est celui que Compose publie, lu dans .env.production.
APP_PORT="$(value_of APP_PORT .env.production)"
APP_PORT="${APP_PORT:-8080}"

app_running() { [ -n "$($COMPOSE ps -q --status running app 2>/dev/null)" ]; }

# ---------------------------------------------------------------- Déploiement

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
# ADMIN_PASSWORD ne sert qu'à créer le premier administrateur : il est passé à ce seul
# conteneur éphémère, par l'environnement (pas sur la ligne de commande).
ADMIN_PASSWORD="$(value_of ADMIN_PASSWORD .env.secrets)"
export ADMIN_PASSWORD
$COMPOSE run --rm -e ADMIN_PASSWORD app php artisan db:seed --class=ReferenceDataSeeder --force
unset ADMIN_PASSWORD

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
