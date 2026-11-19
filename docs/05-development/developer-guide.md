# Guide du développeur

Ce guide s'adresse à un développeur qui reprend StockFlow sans avoir participé à sa conception. Il va du clone à l'application qui tourne, puis décrit l'environnement, les tests, les conventions et les pannes déjà rencontrées.

Les règles du projet sont dans `CLAUDE.md`, l'organisation du travail dans `AGENTS.md`, les décisions dans `DECISIONS.md`. En cas de contradiction : `docs/01-analysis/requirements.md`, puis `DECISIONS.md`.

## 1. Démarrage rapide

```bash
git clone https://github.com/ivan9802/StockfLow.git stockflow
cd stockflow
```

| Système | Installation | Démarrage des jours suivants |
| :-- | :-- | :-- |
| Windows (PowerShell 7) | `pwsh -File scripts/install.ps1` | `pwsh -File scripts/start.ps1` |
| Linux, macOS, Git Bash | `bash scripts/install.sh` | `bash scripts/start.sh` |

Ajoutez `-Demo` (PowerShell) ou `--demo` (Bash) pour charger le jeu de démonstration. L'application répond ensuite sur http://localhost:8080. Le détail des scripts, de leurs options est dans `docs/07-deployment/local-install.md`.

### Installation à la main

