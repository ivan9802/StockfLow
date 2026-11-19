#!/usr/bin/env bash
# Installation locale de StockFlow (développement ou démonstration), depuis un clone neuf.
#
#   bash scripts/install.sh               installation avec les données de référence
#   bash scripts/install.sh --demo        idem, puis chargement du jeu de démonstration
#   bash scripts/install.sh --check       vérifie seulement les prérequis
#
# Options :
#   --demo   remplace la base de développement par le jeu de démonstration (quelques
#            minutes). Si la base contient déjà des comptes, une
#            confirmation est demandée.
#   --yes    répond oui à cette confirmation (exécution sans terminal, par exemple).
#   --check  contrôle Docker, Docker Compose et les fichiers du projet, puis s'arrête.
#
# Le script se relance sans danger : il n'écrase jamais un .env existant, ne régénère
# pas une APP_KEY déjà présente et ne recharge la démonstration que si on le demande.
# Équivalent Windows : scripts/install.ps1. Documentation : docs/07-deployment/local-install.md.
#
# Ports : APP_PORT, FORWARD_DB_PORT et VITE_PORT sont lus dans .env. Une variable
# d'environnement du même nom l'emporte (utile pour une seconde installation sur le poste).
set -euo pipefail

# Git Bash : ne pas convertir les chemins passés aux conteneurs.
export MSYS_NO_PATHCONV=1

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

DEMO=0
ASSUME_YES=0
CHECK_ONLY=0
for arg in "$@"; do
    case "$arg" in
        --demo) DEMO=1 ;;
        --yes|-y) ASSUME_YES=1 ;;
        --check) CHECK_ONLY=1 ;;
        -h|--help) sed -n '2,20p' "$0"; exit 0 ;;
        *) echo "Option inconnue : $arg (voir --help)" >&2; exit 2 ;;
    esac
done

step_no=0
step() { step_no=$((step_no + 1)); printf '\n[%s] %s\n' "$step_no" "$1"; }
fail() { printf '\nERREUR : %s\n' "$1" >&2; exit 1; }
on_exit() {
    local code=$?
    if [ "$code" -ne 0 ]; then
        printf '\nInstallation interrompue (code %s). Corrigez la cause ci-dessus puis relancez le script : il reprend sans casse.\n' "$code" >&2
    fi
}
trap on_exit EXIT

# Valeur d'une variable : environnement d'abord, puis .env, puis valeur par défaut.
env_value() {
    local name="$1" default="$2" value=""
    value="$(printenv "$name" || true)"
    if [ -z "$value" ] && [ -f .env ]; then
        value="$(grep -E "^${name}=" .env | tail -n 1 | cut -d= -f2- | tr -d '"\r' || true)"
    fi
    printf '%s' "${value:-$default}"
}

check_prerequisites() {
    command -v docker >/dev/null 2>&1 \
        || fail "Docker est introuvable. Installez Docker Desktop (Windows, macOS) ou Docker Engine (Linux)."
    docker info >/dev/null 2>&1 \
        || fail "Le démon Docker ne répond pas. Lancez Docker Desktop (ou « sudo systemctl start docker ») puis relancez."
    local compose_version
    compose_version="$(docker compose version --short 2>/dev/null)" \
        || fail "Docker Compose v2 (commande « docker compose ») est introuvable."
    case "$compose_version" in
        v1.*|1.*) fail "Docker Compose $compose_version est trop ancien : la version 2 au moins est requise." ;;
    esac
    [ -f compose.yaml ] && [ -f .env.example ] && [ -f docker/php/Dockerfile ] \
        || fail "Fichiers du projet introuvables dans $ROOT (compose.yaml, .env.example, docker/php/Dockerfile)."
    echo "    Docker $(docker version --format '{{.Server.Version}}'), Docker Compose $compose_version, projet dans $ROOT"
}

# Nombre de comptes dans la base de développement (0 si la table n'existe pas encore).
user_count() {
    docker compose exec -T db sh -c \
        'MYSQL_PWD="$MYSQL_PASSWORD" mysql -N -B -u"$MYSQL_USER" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM users" 2>/dev/null' \
        | tr -d '\r' || true
}

artisan() { docker compose exec -T app php artisan "$@"; }

# Code HTTP d'une adresse, avec curl s'il est présent, sinon depuis le conteneur web.
http_status() {
    if command -v curl >/dev/null 2>&1; then
        curl -s -o /dev/null -w '%{http_code}' "$1" || true
    else
        docker compose exec -T web wget -q -O /dev/null http://127.0.0.1/up >/dev/null 2>&1 && echo 200 || echo 000
    fi
}

step "Vérification des prérequis"
check_prerequisites
if [ "$CHECK_ONLY" -eq 1 ]; then
    trap - EXIT
    echo "    Prérequis présents."
    exit 0
fi

step "Fichier de configuration .env"
if [ -f .env ]; then
    echo "    .env existe déjà : conservé tel quel."
else
    cp .env.example .env
    echo "    .env créé à partir de .env.example."
