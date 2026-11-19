# StockFlow

Gestion de stock pour une PME de distribution de matériel informatique et bureautique à Abidjan : un entrepôt central, un point de vente, environ 800 références, trois profils d'utilisateurs.

StockFlow suit le stock par produit et par emplacement, enregistre ventes, réceptions fournisseurs, transferts et inventaires, refuse toute sortie qui rendrait un stock négatif, et garde la trace de chaque variation : qui, quand, combien, stock avant et après, motif.

## Ce que fait l'application

| Module | Contenu |
| :-- | :-- |
| Accès | Connexion par e-mail, trois profils (administrateur, gestionnaire de stock, vendeur), droits vérifiés côté serveur sur chaque route |
| Catalogue | Produits avec référence et code-barres EAN-13 générés, catégories, fournisseurs, images, recherche instantanée |
| Stock | État par emplacement, historique des mouvements, sorties internes et ajustements avec motif obligatoire |
| Achats | Commandes fournisseurs, réceptions totales ou partielles, réception sans commande, solde d'une commande |
| Transferts | Entrepôt vers boutique et retour, en une étape, annulables |
| Ventes | Point de vente avec disponibilité en direct, bon de sortie imprimable, annulation motivée |
| Inventaires | Comptage sur tablette, écarts justifiés, validation par l'administrateur, régularisation automatique |
| Pilotage | Tableau de bord par période avec comparaison et graphiques, alertes de réapprovisionnement, exports CSV et PDF, journal d'audit |

## Versions

| Composant | Version | Source |
| :-- | :-- | :-- |
| PHP | 8.4 (contrainte Composer `^8.4`) | `composer.json`, `docker/php/Dockerfile` |
| Laravel | 13 | `composer.json` (`laravel/framework ^13.17`), D-001 |
| MySQL | 8.4 LTS, InnoDB | `compose.yaml`, D-002 |
| Node.js | 22 (compilation des CSS et JavaScript seulement) | `docker/php/Dockerfile` |
| Bootstrap | 5 | `package.json` |
| Chart.js | 4 (tableau de bord) | `package.json`, D-021 |
| Docker Compose | v2 ou plus récent (commande `docker compose`) | `scripts/install.sh`, `scripts/install.ps1` |

Versions relevées dans les conteneurs et détail des images : `docs/07-deployment/README.md`.

## Prérequis

- Docker Desktop (Windows, macOS) ou Docker Engine (Linux), avec Docker Compose v2.
- Git.
- Sous Windows : PowerShell 7 (`pwsh`) pour `scripts/install.ps1`, ou Git Bash pour `scripts/install.sh`. Le PowerShell 5.1 livré avec Windows ne suffit pas.
- 4 Go de mémoire libre, environ 5 Go de disque.
- Ports libres : 8080 (application), 3307 (MySQL), 5173 (Vite). Ils se changent dans `.env` par `APP_PORT`, `FORWARD_DB_PORT` et `VITE_PORT`.

PHP, Composer, Node et MySQL tournent dans les conteneurs : rien d'autre n'est à installer sur le poste.

### Ne pas utiliser le PHP du poste

N'utilisez pas le PHP installé sur le poste (XAMPP ou autre). Le projet exige PHP 8.4, et ses dépendances (`vendor/`) vivent dans un volume Docker, pas dans le dossier du projet. Lancé avec le PHP du poste, `php artisan` s'arrête avec un message qui indique la bonne commande.

Sous Windows, le raccourci `artisan.cmd`, à la racine du projet, lance artisan dans le conteneur. Depuis le dossier du projet, dans l'invite de commandes (cmd) :

```bat
artisan.cmd migrate:status
```