Les mêmes étapes que le script, sans lui, depuis le dossier du clone (Git Bash : `export MSYS_NO_PATHCONV=1` d'abord) :

```bash
cp .env.example .env
docker compose up -d --build --wait
docker compose exec -T app composer install
docker compose exec -T app php artisan key:generate
docker compose exec -T app npm ci
docker compose exec -T app npm run build
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan db:seed
```

`key:generate` ne se lance qu'une fois, sur une `APP_KEY` vide. `db:seed` crée les deux emplacements et le premier administrateur, et affiche son mot de passe provisoire si `ADMIN_PASSWORD` est vide dans `.env`. Pour la démonstration, remplacez les deux dernières commandes par `docker compose exec -T app php artisan migrate:fresh --seed --seeder=DemoSeeder`. Contrôle : `http://localhost:8080/up` doit répondre 200.

## 2. Organisation du dépôt

| Dossier ou fichier | Rôle |
| :-- | :-- |
| `app/Console/Commands/` | Commande `stock:verify` (`VerifyStockIntegrity.php`) |
| `app/Enums/` | Rôles (`Role`), types et sens de mouvement, statuts des documents, types d'emplacement |
| `app/Events/`, `app/Listeners/`, `app/Notifications/` | Événement de variation de stock, contrôle des seuils, alerte de réapprovisionnement |
| `app/Exceptions/` | Exceptions métier : stock insuffisant, motif manquant, transition d'état interdite, enregistrement non modifiable |
| `app/Exports/` | Écriture CSV en flux |
| `app/Http/Controllers/` | Contrôleurs des écrans ; `Internal/` pour les points JSON appelés en Fetch (recherche, tableau de bord) |
| `app/Http/Middleware/` | En-têtes de sécurité (`SecurityHeaders`), proxies de confiance (`TrustProxies`) |
| `app/Http/Requests/` | Form Requests : validation et autorisation des saisies |
| `app/Models/` | Modèles Eloquent ; `Concerns/` pour les comportements partagés |
| `app/Policies/` | Policies (ventes) ; les autres droits sont des Gates |
| `app/Providers/AppServiceProvider.php` | Gates, garde-fou contre les commandes destructrices en production |
| `app/Services/` | Logique métier : `StockService` (seul point d'écriture des quantités), ventes, transferts, achats, inventaires, alertes, audit, tableau de bord, références et codes-barres |
| `app/Support/` | Formats d'affichage (`Format`), périodes du tableau de bord (`Period`) |
| `app/View/Composers/` | Données partagées par les vues |
| `bootstrap/app.php` | Middlewares globaux, route `/up` |
| `config/` | Configuration Laravel ; `config/stockflow.php` pour les réglages propres au projet |
| `database/migrations/` | Schéma, source de vérité (onze migrations) |
| `database/seeders/` | `DatabaseSeeder` (appelle `ReferenceDataSeeder`), `ReferenceDataSeeder` (emplacements, premier administrateur), `DemoSeeder` (trois mois d'activité) |
| `database/factories/` | Fabriques pour les tests |
| `docker/` | `php/Dockerfile` (étapes `dev`, `prod`, `web`), réglages PHP et PHP-FPM, configuration Nginx, modèle `env.secrets.example` de `.env.secrets` |
| `docs/` | Documentation, par phase (voir la table du `README.md`) |
| `lang/fr/` | Traductions françaises |
| `resources/views/` | Vues Blade, par module ; `components/` et `layouts/` pour les éléments communs |
| `resources/js/` | JavaScript natif : `app.js`, `modules/` (écrans), `lib/` (utilitaires, appels Fetch), `print.js` |
| `resources/scss/` | Styles Sass compilés par Vite, Bootstrap 5 personnalisé |
| `routes/` | `web.php` (authentification, mot de passe, mentions légales, tableau de bord), `modules.php` (modules métier, inclus par `web.php`), `console.php` |
| `scripts/` | Installation et démarrage locaux (`install.*`, `start.*`), déploiement (`deploy.sh`), Docker Compose de production (`prod-compose.sh`), sauvegarde, restauration |
| `tests/` | `Unit/`, `Feature/` (par module), `Concurrency/` (processus réels, avec `workers/`) |
| `.github/workflows/ci.yml` | Intégration continue |
| `artisan.cmd` | Raccourci Windows qui lance artisan dans le conteneur |
| `compose.yaml`, `compose.prod.yaml` | Environnements de développement et de production |
| `vite.config.js` | Points d'entrée des CSS et JavaScript |

Dossiers et fichiers non versionnés : `vendor/`, `node_modules/`, `public/build/`, `backups/`, `.env`, `.env.production`, `.env.secrets`.

## 3. Environnement de développement

### Conteneurs

Définis dans `compose.yaml` :

| Service | Image | Rôle | Port sur le poste |
| :-- | :-- | :-- | :-- |
| `app` | Construite depuis `docker/php/Dockerfile`, étape `dev` : PHP-FPM 8.4, Composer 2, Node 22 | Exécute l'application, artisan, Composer et npm | `127.0.0.1:5173` (Vite, `VITE_PORT`) |
| `web` | `nginx:1.28-alpine` | Serveur web | `127.0.0.1:8080` (`APP_PORT`) |
| `db` | `mysql:8.4` | Base de développement | `127.0.0.1:3307` (`FORWARD_DB_PORT`) |
| `db-test` | `mysql:8.4`, profil `test` | Base des tests, données en mémoire vive | Aucun |

Le code est monté depuis le poste dans `/var/www/html`. `vendor/` et `node_modules/` vivent dans des volumes Docker (`vendor`, `node_modules`) : les lire à travers le montage Windows rendrait chaque requête beaucoup plus lente. Conséquence : ces dossiers sont vides ou absents côté poste, et le PHP du poste ne peut pas lancer le projet.

En développement, PHP-FPM tourne en root dans le conteneur (`docker/php/fpm-dev.conf`), pour écrire dans `storage/` et `bootstrap/cache/` du dossier monté. L'image de production tourne sous `www-data`.

### Bases de données

| Base | Service | Contenu | Persistance |
| :-- | :-- | :-- | :-- |
| `stockflow` | `db` | Développement et démonstration | Volume `dbdata` |
| `stockflow_test` | `db-test` | Tests automatisés (`phpunit.xml` impose `DB_HOST=db-test` et `DB_DATABASE=stockflow_test`) | Aucune : `tmpfs`, tout disparaît à l'arrêt |

Depuis le poste, un client MySQL se connecte à `127.0.0.1:3307` avec `DB_USERNAME` et `DB_PASSWORD` de `.env`. Depuis le conteneur `db` :

```bash
docker compose exec db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" "$MYSQL_DATABASE"'
```

### Commandes du quotidien

Toutes les commandes PHP, Composer et npm s'exécutent dans le conteneur `app`.

| Besoin | Windows, invite de commandes (cmd) | Windows, PowerShell | Linux, macOS, Git Bash |
| :-- | :-- | :-- | :-- |
| Commande artisan | `artisan.cmd migrate:status` | `.\artisan.cmd migrate:status` | `docker compose exec app php artisan migrate:status` |
| Commande quelconque | `docker compose exec app <commande>` | `docker compose exec app <commande>` | `docker compose exec -T app <commande>` |

Sous Git Bash, exportez d'abord `MSYS_NO_PATHCONV=1`, sinon les chemins passés au conteneur sont convertis en chemins Windows. L'option `-T` évite l'erreur de terminal de Git Bash et sert aussi dans les scripts.

```bash
export MSYS_NO_PATHCONV=1
docker compose exec -T app php artisan stock:verify
docker compose exec -T app composer install
docker compose exec -T app npm run build
docker compose exec -T app vendor/bin/pint
```

| Commande | Effet |
| :-- | :-- |
| `docker compose up -d` | Démarre `app`, `web` et `db` (pas `db-test`) |
| `docker compose stop` | Arrête les conteneurs, données conservées |
| `docker compose ps` | État des conteneurs ; `db` doit être « healthy » |
| `docker compose logs app web db` | Journaux des conteneurs |
| `php artisan stock:verify` | Contrôle de cohérence du stock ; doit répondre « aucun écart » |
| `npm run build` | Compile les CSS et JavaScript dans `public/build` (une trentaine de secondes, davantage sur un poste chargé) |
| `npm run dev` | Serveur Vite avec rechargement à chaud sur le port 5173 ; arrêt par Ctrl+C |
| `vendor/bin/pint` | Met en forme le code PHP ; `vendor/bin/pint --test` vérifie sans modifier, comme la CI |
| `composer validate --no-check-publish` | Vérifie `composer.json` et son accord avec `composer.lock` |

Une vue qui appelle `@vite` sur une nouvelle entrée exige d'ajouter cette entrée dans `vite.config.js` (`input`), puis de relancer `npm run build`.

Les journaux de l'application sont dans `storage/logs/laravel-AAAA-MM-JJ.log` (un fichier par jour).

## 4. Variables d'environnement

`.env` est créé à partir de `.env.example` par le script d'installation, ou à la main (`cp .env.example .env`). Il n'est jamais versionné. Les valeurs locales ci-dessous sont celles de `.env.example`, publiques et réservées au poste de développement. Les valeurs de production viennent de `docs/07-deployment/deployment.md`, section 3 ; les secrets y sont générés sur le serveur et ne figurent dans aucun fichier du dépôt. En production Docker, la configuration tient en deux fichiers : `.env.production`, lu par Docker Compose et par PHP, et `.env.secrets` (modèle `docker/env.secrets.example`), lu par Docker Compose seulement, qui contient `DB_ROOT_PASSWORD` et `ADMIN_PASSWORD`. Les commandes de production passent toutes par `scripts/prod-compose.sh`, qui charge les deux.

| Variable | Rôle | Valeur locale | Valeur de production |
| :-- | :-- | :-- | :-- |
| `APP_NAME` | Nom de l'application, préfixe des cookies | `StockFlow` | `StockFlow` |
| `APP_ENV` | Environnement ; active les garde-fous de production | `local` | `production` (contrôlé par `deploy.sh`) |
| `APP_KEY` | Clé de chiffrement des sessions et cookies | Générée à l'installation | Générée une fois, jamais changée ensuite |
| `APP_PREVIOUS_KEYS` | Anciennes clés gardées pendant une rotation de `APP_KEY` | Vide | Vide en temps normal |
| `APP_DEBUG` | Pages d'erreur détaillées | `true` | `false` (contrôlé par `deploy.sh`) |
| `APP_URL` | Adresse de l'application | `http://localhost:8080` | Adresse publique en `https://` (contrôlé par `deploy.sh`) |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | Langue de l'interface | `fr` | `fr` |
| `APP_FAKER_LOCALE` | Langue des données factices (tests, fabriques) | `fr_FR` | Sans effet |
| `APP_TIMEZONE` | Fuseau horaire | `Africa/Abidjan` | `Africa/Abidjan` |
| `APP_MAINTENANCE_DRIVER` | Stockage du mode maintenance | `file` | `file` |
| `BCRYPT_ROUNDS` | Coût du hachage des mots de passe | `12` | `12` |
| `LOG_CHANNEL`, `LOG_STACK` | Canal de journalisation | `stack`, `daily` | `stack`, `daily` |
| `LOG_DEPRECATIONS_CHANNEL` | Journal des dépréciations | `null` | `null` |
| `LOG_LEVEL` | Niveau minimal journalisé | `debug` | `warning` |
| `LOG_DAILY_DAYS` | Jours de journaux conservés | `30` | `30` ou plus |
| `DB_CONNECTION` | Pilote de base | `mysql` | `mysql` |
| `DB_HOST`, `DB_PORT` | Serveur MySQL | `db`, `3306` | `db`, `3306` en Docker ; adresse de l'hébergeur sans Docker |
| `DB_DATABASE`, `DB_USERNAME` | Base et utilisateur | `stockflow`, `stockflow` | Au choix de l'exploitant |
| `DB_PASSWORD` | Mot de passe MySQL de l'application | Valeur d'exemple de `.env.example` | Mot de passe fort généré sur le serveur ; `deploy.sh` refuse les valeurs d'exemple |
| `DB_ROOT_PASSWORD` | Mot de passe root du conteneur MySQL, lu par Compose seulement | Valeur d'exemple de `.env.example` | Mot de passe fort, distinct de `DB_PASSWORD`, dans `.env.secrets` (refusé dans `.env.production`) |
| `APP_PORT` | Port local du service `web` | `8080` | `8080` (derrière le proxy inverse) |
| `FORWARD_DB_PORT` | Port MySQL publié sur le poste | `3307` | À supprimer |
| `VITE_PORT` | Port du serveur Vite, autorisé par la politique de contenu en local | `5173` | À supprimer |
| `SESSION_DRIVER` | Stockage des sessions | `database` | `database` |
| `SESSION_LIFETIME` | Durée d'inactivité, en minutes | `120` | `120` |
| `SESSION_EXPIRE_ON_CLOSE` | Session terminée à la fermeture du navigateur | `true` | `true` |
| `SESSION_ENCRYPT` | Chiffrement des sessions | `true` | `true` |
| `SESSION_PATH`, `SESSION_DOMAIN` | Portée du cookie | `/`, `null` | `/`, `null` |
| `SESSION_SECURE_COOKIE` | Cookie envoyé en HTTPS seulement | `false` | `true` (contrôlé par `deploy.sh`, sauf `--local-rehearsal`) |
| `SESSION_SAME_SITE` | Attribut SameSite du cookie | `lax` | `lax` |
| `BROADCAST_CONNECTION` | Diffusion d'événements, non utilisée | `log` | `log` |
| `FILESYSTEM_DISK` | Disque par défaut (images privées) | `local` | `local` |
| `QUEUE_CONNECTION` | File d'attente : aucune, tout est synchrone | `sync` | `sync` |
| `CACHE_STORE` | Cache | `database` | `database` |
| `MAIL_MAILER` | L'application n'envoie pas d'e-mail (D-010) | `log` | `log` |
| `VITE_APP_NAME` | Lue par aucun code (constat de `backend-config-audit.md`) | `${APP_NAME}` | Sans effet |
| `TRUSTED_PROXIES` | Proxies dont les en-têtes `X-Forwarded-*` sont crus | Vide | `*` derrière le proxy inverse TLS |
| `ADMIN_NAME`, `ADMIN_EMAIL` | Premier administrateur créé par `ReferenceDataSeeder` | `Administrateur`, `admin@stockflow.example` | Nom et adresse réels |
| `ADMIN_PASSWORD` | Mot de passe provisoire de ce compte ; vide : tiré au hasard et affiché une fois | Vide | Facultatif, dans `.env.secrets` (refusé dans `.env.production`) ; à vider après la première connexion |
| `COMPANY_NAME`, `COMPANY_ADDRESS`, `COMPANY_PHONE`, `COMPANY_EMAIL`, `COMPANY_RCCM`, `COMPANY_PUBLISHER`, `COMPANY_HOST` | Identité de l'entreprise : connexion, bons imprimés, mentions légales | Valeurs d'exemple | Informations réelles de l'entreprise |

Les variables se lisent dans `.env` ou dans l'environnement du conteneur. Une variable placée devant une commande sur le poste (`VAR=... docker compose exec ...`) n'atteint pas le conteneur : utilisez `docker compose exec -e VAR=valeur app ...` ou `.env`.

Après une modification de `.env`, la configuration n'est relue que si elle n'est pas en cache. En cas de doute :

```bash
docker compose exec app php artisan config:clear
```

## 5. Clé de l'application

Le script d'installation génère `APP_KEY` si elle est vide et ne touche jamais une clé existante. À la main, sur le poste de développement :

```bash
docker compose exec app php artisan key:generate
```

En production, la clé se génère sur le serveur, puis se recopie dans `.env.production` (`docs/07-deployment/deployment.md`, section 3). `scripts/deploy.sh` refuse de continuer sans `APP_KEY` en `base64:` :

```bash
echo "base64:$(openssl rand -base64 32)"
```

Une fois l'application en service, ne changez plus la clé : les sessions et cookies chiffrés deviendraient illisibles. Si une rotation est indispensable, placez l'ancienne clé dans `APP_PREVIOUS_KEYS` le temps que les sessions expirent.

## 6. Base de données

La construction de la base, les contraintes, les seeders, les volumes de la démonstration et les durées constatées sont décrits dans `docs/04-database/initialisation.md`. L'essentiel :

| Besoin | Commande (préfixe `docker compose exec app`) |
| :-- | :-- |
| Appliquer les migrations | `php artisan migrate` |
| État des migrations | `php artisan migrate:status` |
| Données de référence seules | `php artisan db:seed --class=ReferenceDataSeeder` |
| Base vide avec emplacements et administrateur | `php artisan migrate:fresh --seed` |
| Base de démonstration | `php artisan migrate:fresh --seed --seeder=DemoSeeder` |
| Contrôle de cohérence | `php artisan stock:verify` |

`migrate:fresh` supprime toutes les tables de la base de développement, comptes créés à la main compris. Le chargement de la démonstration dure une à quelques minutes (le seeder seul : 56 s lors de la vérification du QA), davantage sur un poste chargé ; lancez-le avec un délai d'attente large, et arrêtez `db-test` avant, car elle ralentit l'ensemble.

Le schéma ne change que par une migration. `docs/04-database/schema.sql` en est un export destiné à la lecture.

## 7. Tests

Tous les tests tournent sur MySQL 8.4, jamais sur SQLite : les verrous, les contraintes `CHECK` et les fonctions de date du tableau de bord n'existent pas sous SQLite (D-014).

### Suites

Déclarées dans `phpunit.xml` :

| Suite | Dossier | Contenu |
| :-- | :-- | :-- |
| `Unit` | `tests/Unit` | Calculs isolés (périodes du tableau de bord) |
| `Feature` | `tests/Feature` | Règles métier, écrans, droits, exports, sécurité, tableau de bord, par module |
| `Concurrency` | `tests/Concurrency` | Ventes simultanées par processus PHP séparés |

Le contenu détaillé et le dernier bilan sont dans `docs/06-testing/test-plan.md`.

### Lancer les tests

La base de test n'est démarrée que par le profil `test` :

```bash
docker compose --profile test up -d db-test
docker compose --profile test ps
```

Attendez que `db-test` soit « healthy » : quelques secondes d'ordinaire, plusieurs minutes après un redémarrage du poste. Puis :

```bash
docker compose exec app php artisan test --testsuite=Unit,Feature
docker compose exec app php artisan test --testsuite=Concurrency
docker compose exec -e CONCURRENCY_ROUNDS=50 app php artisan test --testsuite=Concurrency
```

Pour un seul fichier :

```bash
docker compose exec app php artisan test tests/Feature/Stock/StockServiceTest.php
```

Arrêtez la base de test après usage, elle ralentit fortement la machine :

```bash
docker compose stop db-test
```

`phpunit.xml` ne définit pas de suite par défaut : `php artisan test` sans option enchaîne les trois suites, y compris celle de concurrence. Précisez `--testsuite` pour n'en lancer qu'une partie.

### Durées constatées

Sur le poste Windows de référence, code lu à travers le montage Docker (`docs/06-testing/test-plan.md`) : 20 min 28 s pour Unit et Feature le 14/11/2026 (124 tests à cette date), 2 min 38 s pour la concurrence à 5 tours. Sous Linux, ces durées sont nettement plus courtes.

### Pièges connus

- Ne lancez jamais deux suites de tests en même temps. Elles partagent `db-test`, et le `migrate:fresh` de `RefreshDatabase` supprime les tables sous les processus de l'autre suite (constaté le 14/11/2026 sur les tests de concurrence).
- Les tests à processus réels utilisent `DatabaseTruncation`, pas `RefreshDatabase` : une transaction de test cacherait les données aux autres processus.
- Le nom `store()` est réservé par la classe `Factory` de Laravel : l'état « point de vente » de `WarehouseFactory` s'appelle `pointOfSale()`.
- Une réponse en flux (export CSV) n'a pas de méthode `status()` dans les tests : utilisez `getStatusCode()`.
- Un test ne s'affaiblit pas et ne se supprime pas pour passer (`AGENTS.md`, règles communes).

### Intégration continue

`.github/workflows/ci.yml` lance à chaque push et à chaque pull request : `composer validate`, Pint en mode vérification, `npm run build`, les migrations, les suites Unit et Feature, puis la suite de concurrence, sur un MySQL 8.4 du runner, et un audit des dépendances. Détail et équivalent local : `docs/07-deployment/continuous-integration.md`.

## 8. Règles métier et conventions

Les règles complètes sont dans `CLAUDE.md`. Celles du stock ne se négocient pas :

1. Le stock est tenu par produit et par emplacement dans `stock_levels`. Il n'y a pas de colonne de stock sur `products`.
2. Aucune quantité ne devient négative. Une vente, une sortie ou un transfert qui dépasse le disponible est refusé en bloc.
3. Toute variation de `stock_levels.quantity` passe par `App\Services\StockService` et produit exactement une ligne dans `stock_movements`, avec stock avant et stock après. Aucun contrôleur, modèle, seeder ou commande ne modifie une quantité autrement.
4. Chaque opération de stock s'exécute dans `DB::transaction()` avec `lockForUpdate()` sur les lignes concernées, verrouillées dans l'ordre croissant de `(product_id, warehouse_id)`.
5. L'audit d'une opération de stock s'écrit dans la même transaction. `stock_movements` et `audit_logs` sont en ajout seul : une erreur se corrige par un nouveau mouvement.
6. Un document validé ne se supprime pas. Une vente ou un transfert s'annule par des mouvements inverses ; une réception ou un inventaire validé se corrige par un ajustement justifié.
7. Les justifications sont obligatoires pour les sorties internes, les ajustements, les annulations et les écarts d'inventaire.
8. Les montants sont en francs CFA, stockés en entiers.

`php artisan stock:verify` contrôle les règles 1 à 3 sur les données réelles : lancez-le après toute modification qui touche au stock.

Conventions de code, en bref :

- Code, tables, colonnes, classes et routes en anglais ; interface, messages et documentation en français.
- Contrôleurs minces : validation dans un Form Request, autorisation par Policy ou Gate, logique dans un service.
- Autorisations vérifiées côté serveur sur chaque route, y compris les points JSON appelés en Fetch. Masquer un bouton ne protège rien.
- Eloquent ou Query Builder avec paramètres liés, jamais de SQL concaténé.
- `{{ }}` dans Blade ; `{!! !!}` seulement pour le SVG des codes-barres généré par le serveur.
- Pas de framework JavaScript, pas de jQuery, pas de Tailwind.
- Aucune dépendance Composer ou npm sans entrée préalable dans `DECISIONS.md`.

## 9. Flux de travail Git

- Une branche par phase, nommée d'après la phase (par exemple `phase-1-2/cadrage-architecture`, `phase-3/initialisation`), fusionnée dans `main` une fois la phase validée.
- Commits courts, en français, un sujet par commit.
- `vendor/bin/pint` avant chaque commit ; la CI échoue sur `vendor/bin/pint --test` si un fichier n'est pas au format.
- Aucun secret dans Git : `.env` et `.env.production` sont ignorés, `.env.example` documente chaque variable avec des valeurs d'exemple.
- Une tâche de `TASKS.md` ne passe à « Terminé » que si ses critères ont été vérifiés par une commande réellement exécutée.
- Une décision qui touche le périmètre, la stack ou une règle métier est soumise au responsable du projet, puis consignée dans `DECISIONS.md`.
- Dans l'organisation à agents du projet, les commits sont faits par l'agent principal après revue (`AGENTS.md`).

## 10. Dépannage

| Symptôme | Cause | Que faire |
| :-- | :-- | :-- |
| `php artisan` répond « StockFlow s'exécute dans Docker, pas avec le PHP de ce poste », ou une erreur sur `vendor/autoload.php` | Commande lancée avec le PHP du poste (XAMPP) : `vendor/` est dans un volume Docker et le PHP du poste n'est pas en 8.4 | `artisan.cmd <commande>` dans cmd, `.\artisan.cmd <commande>` dans PowerShell, ou `docker compose exec app php artisan <commande>` |
| Le site ne répond plus après un redémarrage du poste | Les conteneurs de développement ne redémarrent pas seuls ; Docker Desktop peut aussi être arrêté | Lancer Docker Desktop, puis `pwsh -File scripts/start.ps1` ou `bash scripts/start.sh` |
| `SQLSTATE[HY000] [2002]` ou base injoignable juste après le démarrage | MySQL met plusieurs minutes à s'initialiser après un redémarrage du poste | Attendre l'état « healthy » de `db` dans `docker compose ps` ; les scripts de démarrage attendent jusqu'à 10 minutes |
| Les tests échouent tout de suite sur la connexion à `db-test` | Base de test non démarrée (profil `test`), ou encore en cours d'initialisation | `docker compose --profile test up -d db-test`, puis attendre « healthy » dans `docker compose --profile test ps` |
| Tables supprimées pendant une suite de tests | Deux suites lancées en même temps sur `db-test` | N'en lancer qu'une à la fois |
| Premier chargement très lent, `npm run build` ou démonstration qui durent plusieurs minutes | Sous Windows, les fichiers du projet sont lus à travers le montage Docker ; la première requête compile aussi les vues | Normal. Les pages suivantes sont plus rapides. Arrêter `db-test` si elle tourne |
| Page sans mise en forme, ou erreur « Vite manifest not found » | Assets non compilés, ou nouvelle entrée `@vite` absente de `vite.config.js` | `docker compose exec app npm run build` ; ajouter l'entrée dans `vite.config.js` si besoin |
| `docker compose up` échoue sur un port | 8080, 3307 ou 5173 déjà utilisé sur le poste | Changer `APP_PORT`, `FORWARD_DB_PORT` ou `VITE_PORT` dans `.env` |
| Le mot de passe fixé par `ADMIN_PASSWORD` ne marche pas | La variable n'a pas atteint le conteneur, ou un administrateur existait déjà | La mettre dans `.env`, ou passer `-e ADMIN_PASSWORD=...` à `docker compose exec` ; le seeder ne modifie jamais un administrateur existant |
| Une modification de `.env` est sans effet | Configuration en cache | `docker compose exec app php artisan config:clear` |
| Chemins déformés sous Git Bash (`C:/Program Files/Git/...`) | Conversion automatique des chemins par MSYS | `export MSYS_NO_PATHCONV=1` avant les commandes `docker compose` |
| `stock:verify` signale des écarts | Une quantité a été modifiée hors de `StockService` | Ne pas retoucher l'historique ; sur une base de démonstration, la recharger ; sinon, corriger par un ajustement justifié dans l'application |

Pour les erreurs du script d'installation lui-même, voir `docs/07-deployment/local-install.md`, section « Si le script s'arrête ». Pour la base, `docs/04-database/initialisation.md`, section « Dépannage ».
