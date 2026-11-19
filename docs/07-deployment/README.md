# Installation et déploiement de StockFlow

Ce dossier décrit comment installer StockFlow sur un poste, le mettre en production, le surveiller, le sauvegarder et le tester automatiquement.

| Document | Contenu |
| :-- | :-- |
| `local-install.md` | Installation sur un poste de développement ou de démonstration par script (Windows, Linux, macOS), démarrage quotidien |
| `deployment.md` | Production avec Docker Compose : premier déploiement, mise à jour, retour arrière, journaux, tâches planifiées |
| `deployment-without-docker.md` | Production sur un hébergement Laravel classique (PHP-FPM, Nginx ou Apache, MySQL 8.4) |
| `backup-restore.md` | Sauvegarde, planification (cron, Planificateur de tâches Windows), conservation, restauration |
| `continuous-integration.md` | Workflow GitHub Actions : ce qu'il vérifie, comment il pointe les tests vers MySQL |

## Versions de référence

Versions relevées le 19/11/2026 dans les conteneurs du projet. Les images Docker suivent les branches indiquées (`php:8.4-fpm-bookworm`, `mysql:8.4`, `nginx:1.28-alpine`, `node:22-bookworm-slim`, `composer:2`) : un nouveau build peut monter d'une version corrective.

| Composant | Version relevée | Branche exigée | Source |
| :-- | :-- | :-- | :-- |
| PHP (FPM et CLI) | 8.4.27 | 8.4 (`composer.json` exige `^8.4`) | D-001, `docker/php/Dockerfile` |
| Laravel | 13.42.0 | 13 | D-001, `composer.lock` |
| MySQL | 8.4.13 | 8.4 LTS, InnoDB | D-002 |
| Nginx | 1.28.3 | 1.28 | `compose.yaml`, `docker/php/Dockerfile` |
| Composer | 2.11.2 | 2 | image `composer:2` |
| Node.js | 22.23.5 | 22 LTS (compilation des assets seulement) | image `node:22-bookworm-slim` |
| npm | 10.9.9 | 10 | fourni avec Node 22 |
| Vite | 8.3.8 | 8 | `package-lock.json` |
| Docker Engine (poste de référence) | 29.8.2 | 24 ou plus récent | |
| Docker Compose (poste de référence) | 5.5.1 | v2 ou plus récent (commande `docker compose`) | |
| PowerShell (scripts Windows) | 7.6.9 | 7.0 ou plus récent | `#Requires -Version 7.0` |