Dans PowerShell, qui ne lance pas un script du dossier courant sans chemin, préfixez par `.\` :

```powershell
.\artisan.cmd migrate:status
```

Il équivaut à `docker compose exec app php artisan migrate:status`.

## Installation en développement

```bash
git clone https://github.com/ivan9802/StockfLow.git stockflow
cd stockflow
```

Puis, depuis le dossier du clone :

| Système | Données de référence seules | Avec le jeu de démonstration |
| :-- | :-- | :-- |
| Windows (PowerShell 7) | `pwsh -File scripts/install.ps1` | `pwsh -File scripts/install.ps1 -Demo` |
| Linux, macOS, Git Bash | `bash scripts/install.sh` | `bash scripts/install.sh --demo` |

Le script vérifie Docker, crée `.env` à partir de `.env.example` s'il n'existe pas, construit et démarre les conteneurs, attend MySQL, installe les dépendances PHP et JavaScript, génère `APP_KEY` si elle est vide, compile les CSS et JavaScript, applique les migrations et charge les données de référence (les deux emplacements et le premier administrateur). Il finit par un contrôle de `http://localhost:8080/up` (port `APP_PORT`) et affiche l'adresse et les identifiants.

Il se relance sans casse : un `.env` existant et une `APP_KEY` déjà présente sont conservés. Il refuse de s'exécuter si `.env` contient `APP_ENV=production`.

Options :

| PowerShell | Bash | Effet |
| :-- | :-- | :-- |
| `-Demo` | `--demo` | Remplace la base de développement par le jeu de démonstration (quelques minutes, davantage sur un poste chargé), puis lance `stock:verify` |
| `-Yes` | `--yes` | Accepte d'avance la confirmation demandée par la démonstration quand la base contient déjà des comptes |
| `-Check` | `--check` | Vérifie seulement les prérequis |

La première installation prend quelques minutes, davantage sur un poste chargé : construction de l'image, dépendances, compilation des assets. Mesure du QA depuis un clone neuf, démonstration comprise : environ 8 minutes avec `install.sh`, jusqu'à 16 minutes avec `install.ps1` sur un poste chargé. Sous Windows, les fichiers du projet sont lus à travers le montage Docker, ce qui ralentit l'ensemble.

Les étapes du script, commande par commande, ses options et son dépannage sont décrits dans `docs/07-deployment/local-install.md`. La même installation sans script, à la main, figure dans `docs/05-development/developer-guide.md`, section 1.

### Premier administrateur

Sans démonstration, le seeder crée un administrateur `admin@stockflow.example` (variable `ADMIN_EMAIL`) s'il n'en existe aucun. Sans `ADMIN_PASSWORD`, il tire un mot de passe au hasard et l'affiche une seule fois, sur la ligne « Compte administrateur créé ». Notez-le : il doit être changé à la première connexion.

Pour fixer ce mot de passe à l'avance, renseignez `ADMIN_PASSWORD` dans `.env` avant de lancer le script d'installation. Préfixer une commande sur le poste (`ADMIN_PASSWORD=... php artisan ...` ou `ADMIN_PASSWORD=... docker compose exec ...`) ne transmet pas la variable au conteneur. Sur une base déjà migrée, sans administrateur, passez-la explicitement :

```bash
docker compose exec -e ADMIN_PASSWORD=mot-de-passe-provisoire app php artisan db:seed --class=ReferenceDataSeeder
```

Un mot de passe fourni par `ADMIN_PASSWORD` n'est pas réaffiché par le seeder ; videz la variable dans `.env` après la première connexion. Le seeder ne recrée pas un administrateur existant et ne change pas son mot de passe.

## Démarrage des jours suivants

| Système | Commande |
| :-- | :-- |
| Windows | `pwsh -File scripts/start.ps1` |
| Linux, macOS, Git Bash | `bash scripts/start.sh` |

Le script démarre les conteneurs, attend que l'application réponde, puis ouvre http://localhost:8080 dans le navigateur (option `-NoOpen` ou `--no-open` pour ne pas l'ouvrir). Après un redémarrage du poste, MySQL peut demander plusieurs minutes.

Les conteneurs de développement ne redémarrent pas seuls avec le poste : sans ce script (ou `docker compose up -d`), le site ne répond pas.

Arrêt en fin de journée :

```bash
docker compose stop
```

Les données restent dans les volumes. N'utilisez pas `docker compose down -v` : l'option `-v` supprime la base.

## Jeu de démonstration

