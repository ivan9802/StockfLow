# Déploiement sans Docker

La voie de référence est Docker Compose (`deployment.md`, décision D-003). Ce document décrit l'alternative : un serveur ou un hébergement Laravel classique, avec PHP-FPM, Nginx ou Apache et MySQL 8.4. Elle est documentée, pas exécutée : aucune installation de ce type n'a été faite pour le projet au 19/11/2026.

## Quand la choisir

| Situation | Voie conseillée |
| :-- | :-- |
| Serveur virtuel (VPS) sous Linux où l'on peut installer Docker | Docker (`deployment.md`) |
| Serveur géré par un prestataire qui fournit PHP-FPM et MySQL, sans Docker | Ce document |
| Hébergement mutualisé sans accès SSH, sans Composer ou sans PHP 8.4 | Déconseillé : impossible de lancer les migrations et `stock:verify`, versions souvent anciennes |

Points à vérifier auprès de l'hébergeur avant de choisir :

- PHP 8.4 en FPM, avec les extensions listées plus bas.
- MySQL 8.4 avec InnoDB. MariaDB n'est pas la base retenue (D-002) : les tests de concurrence et la contrainte `CHECK` sur le stock n'ont été vérifiés que sur MySQL 8.4.
- Accès SSH, Composer 2, possibilité de définir la racine web sur le dossier `public/`.
- HTTPS (certificat Let's Encrypt ou équivalent).
- Une tâche cron, pour les sauvegardes.

## Ce qu'il faut sur le serveur

| Élément | Version |
| :-- | :-- |
| PHP-FPM et PHP CLI | 8.4 |
| Extensions PHP | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `opcache` |
| Composer | 2 |
| MySQL | 8.4 LTS |
| Nginx 1.28 ou Apache 2.4 avec `mod_rewrite` et `proxy_fcgi` | |
| Node.js 22 | Seulement pour compiler les assets ; peut se faire sur une autre machine |

Le fuseau `Africa/Abidjan` doit être réglé dans `php.ini` (`date.timezone`), comme dans `docker/php/php.ini`. Reprenez aussi `upload_max_filesize = 4M`, `post_max_size = 8M`, `expose_php = Off`, et, en production, les réglages de `docker/php/opcache-prod.ini`.

Queue de travaux : inutile. `QUEUE_CONNECTION=sync`, aucun travail en arrière-plan, aucun processus `queue:work` à surveiller.

Ordonnanceur Laravel : inutile à ce jour. L'application ne déclare aucune tâche planifiée (`routes/console.php` ne contient que la commande d'exemple `inspire`). Les sessions et le cache en base sont purgés par tirage au sort lors des requêtes. La seule tâche planifiée est la sauvegarde (section 7).

## 1. Base de données

```sql
CREATE DATABASE stockflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'stockflow'@'localhost' IDENTIFIED BY '<mot de passe fort>';
GRANT ALL PRIVILEGES ON stockflow.* TO 'stockflow'@'localhost';
```