fi
if grep -qE '^APP_ENV=production' .env; then
    fail ".env indique APP_ENV=production : ce script installe un poste de développement. Pour la production, voir scripts/deploy.sh."
fi

APP_PORT="$(env_value APP_PORT 8080)"

step "Construction et démarrage des conteneurs (la première fois : plusieurs minutes)"
# --wait rend la main quand MySQL répond à son contrôle de santé (jusqu'à 10 minutes
# après un redémarrage du poste).
docker compose up -d --build --wait --wait-timeout 600 \
    || fail "Les conteneurs n'ont pas démarré. Voir « docker compose ps » et « docker compose logs db app »."

step "Attente de MySQL"
ready=0
for _ in $(seq 1 60); do
    if docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping -h 127.0.0.1 -uroot --silent' >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 5
done
[ "$ready" -eq 1 ] || fail "MySQL ne répond pas après 5 minutes. Voir « docker compose logs db »."
echo "    MySQL répond."

step "Dépendances PHP (composer install)"
docker compose exec -T app composer install --no-interaction --prefer-dist --no-progress

step "Clé de l'application"
if grep -qE '^APP_KEY=base64:.+' .env; then
    echo "    APP_KEY déjà définie : conservée."
else
    artisan key:generate --force
fi

step "Dépendances JavaScript (npm ci)"
docker compose exec -T app npm ci --no-audit --no-fund

step "Compilation des CSS et JavaScript (npm run build, jusqu'à quelques minutes sous Windows)"
docker compose exec -T app npm run build

seed_output=""
if [ "$DEMO" -eq 1 ]; then
    users="$(user_count)"
    if [ -n "$users" ] && [ "$users" != "0" ]; then
        echo
        echo "La base de développement contient $users compte(s). Le jeu de démonstration la remplace entièrement."
        if [ "$ASSUME_YES" -ne 1 ]; then
            if [ -t 0 ]; then
                read -r -p "Taper OUI pour continuer : " answer
                [ "$answer" = "OUI" ] || fail "Chargement de la démonstration abandonné. La base n'a pas été modifiée."
            else
                fail "Confirmation impossible sans terminal : relancez avec --demo --yes."
            fi
        fi
    fi
    step "Base de démonstration (migrate:fresh et DemoSeeder, quelques minutes)"
    artisan migrate:fresh --force --seed --seeder=DemoSeeder
    step "Contrôle du stock"
    artisan stock:verify
else
    step "Migrations"
    artisan migrate --force
    step "Données de référence (emplacements, premier administrateur)"
    seed_output="$(artisan db:seed --force 2>&1)" || { printf '%s\n' "$seed_output" >&2; fail "Le chargement des données de référence a échoué."; }
    printf '%s\n' "$seed_output"
fi

step "Contrôle de l'application"
code="000"
for _ in $(seq 1 30); do
    code="$(http_status "http://localhost:$APP_PORT/up")"
    [ "$code" = "200" ] && break
    sleep 2
done
[ "$code" = "200" ] || fail "http://localhost:$APP_PORT/up répond $code. Voir « docker compose logs app web »."
echo "    http://localhost:$APP_PORT/up : 200"

trap - EXIT
echo
echo "Installation terminée."
echo
echo "    Adresse : http://localhost:$APP_PORT"
if [ "$DEMO" -eq 1 ]; then
    echo "    Comptes de démonstration, mot de passe commun : demo-stockflow-2026"
    echo "      Administrateur        admin@stockflow.test"
    echo "      Gestionnaire de stock awa.kone@stockflow.test"
    echo "      Vendeur               aminata.diallo@stockflow.test"
else
    # L'adresse n'est annoncée que si le seeder vient de créer le compte : elle est lue
    # dans sa propre ligne, pas dans .env (un administrateur existant peut en avoir une autre).
    created_line="$(printf '%s\n' "$seed_output" | grep 'Compte administrateur créé' | tail -n 1 || true)"
    if [ -n "$created_line" ]; then
        admin_email="$(printf '%s' "$created_line" | sed -E 's/.*Compte administrateur créé : ([^ ,]+).*/\1/')"
        echo "    Administrateur : $admin_email"
        if printf '%s' "$created_line" | grep -q 'mot de passe tiré de ADMIN_PASSWORD'; then
            echo "    Mot de passe provisoire : la valeur de ADMIN_PASSWORD dans .env (le seeder ne la réaffiche pas)."
            echo "    Il doit être changé à la première connexion ; videz ensuite ADMIN_PASSWORD dans .env."
        else
            echo "    Mot de passe provisoire : celui de la ligne « Compte administrateur créé » ci-dessus."
            echo "    Notez-le maintenant : il n'est affiché qu'une fois et doit être changé à la première connexion."
        fi
    else
        echo "    Un administrateur existe déjà : compte inchangé, connectez-vous avec ses identifiants habituels."
    fi
    echo "    Jeu de démonstration : bash scripts/install.sh --demo"
fi
echo
echo "Démarrage des jours suivants : bash scripts/start.sh"