Par le script d'installation (`-Demo` ou `--demo`), ou directement :

```bash
docker compose exec app php artisan migrate:fresh --seed --seeder=DemoSeeder
```

La commande efface toute la base de développement. Elle simule trois mois d'activité : 60 produits actifs sur 7 catégories, 6 fournisseurs, plus de 1 300 mouvements, des commandes en cours, un inventaire validé et un autre en attente de validation, des produits en stock normal, sous le seuil et en rupture. Toutes les données passent par les services métier, comme en exploitation. Détail des volumes : `docs/04-database/initialisation.md`.

Comptes de démonstration. Le mot de passe commun `demo-stockflow-2026` est public et réservé à la démonstration :

| Profil | Adresse |
| :-- | :-- |
| Administrateur | admin@stockflow.test |
| Gestionnaire de stock | awa.kone@stockflow.test, yao.kouassi@stockflow.test |
| Vendeur (point de vente) | aminata.diallo@stockflow.test, serge.bamba@stockflow.test |
| Ancien vendeur, compte désactivé | ibrahim.toure@stockflow.test |

Ces comptes ne doivent jamais exister en production. Le seeder refuse de s'exécuter quand `APP_ENV=production`.

## Commandes utiles

Les commandes s'exécutent dans le conteneur `app`. Préfixe : `docker compose exec app` (ajoutez `-T` sous Git Bash ou dans un script, et exportez `MSYS_NO_PATHCONV=1` sous Git Bash).

| Commande | Effet |
| :-- | :-- |
| `php artisan test --testsuite=Unit,Feature` | Tests unitaires et fonctionnels, sur la base MySQL de test |
| `php artisan test --testsuite=Concurrency` | Ventes simultanées par processus réels (quelques minutes) |
| `php artisan stock:verify` | Rejoue l'historique et vérifie qu'il redonne exactement chaque niveau de stock |
| `vendor/bin/pint` | Mise en forme du code PHP (`--test` pour vérifier sans modifier) |
| `npm run build` | Compilation des CSS et JavaScript (une trentaine de secondes, davantage sur un poste chargé) |
| `npm run dev` | Serveur Vite avec rechargement à chaud, sur le port 5173 |

La base de test n'est pas démarrée avec le reste : elle appartient au profil `test` de `compose.yaml`. Démarrez-la depuis le poste, sans préfixe, avant les tests, et arrêtez-la ensuite, car elle ralentit la machine :

```bash
docker compose --profile test up -d db-test
docker compose exec app php artisan test --testsuite=Unit,Feature
docker compose stop db-test
```

Pour les tests de concurrence sur 50 tours, la variable se passe par `-e` :

```bash
docker compose exec -e CONCURRENCY_ROUNDS=50 app php artisan test --testsuite=Concurrency
```

Ne lancez jamais deux suites de tests en même temps : elles partagent la base `db-test`.

Le guide du développeur (`docs/05-development/developer-guide.md`) détaille l'environnement, les variables, les tests, les conventions et le dépannage.

## Production

StockFlow se déploie avec Docker Compose (`compose.prod.yaml`, `scripts/deploy.sh`) ou sur un hébergement Laravel classique.

En production Docker, la configuration tient en deux fichiers à la racine du dépôt, jamais versionnés :

| Fichier | Contenu | Lu par |
| :-- | :-- | :-- |
| `.env.production` | Configuration de l'application, copiée de `.env.example` | Docker Compose et le conteneur `app` (PHP) |
| `.env.secrets` | `DB_ROOT_PASSWORD` et `ADMIN_PASSWORD`, modèle `docker/env.secrets.example` | Docker Compose seulement, jamais PHP |

Toutes les commandes de production passent par `scripts/prod-compose.sh`, qui charge les deux fichiers :

```bash
scripts/deploy.sh
scripts/prod-compose.sh ps
scripts/prod-compose.sh exec app php artisan stock:verify
scripts/prod-compose.sh logs --since 1h web app
```

Pour répéter la chaîne de production sur un poste, en HTTP, `scripts/deploy.sh --local-rehearsal` lève seulement les contrôles `APP_URL` en `https://` et `SESSION_SECURE_COOKIE=true`. Jamais sur un serveur réel.

