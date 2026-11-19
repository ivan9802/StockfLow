#!/usr/bin/env bash
# Démarrage quotidien de StockFlow sur un poste déjà installé (scripts/install.sh).
#
#   bash scripts/start.sh             démarre les conteneurs et ouvre l'application
#   bash scripts/start.sh --no-open   sans ouvrir le navigateur
#
# Arrêt en fin de journée : docker compose stop (les données sont conservées).
# Équivalent Windows : scripts/start.ps1.
set -euo pipefail
export MSYS_NO_PATHCONV=1

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

OPEN=1
for arg in "$@"; do
    case "$arg" in
        --no-open) OPEN=0 ;;
        -h|--help) sed -n '2,8p' "$0"; exit 0 ;;
        *) echo "Option inconnue : $arg (voir --help)" >&2; exit 2 ;;
    esac
done

fail() { printf 'ERREUR : %s\n' "$1" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || fail "Docker est introuvable."
docker info >/dev/null 2>&1 || fail "Le démon Docker ne répond pas. Lancez Docker Desktop puis relancez."
[ -f .env ] || fail ".env absent : lancez d'abord bash scripts/install.sh."

APP_PORT="$(printenv APP_PORT || true)"
if [ -z "$APP_PORT" ]; then
    APP_PORT="$(grep -E '^APP_PORT=' .env | tail -n 1 | cut -d= -f2- | tr -d '"\r' || true)"
fi
APP_PORT="${APP_PORT:-8080}"
URL="http://localhost:$APP_PORT"

echo "Démarrage des conteneurs (après un redémarrage du poste, MySQL peut demander quelques minutes)..."
docker compose up -d --wait --wait-timeout 600 \
    || fail "Les conteneurs n'ont pas démarré. Voir « docker compose ps » et « docker compose logs »."

code="000"
for _ in $(seq 1 60); do
    code="$(curl -s -o /dev/null -w '%{http_code}' "$URL/up" || true)"
    [ "$code" = "200" ] && break
    sleep 2
done
[ "$code" = "200" ] || fail "$URL/up répond $code. Voir « docker compose logs app web »."

echo "StockFlow répond sur $URL"

if [ "$OPEN" -eq 1 ]; then
    if command -v xdg-open >/dev/null 2>&1; then
        xdg-open "$URL" >/dev/null 2>&1 || true
    elif command -v open >/dev/null 2>&1; then
        open "$URL" || true
    elif command -v cmd.exe >/dev/null 2>&1; then
        cmd.exe /c start "" "$URL" || true
    fi
fi