Réglez le serveur MySQL avec `character-set-server=utf8mb4`, `collation-server=utf8mb4_unicode_ci` et le fuseau horaire du système sur `Africa/Abidjan` (UTC+0, sans heure d'été). Ne reprenez pas les réglages allégés de `compose.yaml` (`innodb-flush-log-at-trx-commit=2`, `innodb-doublewrite=OFF`) : ils réservent le développement.

## 2. Code et dépendances

Avec un utilisateur dédié, par exemple `stockflow`, membre du groupe du serveur web (`www-data` sous Debian et Ubuntu) :

```bash
git clone https://github.com/ivan9802/StockfLow.git /var/www/stockflow
cd /var/www/stockflow
git checkout main
composer install --no-dev --optimize-autoloader --no-interaction
```

Assets : sur le serveur si Node 22 y est installé, sinon sur un poste puis copie du dossier `public/build` :

```bash
npm ci
npm run build
```

`node_modules` n'est pas nécessaire en production : supprimez-le après la compilation.

## 3. Fichier `.env`

```bash
cp .env.example .env
chmod 640 .env
php artisan key:generate
```

Réglez les mêmes valeurs que pour la production Docker (`deployment.md`, section 3), sauf :

| Variable | Valeur sans Docker |
| :-- | :-- |
| `DB_HOST` | `127.0.0.1` ou l'adresse fournie par l'hébergeur |
| `DB_PORT` | `3306` ou celui de l'hébergeur |
| `TRUSTED_PROXIES` | Vide si Nginx ou Apache reçoit directement le HTTPS ; `*` seulement derrière un proxy inverse ou un équilibreur de charge qui ne laisse passer que son trafic |
| `DB_ROOT_PASSWORD`, `APP_PORT`, `FORWARD_DB_PORT`, `VITE_PORT` | À supprimer : propres à Docker Compose. Le mot de passe root de MySQL ne doit pas figurer dans le `.env` lu par PHP |
| `ADMIN_PASSWORD` | À fournir seulement pour la commande `db:seed` du premier déploiement (`ADMIN_PASSWORD=... php artisan db:seed --class=ReferenceDataSeeder --force`), jamais dans `.env` |

Aussi : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`.

## 4. Droits sur les fichiers

Le serveur web doit lire tout le projet, mais n'écrire que dans `storage/` et `bootstrap/cache/` :

```bash
sudo chown -R stockflow:www-data /var/www/stockflow
sudo find /var/www/stockflow -type d -exec chmod 750 {} \;
sudo find /var/www/stockflow -type f -exec chmod 640 {} \;
sudo chmod -R ug+rwX /var/www/stockflow/storage /var/www/stockflow/bootstrap/cache
sudo chmod 750 /var/www/stockflow/artisan
```

Les images des produits sont dans `storage/app/private`, jamais servies directement : elles passent par une route réservée aux utilisateurs connectés. `php artisan storage:link` est inutile.

## 5. Migrations et données de référence

```bash
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan stock:verify
```

Notez le mot de passe administrateur s'il est affiché. Ne chargez jamais `DemoSeeder` en production.

## 6. Serveur web

### Nginx et PHP-FPM

La configuration reprend `docker/nginx/default.conf` : seul `public/index.php` est exécutable, les fichiers cachés sont refusés.

```nginx
server {
    listen 80;
    server_name stock.example.ci;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name stock.example.ci;
    root /var/www/stockflow/public;
    index index.php;
    charset utf-8;
    client_max_body_size 8m;
    server_tokens off;

    ssl_certificate     /etc/letsencrypt/live/stock.example.ci/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/stock.example.ci/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;
}
```

Le pool PHP-FPM tourne sous `www-data` (valeur par défaut des paquets Debian et Ubuntu).

### Apache

Racine du site sur `/var/www/stockflow/public`, `AllowOverride All` pour que le `public/.htaccess` de Laravel s'applique, modules `rewrite`, `headers` et `proxy_fcgi` activés, PHP-FPM relié par `SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"`. N'utilisez pas `mod_php` avec un autre PHP que 8.4.

## 7. Tâches planifiées

Une seule : la sauvegarde. `scripts/backup.sh` est écrit pour Docker ; sans Docker, la crontab de l'utilisateur `stockflow` fait le même travail :

```cron
# Sauvegarde chaque nuit à 2 h 30, conservation 30 jours
30 2 * * * cd /var/www/stockflow && mysqldump --defaults-extra-file=/home/stockflow/.my.cnf --single-transaction --routines --triggers --set-gtid-purged=OFF stockflow | gzip > /var/backups/stockflow/stockflow-db_$(date +\%Y-\%m-\%d_\%H-\%M).sql.gz && tar -czf /var/backups/stockflow/stockflow-files_$(date +\%Y-\%m-\%d_\%H-\%M).tar.gz -C storage app && find /var/backups/stockflow -name 'stockflow-*' -mtime +30 -delete
```

`/home/stockflow/.my.cnf`, en `chmod 600`, contient les identifiants et évite de les mettre dans la crontab :

```ini
[client]
user=stockflow
password=<mot de passe>
```

Les fichiers produits ont le même format que ceux de `backup.sh` : la procédure de restauration de `backup-restore.md` s'applique, en remplaçant les commandes `docker compose ... exec` par leurs équivalents directs (`gunzip -c ... | mysql stockflow`, `tar -xzf ... -C storage`, puis `php artisan stock:verify`).

Si une tâche Laravel est ajoutée un jour à `routes/console.php`, ajoutez aussi :

```cron
* * * * * cd /var/www/stockflow && php artisan schedule:run >> /dev/null 2>&1
```

## 8. Journaux

`LOG_STACK=daily` : un fichier `storage/logs/laravel-AAAA-MM-JJ.log` par jour, conservé `LOG_DAILY_DAYS` jours (30 par défaut), supprimé ensuite par Laravel lui-même. Les journaux de Nginx et PHP-FPM tournent avec `logrotate`, configuré par les paquets de la distribution.

## 9. Mise à jour

```bash
cd /var/www/stockflow
# sauvegarde d'abord (section 7, lancée à la main)
php artisan down --retry=60
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build && rm -rf node_modules
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl reload php8.4-fpm      # vide l'OPcache
php artisan stock:verify
php artisan up
```

Le rechargement de PHP-FPM est indispensable si `opcache.validate_timestamps=0` : sinon l'ancien code reste en mémoire.

Retour arrière : `git checkout <commit précédent>`, mêmes commandes, puis restauration de la sauvegarde prise avant la mise à jour.

## Différences avec la voie Docker

| Sujet | Docker | Sans Docker |
| :-- | :-- | :-- |
| Versions PHP, MySQL, Nginx | Fixées par les images | Dépendent du serveur ; à vérifier à chaque mise à jour système |
| Mise à jour | `scripts/deploy.sh` (sauvegarde, maintenance, migrations, contrôles) | Commandes de la section 9, à la main |
| Retour arrière du code | Image de la version précédente reconstruite | `git checkout` puis réinstallation des dépendances |
| Sauvegarde | `scripts/backup.sh` | Ligne cron de la section 7 |
| Isolation | Conteneurs, MySQL sans port publié | À assurer par la configuration du serveur (MySQL sur `127.0.0.1`, pare-feu) |
