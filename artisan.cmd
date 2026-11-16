@echo off
rem Lance une commande artisan dans le conteneur Docker de StockFlow.
rem Exemple : artisan.cmd migrate:status
rem Le PHP du poste n'est pas utilisé : le projet exige PHP 8.3 au minimum, fourni par le conteneur.
cd /d "%~dp0"
docker compose exec app php artisan %*