Extensions PHP utilisées : `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `opcache`, et `pcntl` pour les tests de concurrence.

## Environnements

| Environnement | Où | Base de données | Fichier de configuration | Particularités |
| :-- | :-- | :-- | :-- | :-- |
| Développement | Poste du développeur, `compose.yaml` | Service `db`, MySQL 8.4, volume `dbdata`, port 3307 sur le poste | `.env` copié de `.env.example` | `APP_ENV=local`, `APP_DEBUG=true`, code monté depuis le poste, PHP-FPM en root (dossier monté depuis Windows), réglages MySQL allégés |
| Démonstration | Même poste, même `compose.yaml` | Service `db`, rechargé par `DemoSeeder` | `.env` | Comptes au mot de passe public : jamais en production |
| Test automatisé local | Conteneur `app` | Service `db-test` (profil `test`), MySQL 8.4 en mémoire vive | `phpunit.xml` | Démarrer avec `docker compose --profile test up -d db-test`, arrêter ensuite |
| Intégration continue | GitHub Actions, `.github/workflows/ci.yml` | Service MySQL 8.4 du runner | Variables `DB_*` du job | Voir `continuous-integration.md` |
| Production Docker | Serveur Linux, `compose.prod.yaml` | Service `db`, aucun port publié | `.env.production` | Images autonomes, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS par un proxy inverse |
| Production sans Docker | Hébergement Laravel classique | MySQL 8.4 fourni par l'hébergeur ou installé | `.env` du serveur | Voir `deployment-without-docker.md` |

## Ce qui a été exécuté et ce qui est seulement documenté

État au 19/11/2026 (tâche T-904). « Exécuté » signifie lancé sur le poste de référence (Windows 11, Docker Desktop 29.8.2), avec le résultat indiqué.

| Élément | État |
| :-- | :-- |
| `scripts/install.sh --check` et `scripts/install.ps1 -Check` | Exécutés : prérequis détectés ; cas d'échec vérifiés (Docker absent du `PATH`, démon injoignable) : message clair et code de sortie 1 |
| Analyse syntaxique des scripts (`bash -n`, analyseur PowerShell) | Exécutée : aucune erreur |
| `shellcheck` | Non exécuté : absent du poste, et son lancement par une image Docker a été refusé dans la session. À lancer par le QA ou en CI |
| `scripts/install.sh` complet, depuis un clone neuf (projet Compose `stockflow-devops`, ports 18080, 13307, 15173) | Exécuté : 11 étapes, `/up` répond 200, mot de passe administrateur affiché, code de sortie 0 |
| `scripts/install.sh` relancé sur la même installation | Exécuté : `.env` et `APP_KEY` conservés, « Nothing to migrate », compte existant signalé, code de sortie 0 |
| Garde-fou de `--demo` / `-Demo` sans terminal, base non vide | Exécuté pour les deux scripts : refus avec message, code de sortie 1, base intacte |
| `scripts/install.ps1` sans `-Check` | Exécuté sur une installation existante (étapes 1 à 8 puis garde-fou de `-Demo`) ; jamais lancé sur un clone vierge |
| Chargement de la démonstration par script (`--demo --yes`) | Non exécuté (plus de 10 minutes) : à faire par le QA |
| `scripts/start.sh --no-open` et `scripts/start.ps1 -NoOpen` | Exécutés : conteneurs « healthy », application qui répond, code de sortie 0 |
| `compose.prod.yaml` avec `scripts/deploy.sh` (projet isolé `stockflow-devops`, port 18080) | Exécuté : 7 étapes, `stock:verify` sans écart, `/up` et `/login` 200 ; `db`, `app` et `web` « healthy » ; pilote `json-file` avec `max-size=10m`, `max-file=5` sur les trois conteneurs |
| `scripts/backup.sh` puis `scripts/restore.sh` sur ce projet isolé | Exécutés : sauvegarde contrôlée, restauration terminée, `stock:verify` sans écart, `/login` 200 |
| Workflow GitHub Actions | Syntaxe YAML validée localement (bibliothèque `yaml` de Node) ; jamais exécuté sur GitHub : il le sera au premier push |
| Déploiement sans Docker | Documenté seulement |
| Planification des sauvegardes (cron, Planificateur de tâches) | Documentée seulement |

Corrections T-908b, essayées le 19/11/2026 dans le projet isolé `stockflow-devops` avec le code de travail courant :

| Élément | État |
| :-- | :-- |
| Refus de `scripts/deploy.sh` : `APP_URL` en http, `SESSION_SECURE_COOKIE=false`, `DB_ROOT_PASSWORD` dans `.env.production`, `DB_PASSWORD=secret`, `DB_ROOT_PASSWORD=root-secret`, `.env.secrets` non ignoré par Git | Exécuté : refus avec message, code 1, dans chaque cas |
| `scripts/deploy.sh --local-rehearsal` avec reconstruction complète des images | Exécuté : avertissement `!!!`, 7 étapes, `stock:verify` sans écart, `/up` et `/login` 200, trois conteneurs « healthy » |
| Conteneur `app` | Vérifié : processus sous `www-data` ; code `root:root`, non inscriptible ; `storage/` et `bootstrap/cache/` à `www-data` ; ni `git`, ni `unzip`, ni `mysql`, ni `mysqldump` ; `zend.exception_ignore_args` à 1 ; `getenv()` sans `DB_ROOT_PASSWORD` ni `ADMIN_PASSWORD` |
| En-têtes par `curl` sur `/login`, `/up`, une 404 Laravel, une 404 et une 403 de Nginx, un fichier statique | Vérifié : `X-Content-Type-Options`, `X-Frame-Options` et `Referrer-Policy` présents une seule fois partout ; CSP et `Permissions-Policy` de Laravel conservés |
| `backup.sh` et `restore.sh` par `scripts/prod-compose.sh` | Exécutés : sauvegarde contrôlée, restauration terminée, `/login` 200 |
| Commande Compose sans `.env.secrets` | Vérifié : arrêt sur « DB_ROOT_PASSWORD manquant » |

Toutes les ressources Docker du projet d'essai `stockflow-devops` (conteneurs, volumes, réseau, images) ont été supprimées après ces essais.
