# Audit de la configuration applicative avant déploiement

Tâche T-902, agent 4 (backend Laravel), 19/11/2026. Branche `phase-3/initialisation`, arbre de travail non commité au moment de l'audit.

Audit en lecture seule : aucun fichier de code n'a été modifié, aucun test, aucune migration ni aucun seeder n'a été lancé. Une seule requête a touché une base : un `SELECT` de variables de session MySQL sur la base de développement `db` (pas sur `db-test`), sans écriture. Les secrets (APP_KEY, mots de passe) sont notés « [masqué] ».

## Synthèse

La configuration est saine dans son ensemble et fonctionne dans la cible Docker décrite par `compose.prod.yaml` : PHP 8.4.27, Laravel 13.42.0, MySQL 8.4.13, toutes les extensions nécessaires chargées, `composer validate --strict` et `composer check-platform-reqs` sans erreur, aucun avis de sécurité Composer, configuration exportable par `config:cache`, mode strict MySQL actif, fuseau cohérent entre PHP et MySQL.

Aucun constat bloquant pour un déploiement Docker. Cinq constats majeurs, qui tiennent tous à des réglages qui marchent aujourd'hui par effet de bord de l'environnement Docker ou dépendent d'une saisie manuelle non contrôlée :

1. `TRUSTED_PROXIES` est lu par `env()` dans `bootstrap/app.php`, avant le chargement du fichier `.env`. La valeur n'est prise en compte que si elle est une vraie variable d'environnement du processus (cas de `env_file` dans `compose.prod.yaml`, avec `clear_env = no` dans l'image PHP officielle). Avec un fichier `.env` classique, elle est ignorée en silence.
2. `SESSION_SECURE_COOKIE=false` est écrit en dur dans `.env.example`, que la procédure de déploiement fait copier ; le script de déploiement ne le contrôle pas. Oublier de le changer supprime l'attribut `Secure` en production.
3. `composer.json` annonce `php: ^8.3` alors que les dépendances verrouillées exigent PHP 8.4.1 et que `config/database.php` utilise la classe `Pdo\Mysql`, apparue en PHP 8.4.
4. `zend.exception_ignore_args` vaut `0` dans l'image : les traces d'exception écrites dans les journaux peuvent contenir les arguments des fonctions, donc des fragments de mot de passe.
5. Écarts entre `system-architecture.md` (sections 7 et 11) et le code : variables `STOCKFLOW_*` documentées mais non lues, statuts 409 annoncés mais jamais rendus, exceptions métier annoncées comme journalisées au niveau `info` alors qu'elles ne le sont pas du tout.

Pas de tâche planifiée : aucun cron `schedule:run` n'est nécessaire dans l'état actuel.

## Versions constatées

| Composant | Exigé (fichier:ligne) | Installé dans le conteneur `app` | Remarque |
| :-- | :-- | :-- | :-- |
| PHP | `^8.3` (`composer.json:9`) | 8.4.27 (NTS, OPcache) | Le verrou exige en réalité 8.4.1 (Symfony 8.1) |
| Composer | | 2.11.2 | |
| laravel/framework | `^13.17` (`composer.json:12`) | 13.42.0 | Conforme à D-001 |
| barryvdh/laravel-dompdf | `^3.1` (`composer.json:10`) | 3.1.2 | |
| picqer/php-barcode-generator | `^3.3` (`composer.json:13`) | 3.3.0 | |
| laravel/tinker | `^3.0` | 3.1.0 | |
| laravel/pint (dev) | `^1.27` | 1.32.3 | |
| phpunit/phpunit (dev) | `^12.5.12` | 12.5.43 | |
| laravel/pail, laravel/pao, collision, mockery, faker (dev) | | 1.3.0, 1.1.5, 8.9.6, 1.6.15, 1.24.1 | `laravel/pao` n'apparaît pas dans `DECISIONS.md` |
| MySQL | 8.4 (D-002) | 8.4.13 | |
| Nginx (dev et image `web`) | | 1.28-alpine | |

Extensions PHP chargées comparées aux besoins :

| Extension | Chargée | Usage dans StockFlow |
| :-- | :-- | :-- |
| pdo_mysql | oui | connexion MySQL |
| gd | oui | facultative pour dompdf (images raster) ; non utilisée directement par l'application |
| intl | oui | non utilisée directement (le formatage passe par `number_format`, `app/Support/Format.php:19`) |
| zip | oui | Composer seulement |
| bcmath | oui | non utilisée (montants entiers, D-012) |
| mbstring | oui | Laravel, dompdf |
| opcache | oui | `opcache.enable_cli = 0`, réglages de production dans `docker/php/opcache-prod.ini` |
| fileinfo | oui | détection du type MIME des images téléversées |
| sodium | oui | |

Compatibilité :

- PHP 8.3 : incompatible. `composer why-not php 8.3.0` liste 16 paquets Symfony 8.1 qui exigent `>=8.4.1`, et `config/database.php:4,63,83` utilise `Pdo\Mysql` (PHP 8.4).
- PHP 8.5 : `composer why-not php 8.5.0` ne signale aucun paquet bloquant. Non vérifié par exécution (pas d'image 8.5 disponible) : des avis de dépréciation restent possibles.
- MySQL 8.4 : en service en 8.4.13, `sql_mode` strict appliqué, `caching_sha2_password` géré par mysqlnd. Rien d'incompatible relevé.

## Variables d'environnement

Recensement par `grep -rn "env(" config app bootstrap routes database`. Les fichiers de configuration du cadriciel (`vendor/laravel/framework/config/hashing.php`, `broadcasting.php`, `view.php`, etc.) sont fusionnés avec ceux du projet : ils lisent aussi `BCRYPT_ROUNDS`, `BROADCAST_CONNECTION`, `ASSET_URL`, `FRONTEND_URL`, `HASH_DRIVER`.

### Variables propres à l'application ou réglantes

| Variable | Lue où | Dans `.env.example` | Défaut dans le code | Risque |
| :-- | :-- | :-- | :-- | :-- |
| APP_NAME | `config/app.php:16`, `cache.php:121`, `session.php:132`, `database.php:152`, `mail.php:115` | oui (l. 4) | `Laravel` | Mineur : nom de cookie `laravel-session` si absent |
| APP_ENV | `config/app.php:29` | oui (l. 5) | `production` | Bon défaut |
| APP_KEY | `config/app.php:100` | oui, vide (l. 6) | aucun | Contrôlé par `scripts/deploy.sh:17` |
| APP_PREVIOUS_KEYS | `config/app.php:104` | non | vide | Mineur : rotation de clé non documentée |
| APP_DEBUG | `config/app.php:42` | oui, `true` (l. 7) | `false` | Contrôlé par `scripts/deploy.sh:16` |
| APP_URL | `config/app.php:55`, `filesystems.php:44`, `mail.php:49` | oui, `http://localhost:8080` (l. 8) | `http://localhost` | Mineur : non contrôlé au déploiement (HTTPS attendu) |
| APP_TIMEZONE | `config/app.php:68` | oui (l. 13) | `Africa/Abidjan` | Aucun |
| APP_LOCALE, APP_FALLBACK_LOCALE | `config/app.php:81,83` | oui (l. 10-11) | `en` | Mineur : interface en anglais si absentes |
| APP_FAKER_LOCALE | `config/app.php:85` | oui (l. 12) | `en_US` | Aucun en production |
| APP_MAINTENANCE_DRIVER | `config/app.php:122` | oui (l. 15) | `file` | Aucun |
| APP_MAINTENANCE_STORE | `config/app.php:123` | non | `database` | Aucun (pilote `file`) |
| BCRYPT_ROUNDS | cadriciel `hashing.php` | oui (l. 18) | 12 | Aucun |
| LOG_CHANNEL | `config/logging.php:21` | oui (l. 20) | `stack` | Aucun |
| LOG_STACK | `config/logging.php:57` | oui, `daily` (l. 21) | `single` | Mineur : fichier unique sans rotation si absente |
| LOG_LEVEL | `config/logging.php:64,71,79,95,107,118,125` | oui, `debug` (l. 23) | `debug` | Mineur : `warning` attendu en production (doc de déploiement) |
| LOG_DAILY_DAYS | `config/logging.php:72` | non | 14 | Mineur : durée de conservation non documentée |
| LOG_DEPRECATIONS_CHANNEL | `config/logging.php:35` | oui (l. 22) | `null` | Aucun |
| DB_CONNECTION | `config/database.php:20`, `queue.php:106,125` | oui (l. 26) | `sqlite` | Mineur : base SQLite silencieuse si absente |
| DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME | `config/database.php:50-53` | oui (l. 27-30) | `127.0.0.1`, 3306, `laravel`, `root` | Mineur : `root` par défaut |
| DB_PASSWORD | `config/database.php:54` | oui, `secret` (l. 31) | vide | Valeur d'exemple à remplacer (doc de déploiement) |
| DB_URL, DB_SOCKET, DB_CHARSET, DB_COLLATION | `config/database.php:49,55-57` | non | vide, vide, `utf8mb4`, `utf8mb4_unicode_ci` | Aucun |
| MYSQL_ATTR_SSL_CA | `config/database.php:63` | non | vide | Aucun (MySQL sur le réseau Docker interne) |
| SESSION_DRIVER | `config/session.php:21` | oui (l. 41) | `database` | Aucun |
| SESSION_LIFETIME | `config/session.php:35` | oui, 120 (l. 42) | 120 | Conforme à D-010 |
| SESSION_ENCRYPT | `config/session.php:50` | oui, `true` (l. 43) | `false` | Mineur : défaut faible ; le `.env` local vaut `false` |
| SESSION_PATH, SESSION_DOMAIN | `config/session.php:146,159` | oui (l. 44-45) | `/`, null | Aucun |
| SESSION_SECURE_COOKIE | `config/session.php:172` | oui, `false` (l. 46) | null (automatique) | Majeur, voir M-2 |
| SESSION_SAME_SITE | `config/session.php:202` | oui, `lax` (l. 47) | `lax` | Aucun |
| SESSION_COOKIE, SESSION_HTTP_ONLY, SESSION_EXPIRE_ON_CLOSE, SESSION_PARTITIONED_COOKIE, SESSION_CONNECTION, SESSION_TABLE, SESSION_STORE | `config/session.php:130,185,37,215,76,89,104` | non | `stockflow-session`, `true`, `false`, `false`, null, `sessions`, null | Aucun : défauts corrects |
| CACHE_STORE | `config/cache.php:18` | oui (l. 52) | `database` | Aucun |
| CACHE_PREFIX | `config/cache.php:121` | non | `stockflow-cache-` | Aucun |
| QUEUE_CONNECTION | `config/queue.php:16` | oui, `sync` (l. 51) | `database` | Mineur : sans variable, file `database` sans worker |
| FILESYSTEM_DISK | `config/filesystems.php:16` | oui (l. 50) | `local` | Aucun |
| MAIL_MAILER | `config/mail.php:17` | oui, `log` (l. 55) | `log` | Aucun (D-010) |
| MAIL_FROM_ADDRESS, MAIL_FROM_NAME | `config/mail.php:114,115` | non | `hello@example.com` | Aucun : pas d'envoi |
| BROADCAST_CONNECTION | cadriciel `broadcasting.php` | oui (l. 49) | `null` | Aucun (pas de diffusion) |
| TRUSTED_PROXIES | `bootstrap/app.php:26` (hors `config/`) | oui, vide (l. 61) | null | Majeur, voir M-1 |
| VITE_PORT | `app/Http/Middleware/SecurityHeaders.php:30,35` (hors `config/`), `compose.yaml:18` | oui (l. 38) | 5173 | Mineur, voir m-3 |
| ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD | `database/seeders/ReferenceDataSeeder.php:42,43,46` (hors `config/`) | oui (l. 65-67) | `Administrateur`, `admin@stockflow.example`, mot de passe aléatoire | Mineur, voir m-4 |
| COMPANY_NAME, COMPANY_ADDRESS, COMPANY_PHONE, COMPANY_EMAIL, COMPANY_RCCM, COMPANY_PUBLISHER, COMPANY_HOST | `config/stockflow.php:10-16` | oui (l. 70-76) | valeurs fictives ou « à renseigner » | Mineur : mentions légales fictives possibles en production, non contrôlées |

### Variables de `.env.example` jamais lues par le code PHP

| Variable | Lue par | Remarque |
| :-- | :-- | :-- |
| DB_ROOT_PASSWORD | `compose.yaml`, `compose.prod.yaml` | Normal, commenté à la ligne 32 |
| APP_PORT, FORWARD_DB_PORT | `compose.yaml`, `compose.prod.yaml`, `scripts/deploy.sh:20` | Normal |
| VITE_APP_NAME | personne | Aucun `import.meta.env` dans `resources/js` ni `vite.config.js` : à retirer |

### Variables lues mais sans objet pour StockFlow

Redis, Memcached, DynamoDB, S3, SQS, Beanstalkd, Papertrail, Slack, Postmark, Resend, Pusher, Reverb, Ably, Mercure (`config/cache.php`, `database.php:148-179`, `filesystems.php:52-58`, `queue.php:49-71`, `logging.php:86-112`, `services.php`, cadriciel `broadcasting.php`) : pilotes non utilisés, leur absence de `.env.example` est normale.

### Configuration effective dans le conteneur de développement

Relevée par `php artisan config:show` :

- `app` : env `local`, debug `true`, url `http://localhost:8080`, fuseau `Africa/Abidjan`, locale `fr`, clé [masqué], maintenance `file`.
- `session` : driver `database`, lifetime 120, expire_on_close `false`, encrypt `false`, cookie `stockflow-session` (par défaut), secure `false`, http_only `true`, same_site `lax`, serialization `json`, loterie 2/100.
- `logging` : default `stack`, stack vers `daily`, niveau `debug`, max_files 14, dépréciations vers `null`.
- `database` : default `mysql`, hôte `db`, port 3306, base `stockflow`, utilisateur `stockflow`, mot de passe [masqué], `utf8mb4` / `utf8mb4_unicode_ci`, strict `true`, options `[]`.
- `cache` : `database`, préfixe `stockflow-cache-`. `queue` : `sync`, échecs `database-uuids` sur `mysql`. `filesystems` : défaut `local` (racine `storage/app/private`, `serve` à `true`), `public` (racine `storage/app/public`). `mail` : `log`.
- `php artisan about` : configuration et routes non mises en cache, vues en cache.

Le `.env` local diffère de `.env.example` : `SESSION_ENCRYPT=false`, et `TRUSTED_PROXIES`, `ADMIN_*`, `COMPANY_*` absents. Sans conséquence en développement.

Un fichier `.env.production` existe sur le poste (ignoré par Git, `.gitignore:5`) : il sert de répétition locale (`APP_URL=http://localhost:8090`, `SESSION_SECURE_COOKIE=false`, `TRUSTED_PROXIES=*`, `LOG_LEVEL=warning`, `SESSION_ENCRYPT=true`).

## Constats par thème

### Sessions et cookies

- Driver `database` (table `sessions` créée par `database/migrations/0001_01_01_000000_create_users_table.php`), durée 120 minutes d'inactivité, pas d'expiration à la fermeture du navigateur (`config/session.php:35,37`), conforme à D-010.
- Chiffrement : défaut `false` (`config/session.php:50`), `true` dans `.env.example:43`. En base, le contenu de session n'est chiffré que si la variable est posée.
- HttpOnly : `true` par défaut (`config/session.php:185`). Le cookie `XSRF-TOKEN` n'est pas HttpOnly, c'est voulu par Laravel.
- SameSite : `lax` (`config/session.php:202`).
- Nom : `stockflow-session`, dérivé de `APP_NAME` (`config/session.php:130-133`).
- Secure : `env('SESSION_SECURE_COOKIE')` sans défaut (`config/session.php:172`). Si la variable est absente, la valeur est `null` et Symfony pose l'attribut `Secure` seulement quand la requête est reconnue comme HTTPS (`Response::prepare`), ce qui dépend de `TRUSTED_PROXIES` derrière le proxy TLS. Si la variable vaut `false`, comme dans `.env.example:46`, le cookie n'est jamais `Secure`, même en HTTPS.

### Cache, file d'attente, journaux, mail, stockage

- Cache `database` (tables `cache` et `cache_locks`, `0001_01_01_000001_create_cache_table.php`) : sert au limiteur de connexion (5 essais par minute). Les entrées expirées ne sont purgées qu'à la relecture ; volume négligeable.
- File d'attente : `sync` attendu partout. Aucune classe `ShouldQueue` dans `app/`. L'événement `StockLevelChanged` est diffusé après le commit (`app/Services/StockService.php:220`) et son écouteur journalise ses échecs au niveau `critical` (`app/Listeners/CheckStockThresholds.php:24`). Le défaut `database` de `config/queue.php:16` n'aurait d'effet que sur un futur job mis en file.
- Journaux : canal `stack` vers `daily`, fichiers `storage/logs/laravel-AAAA-MM-JJ.log`, 14 jours conservés (`config/logging.php:72`), niveau `debug` par défaut, `warning` prévu en production. En production, `storage/logs` est un volume (`compose.prod.yaml:20`). Le contexte d'une exception contient `userId` et la trace ; aucune donnée de requête. Les tests (`APP_ENV=testing`) écrivent dans le même dossier `storage/logs` que le développement : 9 lignes `testing.ERROR` relevées.
- Mail : `log`, aucun envoi (D-010). Rien à configurer.
- Stockage : les images de produits sont sur le disque privé `local` (`app/Services/ProductService.php:17`, racine `storage/app/private`) et servies par une route authentifiée (`app/Http/Controllers/ProductController.php:108-117`). Le disque `public` n'est pas utilisé : `storage:link` est inutile. En revanche `serve => true` sur le disque `local` (`config/filesystems.php:36`) enregistre deux routes `GET` et `PUT storage/{path}` (`storage.local`, `storage.local.upload`), protégées par signature mais inutiles.

### Gestion des erreurs

- `bootstrap/app.php:43-45` : rendu JSON pour `internal/*` et les requêtes qui attendent du JSON.
- `bootstrap/app.php:49` : `BusinessException` n'est pas journalisée.
- `bootstrap/app.php:51-57` : rendu 422 JSON avec `message` et détails, ou retour à la page précédente avec la saisie et un message flash. Les champs `password`, `password_confirmation` et `current_password` ne sont pas réinjectés (liste `dontFlash` du cadriciel).
- `ImmutableRecordException` hérite de `LogicException` : elle est journalisée et rendue en 500, ce qui est voulu.
- Pages d'erreur françaises `resources/views/errors/{403,404,419,429,500,503}.blade.php` : aucune n'affiche le message de l'exception. Le gabarit charge les styles par `@vite` (`resources/views/errors/layout.blade.php:9`) : il dépend du manifeste Vite, présent dans l'image `app` (`docker/php/Dockerfile:59`).
- `APP_DEBUG` : défaut `false`, contrôlé par `scripts/deploy.sh:16`.
- `zend.exception_ignore_args = 0` et, en développement, `display_errors = 1` (relevés par `php -r ini_get`). En production, `display_errors = Off` est posé par `docker/php/opcache-prod.ini:24`, mais `zend.exception_ignore_args` n'est posé nulle part.

### Base de données

- Connexion `mysql` (`config/database.php:47-65`) : `utf8mb4`, `utf8mb4_unicode_ci` (identique au serveur, `compose.prod.yaml:48-49`), `strict => true`, moteur par défaut du serveur (InnoDB).
- Mode SQL effectif : `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`.
- Options PDO : seulement `ATTR_SSL_CA` si défini (`config/database.php:62-64`) ; les options par défaut de Laravel s'appliquent, préparations natives (`ATTR_EMULATE_PREPARES` à `false`, vérifié).
- Isolation `REPEATABLE-READ`, `innodb_lock_wait_timeout` 50 s : compatible avec `lockForUpdate()`.
- Fuseau : PHP `Africa/Abidjan` (`config/app.php:68` et `docker/php/php.ini:2`) ; MySQL `time_zone = SYSTEM`, `system_time_zone = GMT` (variable `TZ` des services `db`). Abidjan est en UTC+0 sans heure d'été, `NOW()` MySQL et `now()` PHP coïncident (relevé : `2026-11-19 01:37:26` des deux côtés). Aucune clé `timezone` dans la connexion : la cohérence dépend de la variable `TZ` du conteneur MySQL.

### Configurations dangereuses en production et `config:cache`

- `APP_DEBUG`, `APP_ENV`, `APP_KEY` : contrôlés par `scripts/deploy.sh:15-17`.
- `APP_URL` et `SESSION_SECURE_COOKIE` : non contrôlés.
- `config:cache` : la configuration complète s'exporte et se relit (simulation `var_export` puis `eval` dans tinker : 19 groupes, sans erreur ; la commande elle-même n'a pas été lancée pour ne pas écrire `bootstrap/cache/config.php` pendant que des tests tournent). La route en fermeture `routes/web.php:34` est compatible avec `route:cache` depuis Laravel 9.
- Appels `env()` hors de `config/`, qui ne lisent pas le fichier `.env` une fois la configuration en cache :
  - `bootstrap/app.php:26` (`TRUSTED_PROXIES`) : pire que le cas général. Le rappel de `withMiddleware` s'exécute à la résolution du noyau HTTP (`Application::handleRequest`, `ApplicationBuilder::withMiddleware`, vérifié dans `vendor/laravel/framework`), avant l'amorceur `LoadEnvironmentVariables`. Le fichier `.env` n'est donc jamais lu à ce moment, cache ou pas. En production Docker, la valeur arrive quand même par `env_file: .env.production` (`compose.prod.yaml:16`), parce que l'image PHP officielle pose `clear_env = no` (`/usr/local/etc/php-fpm.d/docker.conf:12`, vérifié).
  - `app/Http/Middleware/SecurityHeaders.php:30,35` (`VITE_PORT`) : seulement en environnement `local` avec Vite à chaud.
  - `database/seeders/ReferenceDataSeeder.php:42,43,46` (`ADMIN_*`) : lancé par `scripts/deploy.sh:45` via `compose run`, qui fournit les variables d'environnement. Hors Docker, avec la configuration en cache, les valeurs de `.env` seraient ignorées.
  - Aucun `getenv`, `$_ENV` ni `$_SERVER[...]` dans `app/`, `config/`, `bootstrap/`, `routes/`, `database/`.
- `TrustHosts` n'est pas activé : l'hôte est pris dans `X-Forwarded-Host` du proxy. Acceptable tant que seul le proxy local atteint le service `web`.

### Tâches planifiées

`routes/console.php:6-8` ne contient que la commande d'exemple `inspire`. Aucune tâche `Schedule::`. Les sessions expirées sont purgées par la loterie (2 requêtes sur 100), les journaux par la rotation `daily`, il n'y a ni file d'attente ni e-mail. Aucun cron `schedule:run` n'est nécessaire. Les sauvegardes (`scripts/backup.sh`) relèvent d'un cron système, côté DevOps (T-904).

## Constats classés

### Bloquant

Aucun.

### Majeur

**M-1. `TRUSTED_PROXIES` lu par `env()` avant le chargement de `.env`**, `bootstrap/app.php:26-32`.
Effet hors Docker ou si la variable n'est pas injectée dans le processus : le schéma HTTPS n'est pas reconnu derrière le proxy, donc pas de HSTS (`app/Http/Middleware/SecurityHeaders.php:57`), pas de cookie `Secure` automatique, URL d'assets en `http://` sur une page HTTPS (contenu mixte bloqué), et un seul compteur de tentatives de connexion pour toute l'entreprise. Rien ne signale l'échec.
Correction proposée (périmètre backend) :

```php
// config/stockflow.php
'trusted_proxies' => env('TRUSTED_PROXIES'),
```

```php
// app/Providers/AppServiceProvider.php, dans boot()
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

if ($proxies = config('stockflow.trusted_proxies')) {
    TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
    TrustProxies::withHeaders(
        Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
    );
}
```

et suppression du bloc `if ($proxies = env(...))` de `bootstrap/app.php:26-32`. `TrustProxies` est un middleware global qui lit ces valeurs statiques à chaque requête, après le démarrage des fournisseurs. Un test fonctionnel avec `X-Forwarded-Proto: https` et `config(['stockflow.trusted_proxies' => '*'])` devra vérifier la présence de HSTS (QA).

**M-2. Attribut `Secure` du cookie de session dépendant d'une saisie manuelle**, `.env.example:46`, `config/session.php:172`, `scripts/deploy.sh:15-17`.
`.env.example` pose `SESSION_SECURE_COOKIE=false` et la procédure fait copier ce fichier en `.env.production` (`docs/07-deployment/deployment.md:57`). Une valeur oubliée donne un cookie de session sans `Secure` en production.
Correction proposée :
- `config/session.php:172` : `'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV', 'production') === 'production'),`
- `.env.example:46` : remplacer par `# SESSION_SECURE_COOKIE=true    (vrai par défaut quand APP_ENV=production)`.
- `scripts/deploy.sh` (DevOps) : refuser `SESSION_SECURE_COOKIE=false` et un `APP_URL` qui ne commence pas par `https://`, avec une option explicite pour la répétition locale en HTTP, puisque le `.env.production` du poste utilise `http://localhost:8090`.

**M-3. Contrainte PHP de `composer.json` fausse**, `composer.json:9`, `config/database.php:4,63,83`.
`^8.3` laisse croire que PHP 8.3 suffit. Le verrou exige 8.4.1 (Symfony 8.1) et `Pdo\Mysql` provoque une erreur fatale en 8.3. Un `composer update` lancé sur un PHP 8.3 choisirait d'autres versions que celles testées.
Correction proposée : `"php": "^8.4"` et, dans `"config"`, `"platform": {"php": "8.4.1"}` pour figer la résolution. Puis `composer update --lock` dans le conteneur et `composer validate --strict`.

**M-4. Arguments de fonction dans les traces journalisées**, `docker/php/php.ini` (périmètre DevOps).
`zend.exception_ignore_args` vaut `0` dans les deux images. Les traces écrites par Laravel contiennent les arguments scalaires (tronqués à 15 caractères), ce qui suffit à exposer un mot de passe de 12 caractères si une exception survient dans une fonction qui le reçoit. Contraire à `system-architecture.md` section 6 (« les journaux ne contiennent ni mot de passe ni jeton de session »).
Correction proposée : ajouter `zend.exception_ignore_args = On` dans `docker/php/php.ini`.

**M-5. Documentation d'architecture en désaccord avec le code**, `docs/02-architecture/system-architecture.md` sections 7 et 11 (périmètre architecte).
- Section 11 : `STOCKFLOW_PDF_MAX_ROWS` et `STOCKFLOW_DASHBOARD_REFRESH_SECONDS` annoncées comme lues par `config/stockflow.php` ; elles sont codées en dur (`config/stockflow.php:23,26`).
- Section 11 : « les images utilisent explicitement le disque `public` » ; le code utilise le disque privé `local`, conformément à `CLAUDE.md`. Volume annoncé `storage/app/public`, réel `storage/app` (`compose.prod.yaml:19`). Production annoncée en `LOG_CHANNEL=stderr`, réelle en fichiers `daily` sur volume. `.env.testing` annoncé, absent (la configuration de test est dans `phpunit.xml:23-39`). `config:cache` annoncé à la construction de l'image, fait au déploiement (`scripts/deploy.sh:52-54`).
- Section 7 : statuts 409 pour `InvalidStateTransitionException` et `InventoryAlreadyOpenException`, champ `code` dans la réponse JSON, classe abstraite ; le code rend tout en 422 sans `code` (`bootstrap/app.php:53`, `app/Exceptions/BusinessException.php:11`). Exceptions métier « journalisées au niveau `info` » ; elles ne sont pas journalisées (`bootstrap/app.php:49`).
Correction proposée : aligner le document sur le code. Si l'architecte veut garder la journalisation des refus, remplacer `bootstrap/app.php:49` par `$exceptions->level(BusinessException::class, \Psr\Log\LogLevel::INFO);` et relever `LOG_LEVEL` en conséquence (avec `warning` en production, ces lignes ne seraient de toute façon pas écrites).

### Mineur

**m-1. Défauts faibles dans `config/`** :
- `config/session.php:50` : `env('SESSION_ENCRYPT', true)`.
- `config/app.php:16` : défaut `'StockFlow'`.
- `config/app.php:81,83` : défaut `'fr'`.
- `config/database.php:20`, `config/queue.php:106,125` : défaut `'mysql'`.
- `config/queue.php:16` : défaut `'sync'`.
- `config/logging.php:57` : défaut `'daily'`.

**m-2. Variables lues mais non documentées** : ajouter à `.env.example` `LOG_DAILY_DAYS=14` (avec une phrase sur la conservation des journaux, durée à valider au titre de la loi 2013-450) et `APP_PREVIOUS_KEYS=` (rotation de clé sans perte de session). Retirer `VITE_APP_NAME` (`.env.example:57`), lu par personne.

**m-3. `env('VITE_PORT')` hors de `config/`**, `app/Http/Middleware/SecurityHeaders.php:30,35`. Sans effet en production (branche `local` seulement). Correction : `'vite_port' => (int) env('VITE_PORT', 5173)` dans `config/stockflow.php` et `config('stockflow.vite_port')` dans le middleware.

**m-4. `env('ADMIN_*')` dans un seeder**, `database/seeders/ReferenceDataSeeder.php:42,43,46` (périmètre base de données). Fonctionne dans Docker (variables injectées). Correction : clés `'admin' => ['name' => env('ADMIN_NAME', 'Administrateur'), 'email' => env('ADMIN_EMAIL', 'admin@stockflow.example'), 'password' => env('ADMIN_PASSWORD')]` dans `config/stockflow.php`, lues par `config('stockflow.admin.*')`. Attention : le mot de passe resterait alors dans `bootstrap/cache/config.php` tant qu'il n'est pas retiré de `.env.production` puis la configuration remise en cache ; la doc de déploiement (`deployment.md:112`) le demande déjà.

**m-5. Routes `storage/{path}` inutiles**, `config/filesystems.php:36`. Mettre `'serve' => false` : les images passent par `ProductController::image`. Supprime `storage.local` et `storage.local.upload`.

**m-6. Fuseau MySQL implicite**, `config/database.php:47-65`. Ajouter `'timezone' => '+00:00',` à la connexion `mysql` pour que `SET time_zone` ne dépende plus de la variable `TZ` du conteneur.

**m-7. Extensions non déclarées**, `composer.json:8-14`. Ajouter `"ext-pdo_mysql": "*"`, `"ext-mbstring": "*"`, `"ext-fileinfo": "*"`, `"ext-dom": "*"` pour que `composer check-platform-reqs` les contrôle.

**m-8. Restes du squelette Laravel** :
- `routes/console.php:6-8` : commande `inspire` à retirer.
- `composer.json:64-66` : `post-create-project-cmd` crée `database/database.sqlite`, sans objet.
- `composer.json:82` : `allow-plugins` autorise `pestphp/pest-plugin` alors que Pest n'est pas utilisé.
- `laravel/pao` (`composer.json:18`) est absent de `DECISIONS.md` (paquet de développement, probablement hérité du squelette : à confirmer par le CTO).

**m-9. Mentions légales fictives possibles en production**, `config/stockflow.php:13-16` (`contact@example.com`, « à renseigner »). Le script de déploiement pourrait refuser `COMPANY_RCCM` vide ou égal à la valeur par défaut (DevOps).

**m-10. Journaux de test mêlés aux journaux de développement**, `storage/logs`. Ajouter `<env name="LOG_STACK" value="single"/>` ou `<env name="LOG_CHANNEL" value="null"/>` dans `phpunit.xml` (périmètre QA).

**m-11. `TrustHosts` non activé**, `bootstrap/app.php:18-40`. Option : `$middleware->trustHosts();` (hôtes dérivés de `config('app.url')` à chaque requête). Suppose un `APP_URL` exact en production, sans quoi toutes les requêtes seraient refusées : à décider avec la sécurité (T-905).

## Commandes exécutées

Toutes depuis `D:\projet_encours\stackflow`, sous Git Bash, préfixées par `MSYS_NO_PATHCONV=1 docker compose exec -T app` pour celles qui s'exécutent dans le conteneur.

| Commande | Résultat |
| :-- | :-- |
| `docker compose ps` | `app`, `web`, `db` et `db-test` en service ; aucune action sur `db-test` |
| `php -v` | PHP 8.4.27 (cli) NTS, Zend OPcache |
| `php artisan --version` | Laravel Framework 13.42.0 |
| `composer --version` | Composer 2.11.2 |
| `composer show --direct` | versions du tableau ci-dessus |
| `php -m` | pdo_mysql, gd, intl, zip, bcmath, mbstring, Zend OPcache, fileinfo, sodium présents |
| `composer validate --strict` | `./composer.json is valid`, code 0 |
| `composer check-platform-reqs` | toutes les lignes `success`, code 0 |
| `composer why-not php 8.3.0` | 16 paquets Symfony 8.1 exigent `>=8.4.1` |
| `composer why-not php 8.5.0` et `8.6.0` | aucun paquet bloquant |
| `composer audit` | aucun avis de sécurité |
| `php artisan config:show app`, `session`, `logging`, `database`, `cache`, `queue`, `filesystems`, `mail` | valeurs résumées plus haut, secrets masqués à l'affichage |
| `php artisan about --only=environment,cache,drivers` | local, debug actif, configuration et routes non mises en cache, vues en cache, pilotes database/sync/log |
| `php artisan route:list --path=storage` | 2 routes `storage.local` (GET) et `storage.local.upload` (PUT) |
| `php artisan route:list --except-vendor` | 88 routes |
| `php artisan tinker --execute` (SELECT `VERSION()`, `sql_mode`, `time_zone`, `NOW()`, jeu de caractères, isolation, `innodb_lock_wait_timeout` ; `ATTR_EMULATE_PREPARES`) | MySQL 8.4.13, mode strict, `SYSTEM`/`GMT`, heure identique à PHP, `utf8mb4_unicode_ci`, `REPEATABLE-READ`, 50 s, `false` |
| `php artisan tinker --execute` (`var_export` puis `eval` de la configuration) | « config exportable: 19 groupes » |
| `php -r 'ini_get(...)'` | `zend.exception_ignore_args=0`, `display_errors=1` (dev), `date.timezone=Africa/Abidjan`, `upload_max_filesize=4M`, `post_max_size=8M`, `opcache.enable_cli=0` |
| `grep -rn clear_env /usr/local/etc/php-fpm.d/` (dans le conteneur) | `docker.conf:12: clear_env = no` |
| `grep -n` sur `ApplicationBuilder.php`, `Application.php`, `LoadEnvironmentVariables.php`, `TrustProxies.php`, `StartSession.php` du cadriciel | ordre d'exécution du rappel `withMiddleware` et API `TrustProxies::at()` confirmés |
| `grep -rn "env(" config app bootstrap routes database` (poste) | 207 appels sur 201 lignes, recensés dans le tableau des variables |
| `grep` sur `.env` et `.env.production` (noms et indicateurs non secrets seulement) | écarts décrits dans la section des variables |

Non exécuté, volontairement : `php artisan test`, `migrate`, `db:seed`, `config:cache` (écrirait `bootstrap/cache/config.php` et fausserait les tests en cours), toute requête HTTP sur l'application (créerait une ligne de session en base).

## Suivi

### T-908a, 19/11/2026 (agent 4)

Corrections faites, sans commit. Fichiers modifiés : `bootstrap/app.php`, `app/Http/Middleware/SecurityHeaders.php`, `app/Providers/AppServiceProvider.php`, `config/stockflow.php`, `config/session.php`, `config/filesystems.php`, `config/logging.php`, `composer.json`, `composer.lock`, `.env.example`. Fichiers créés : `app/Http/Middleware/TrustProxies.php`, et dans `tests/Feature/Security/` : `TrustedProxiesTest.php`, `SecurityHeadersTest.php`, `SessionCookieTest.php`, `DestructiveCommandsTest.php`.

| Constat | Correction | État |
| :-- | :-- | :-- |
| M-1 | `TrustProxies` de l'application (sous-classe du middleware du cadriciel) remplace celui de la pile globale (`$middleware->replace(...)`, `bootstrap/app.php`). Il lit `config('stockflow.trusted_proxies')` (`env('TRUSTED_PROXIES')`) dans `proxies()`, au moment de la requête, donc après le chargement de `.env` ou du cache de configuration. Vide : aucun proxy ; `*` : tous ; sinon liste séparée par des virgules. En-têtes X-Forwarded-For, Host, Port, Proto | Corrigé, couvert par `TrustedProxiesTest` (5 tests) |
| M-2 (configuration) | `config/session.php` : `secure` vaut par défaut `APP_ENV === production`. `.env.example` garde `SESSION_SECURE_COOKIE=false` avec un commentaire qui demande `true` en production. Le contrôle de `deploy.sh` reste au DevOps | Corrigé pour la partie backend |
| M-3 | `composer.json` : `php: ^8.4`, `config.platform.php: 8.4.1` (version minimale exigée par Symfony 8.1 dans le verrou). `composer update --lock` : seuls `content-hash`, la contrainte `php` et `platform-overrides` changent dans `composer.lock` | Corrigé |
| m-1 (partiel) | `config/session.php` : `encrypt` par défaut à `true` | Corrigé. Le `.env` local du poste garde `SESSION_ENCRYPT=false` (fichier hors de mon périmètre) |
| m-2 (partiel) | `.env.example` : `LOG_DAILY_DAYS=30` et `APP_PREVIOUS_KEYS=` documentés ; défaut de `config/logging.php` passé de 14 à 30 jours. `VITE_APP_NAME` laissé en place (non demandé) | Corrigé |
| m-3 | `SecurityHeaders` lit `config('stockflow.vite_port')` | Corrigé |
| m-5 | `config/filesystems.php` : disque `local` en `serve => false`. Aucun `temporaryUrl` ni `storage.local` dans `app/`, `resources/`, `routes/` ; les images passent par `ProductController::image`. `route:list --path=storage` ne renvoie plus rien | Corrigé |
| SEC-16 | `config/session.php` : `expire_on_close` par défaut à `true`, `SESSION_LIFETIME=120` inchangé. `SESSION_EXPIRE_ON_CLOSE=true` documenté dans `.env.example` | Corrigé, couvert par `SessionCookieTest` (cookie sans `Expires` ni `Max-Age` après connexion) |
| SEC-21 | `SecurityHeaders` passe du groupe `web` à la tête de la pile globale (`$middleware->prepend(...)`) : il enveloppe aussi la maintenance, `/up`, les 404 et les réponses JSON. Le nonce CSP reste celui de `Vite::cspNonce()`, le même que dans les vues | Corrigé, couvert par `SecurityHeadersTest` (4 tests, dont la correspondance du nonce sur `/login` et sur une 404) |
| Garde-fou de production | `AppServiceProvider::boot()` : `DB::prohibitDestructiveCommands($this->app->isProduction())`. Couvre `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` et `db:wipe` (vérifié dans `Illuminate\Support\Facades\DB::prohibitDestructiveCommands`). Le contrôle passe avant `confirmToProceed()`, donc même avec `--force` | Corrigé, couvert par `DestructiveCommandsTest` (6 tests) |

Sécurité de `DestructiveCommandsTest` : le test échoue avant de lancer quoi que ce soit si l'interdiction n'est pas active (lecture de `prohibitedFromRunning` par réflexion), et chaque commande vise une connexion inexistante. Vérifié par mutation : avec l'appel retiré du fournisseur, les 5 cas échouent sur « Garde-fou inactif ... la commande n'est pas lancée », aucune commande n'est lancée et la base reste intacte. L'appel a ensuite été remis.

Conséquence à connaître : `migrate:rollback` est lui aussi refusé en production. Le retour arrière documenté (`deployment.md`, section 8) passe par `scripts/restore.sh`, pas par un rollback de migrations : il n'est pas touché.

Limite de SEC-21 : une erreur fatale levée avant la pile de middlewares (pendant l'amorçage du cadriciel) ne passe pas par `SecurityHeaders`. Les en-têtes de base (`X-Content-Type-Options`, `X-Frame-Options`) pourraient aussi être posés par Nginx (`docker/nginx/default.conf`, périmètre DevOps) pour couvrir ce cas.

Vérification de M-1 avec les deux modes de configuration : avec un `.env` classique, `config('stockflow.trusted_proxies')` est rempli par l'amorceur `LoadEnvironmentVariables` avant le traitement de la requête ; avec `config:cache`, la valeur vient de `bootstrap/cache/config.php`. Dans les deux cas, le middleware la lit au moment de la requête. Le passage par le cache a été contrôlé en exécutant `config:cache` puis en lisant les valeurs. Le chemin `.env` n'a pas été testé de bout en bout avec `TRUSTED_PROXIES` posé dans le `.env` du poste, parce que je n'ai pas le droit de modifier ce fichier.

Commandes exécutées (conteneur `app`) :

| Commande | Résultat |
| :-- | :-- |
| `composer update --lock --no-interaction` | aucun paquet changé, aucun avis de sécurité |
| `composer validate --strict` | `./composer.json is valid`, code 0 |
| `composer install --dry-run` | « Nothing to install, update or remove » |
| `composer check-platform-reqs` | tout en `success` |
| `vendor/bin/pint --test` sur les 22 fichiers touchés ou créés | PASS |
| `php artisan config:cache` puis lecture par tinker | cache écrit ; `trusted_proxies` null, `vite_port` 5173, `session.secure` false (APP_ENV local), `expire_on_close` true, `local.serve` false, `max_files` 30 (`session.encrypt` false à cause du `.env` local) |
| `php artisan route:list --path=storage` | aucune route |
| `php artisan config:clear` | cache supprimé, `bootstrap/cache` ne contient plus que `packages.php` et `services.php` |
| `php artisan test tests/Feature/Security/DestructiveCommandsTest.php`, avec et sans le garde-fou | 6 passés ; sans le garde-fou, 5 échecs sûrs, puis code rétabli |
| `php artisan test tests/Feature/Auth tests/Feature/Security tests/Feature/Dashboard` | 81 tests passés (313 assertions), 148 s |