Les procédures sont dans `docs/07-deployment/` :

| Document | Contenu |
| :-- | :-- |
| `docs/07-deployment/deployment.md` | Production avec Docker : `.env.production`, proxy inverse HTTPS, premier déploiement, mise à jour, retour arrière, journaux |
| `docs/07-deployment/deployment-without-docker.md` | Production sans Docker (PHP-FPM, Nginx ou Apache, MySQL 8.4) |
| `docs/07-deployment/backup-restore.md` | Sauvegarde, planification, restauration |
| `docs/07-deployment/continuous-integration.md` | Workflow GitHub Actions |

Garde-fous en production, actifs quand `APP_ENV=production` :

- le jeu de démonstration refuse de se charger ;
- `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` et `db:wipe` sont refusés par l'application, même avec `--force` ;
- `scripts/deploy.sh` s'arrête si `.env.production` ou `.env.secrets` manque ou n'est pas ignoré par Git, si `APP_ENV` ne vaut pas `production`, `APP_DEBUG` pas `false`, ou si `APP_KEY` manque, si `DB_ROOT_PASSWORD` ou `ADMIN_PASSWORD` figure dans `.env.production`, si un mot de passe MySQL est vide ou vaut une valeur d'exemple, et, hors `--local-rehearsal`, si `APP_URL` n'est pas en `https://` ou si `SESSION_SECURE_COOKIE` ne vaut pas `true`.

Ces garde-fous dépendent de `APP_ENV` : un `.env.production` mal rempli les désactive.

## Architecture en bref

Monolithe Laravel 13 (PHP 8.4), rendu serveur Blade, Bootstrap 5, JavaScript natif et Fetch, Chart.js 4 pour les graphiques du tableau de bord, MySQL 8.4.

Toute variation de stock passe par `App\Services\StockService`, dans une transaction, avec verrouillage des lignes concernées (`lockForUpdate`) dans un ordre constant. Chaque variation écrit une ligne dans `stock_movements`, table en ajout seul. Une contrainte `CHECK` empêche en dernier recours tout stock négatif dans la base.

Documentation détaillée :

| Document | Contenu |
| :-- | :-- |
| `docs/01-analysis/requirements.md` | Exigences, user stories, critères d'acceptation, matrice des droits |
| `docs/02-architecture/system-architecture.md` | Couches, services, diagrammes de séquence, sécurité, risques |
| `docs/02-architecture/database-design.md` | Modèle conceptuel et logique, dictionnaire de données, index |
| `docs/02-architecture/architecture-decisions.md` | Décisions d'architecture et leurs raisons |
| `docs/03-design/` | Charte d'interface et maquettes |
| `docs/04-database/initialisation.md` | Construction, réinitialisation et vérification de la base |
| `docs/05-development/developer-guide.md` | Guide du développeur |
| `docs/06-testing/` | Plan de tests, audit de sécurité |
| `docs/07-deployment/` | Installation, déploiement, sauvegardes, intégration continue |
| `docs/08-user-guides/` | Guides de l'utilisateur et de l'administrateur |
| `docs/10-ui-ux-dashboard/` | Design system, tableau de bord, animations |
| `DECISIONS.md` | Registre court des décisions |
| `TASKS.md` | Backlog et avancement |
| `CHANGELOG.md` | Journal des versions |

## Environnements

| Environnement | Base | Fichier |
| :-- | :-- | :-- |
| Développement | MySQL 8.4 du service `db`, base `stockflow` (port 3307 sur le poste) | `compose.yaml`, `.env` |
| Tests | MySQL 8.4 du service `db-test` (profil `test`), base `stockflow_test` en mémoire vive | `phpunit.xml` |
| Intégration continue | MySQL 8.4 du runner GitHub Actions | `.github/workflows/ci.yml` |
| Production | Voir `docs/07-deployment/` | `compose.prod.yaml`, `.env.production`, `.env.secrets` |

## Licence

Code propriétaire, réalisé pour un usage interne et académique.
