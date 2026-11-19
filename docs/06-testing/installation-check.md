# Vérification de l'installation depuis une copie propre

Tâche T-907, agent QA (7), exécutée le 19/11/2026 entre 10 h 29 et 11 h 05. Objectif : installer StockFlow comme le ferait un développeur qui découvre le projet, dans un environnement isolé, sans toucher à l'installation de l'utilisateur, et consigner ce qui marche, ce qui manque et ce qui est faux.

Ce contrôle n'est pas la campagne de tests (mission 2) : seule la classe `AuthenticationTest` a été lancée, comme test de fumée.

## Environnement

| Élément | Valeur relevée |
| :-- | :-- |
| Poste | Windows 11 Famille 10.0.26300 |
| Docker | Docker Desktop, moteur 29.8.2 (linux/amd64), 2 processeurs et 2,35 Gio de mémoire alloués à la machine virtuelle |
| Docker Compose | v5.5.1 |
| Shells | Git Bash (GNU bash 5.3.15), PowerShell 7.6.9 |
| Git | 2.55.0.windows.5, `core.autocrlf=true` |
| curl | 8.21.0 |
| Dans les conteneurs | PHP 8.4.27, Laravel 13.42.0, Node 22.23.5, MySQL 8.4.13 |

Pendant tout le contrôle, l'installation de l'utilisateur (projet Compose `stackflow`, ports 8080, 3307, 5173) a continué de tourner ; elle n'a été ni arrêtée, ni modifiée. Les volumes `stockflow-prod_*` présents sur le poste avant le contrôle n'ont pas été touchés.

## Méthode d'isolement

Le travail de la mission n'est pas commité : un `git clone` ne le contiendrait pas. La copie propre a donc été construite à partir de la liste exacte de ce qu'un clone contiendrait une fois ce travail commité :

```bash
cd D:/projet_encours/stackflow
git ls-files -z --cached --others --exclude-standard | tar --null -T - -cf - | tar -xf - -C D:/projet_encours/stockflow-qa
cd D:/projet_encours/stockflow-qa && git init -q
```

- Dossier cible `D:\projet_encours\stockflow-qa` : projet Compose `stockflow-qa`, distinct de `stackflow`.
- Ports : `APP_PORT=18080`, `FORWARD_DB_PORT=13307`, `VITE_PORT=15173`, libres avant le contrôle (`netstat`).
- 339 fichiers copiés en 7 s. Contrôle de la cible : `.env`, `.env.production`, `.env.secrets`, `vendor/`, `node_modules/`, `public/build/`, `public/hot`, `backups/` absents.
- Fins de ligne : `.gitattributes` impose `eol=lf` partout et `eol=crlf` pour `*.cmd`. La copie par `tar` reprend les fichiers du dossier de travail tels quels ; un vrai clone appliquerait ces règles. Seul `artisan.cmd` diffère (voir l'anomalie QA-3) ; il a été converti en CRLF dans la copie pour reproduire un clone.
- Limite : le cache de construction BuildKit du poste est partagé. Les couches de l'image `dev` déjà construites pour `stackflow` ont pu être réutilisées (étape `node:22` marquée `CACHED`), ce qui raccourcit la construction par rapport à un poste vierge. Les téléchargements Composer et npm, eux, ont bien eu lieu (volumes neufs).

## Scénario : les huit étapes

Commande unique des étapes 2 à 6, depuis la copie, sous Git Bash :

```bash
APP_PORT=18080 FORWARD_DB_PORT=13307 VITE_PORT=15173 bash scripts/install.sh --demo --yes
```

Code de sortie 0, durée totale 476 s (7 min 56 s), construction de l'image comprise.

| N° | Étape | Statut | Durée | Preuve |
| :-- | :-- | :-- | :-- | :-- |
| 1 | Récupération (copie propre) | Réussi | 7 s | 339 fichiers ; absence de `.env`, `vendor/`, `node_modules/`, `public/build/`, `.env.production`, `backups/` vérifiée |
| 2 | Installation des dépendances | Réussi | inclus dans 476 s | Image `stockflow-qa-app` construite ; `composer install` : « Package operations: 117 installs, 0 updates, 0 removals » ; `npm ci` : « added 37 packages in 25s » ; `npm run build` : « built in 33.75s », `public/build/manifest.json` produit |
| 3 | Configuration de l'environnement | Réussi | inclus | « .env créé à partir de .env.example. » ; « Application key set successfully. » (clé de 51 caractères, non recopiée ici) |
| 4 | Création de la base | Réussi | inclus | `stockflow-qa-db-1 Healthy`, « MySQL répond. » ; base `stockflow` créée par l'image MySQL |
| 5 | Migrations | Réussi | 35 s environ | `migrate:fresh` : 11 migrations « DONE » ; 27 tables comptées ensuite dans `information_schema` |
| 6 | Données de démonstration | Réussi | 56,5 s (seeder) | « Démonstration prête : 61 produits, 1344 mouvements, 295 ventes. » ; étape 10 du script : « Stock cohérent : 1344 mouvements rejoués sur 122 lignes de stock, aucun écart. » |
| 7 | Démarrage | Réussi | 11 s (conteneurs déjà lancés), 33 s après `docker compose stop` | `bash scripts/start.sh --no-open` : « StockFlow répond sur http://localhost:18080 », code 0 ; `docker compose ps` : `app`, `web` en marche, `db` « healthy », ports 18080, 13307, 15173 sur 127.0.0.1 |
| 8 | Accès | Réussi | moins de 10 s | Voir le détail ci-dessous |

### Détail des étapes 2 à 6 : étapes affichées par le script

| Étape du script | Résultat |
| :-- | :-- |
| [1] Vérification des prérequis | Docker 29.8.2, Docker Compose 5.5.1 |
| [2] Fichier de configuration .env | Créé depuis `.env.example` |
| [3] Construction et démarrage des conteneurs | `db`, `app`, `web` créés et démarrés, `db` « Healthy » |
| [4] Attente de MySQL | « MySQL répond. » |
| [5] Dépendances PHP | 117 paquets installés, découverte des paquets sans erreur |
| [6] Clé de l'application | Générée |
| [7] Dépendances JavaScript | 37 paquets |
| [8] Compilation des CSS et JavaScript | Réussie en 34 s |
| [9] Base de démonstration | 11 migrations, `DemoSeeder` en 56 s |
| [10] Contrôle du stock | Aucun écart |
| [11] Contrôle de l'application | `http://localhost:18080/up : 200` |
| Fin | Adresse et trois comptes de démonstration affichés |

La procédure manuelle du guide du développeur n'a pas été exécutée : le script n'a pas échoué, elle n'a donc pas été nécessaire.

### Détail de l'étape 8 : accès

Requêtes `curl` depuis le poste, avec un fichier de cookies :

| Requête | Résultat attendu | Résultat obtenu |
| :-- | :-- | :-- |
| `GET /up` | 200 | 200 |
| `GET /login` | 200 et jeton CSRF | 200, champ `_token` de 40 caractères |
| `POST /login` (`_token`, `email=admin@stockflow.test`, mot de passe public de démonstration du README) | Redirection | 302 vers `http://localhost:18080/` (port de la copie, pas celui d'`APP_URL`) |
| `GET /dashboard` | 200 et « Tableau de bord » | 200, « Tableau de bord » présent 3 fois |
| `GET /internal/dashboard?period=last_30_days`, en-tête `Accept: application/json` | 200, JSON | 200, `application/json`, période du 21/10/2026 au 19/11/2026, `active_products` 60, `low_count` 8, `out_count` 7 |
| Même requête sans session | Refus | 401 |
| `docker compose exec -T app php artisan stock:verify` | Aucun écart | « Stock cohérent : 1344 mouvements rejoués sur 122 lignes de stock, aucun écart. », code 0, 3 s |

## Contrôles complémentaires

| Contrôle | Commande | Statut | Preuve |
| :-- | :-- | :-- | :-- |
| Idempotence | `APP_PORT=18080 FORWARD_DB_PORT=13307 VITE_PORT=15173 bash scripts/install.sh` (sans démonstration), sur la base de démonstration | Réussi | Code 0 en 121 s. « .env existe déjà : conservé tel quel. », « APP_KEY déjà définie : conservée. », « Nothing to migrate. ». Empreinte SHA-256 de `.env` et de la ligne `APP_KEY` identiques avant et après ; comptes, mouvements et emplacements inchangés (6, 1 344, 2). Message final trompeur : voir QA-1 |
| Contrôle des prérequis, Bash | `bash scripts/install.sh --check` | Réussi | « Prérequis présents. », code 0 |
| Contrôle des prérequis, PowerShell | `pwsh -File scripts/install.ps1 -Check` | Réussi | « Docker 29.8.2, Docker Compose 5.5.1, projet dans D:\projet_encours\stockflow-qa », « Prérequis présents. », code 0, 24 s |
| Installation complète PowerShell | Voir la section suivante | Réussi | Code 0 en 936 s, 11 étapes, aucun écart de stock, connexion et tableau de bord vérifiés |
| Garde-fou de production, `migrate:fresh` | `docker compose exec -T -e APP_ENV=production app php artisan migrate:fresh --force` | Réussi | « This command is prohibited from running in this environment. », code 1. Tables, mouvements, comptes : 27, 1 344, 6 avant et après |
| Garde-fou de production, `db:wipe` | Même forme, `db:wipe --force` | Réussi | Même refus, code 1, mêmes comptes après |
| Démonstration refusée en production | `docker compose exec -T -e APP_ENV=production app php artisan db:seed --class=DemoSeeder --force` | Réussi | « Le jeu de démonstration ne se charge jamais en production. », code 1, 1 344 mouvements après |
| Test de fumée | `docker compose --profile test up -d db-test`, puis `docker compose exec -T app php artisan test --filter=AuthenticationTest` | Réussi | `db-test` « healthy » en 52 s ; « 15 passed (63 assertions) », 29,6 s ; `db-test` arrêté ensuite |
| Suite Unit présente | `vendor/bin/phpunit --list-tests --testsuite=Unit` | Constaté | 26 tests `PeriodTest` listés |
| PHP du poste | `php artisan --version` avec le PHP de XAMPP | Réussi | Message clair (PHP 8.2.12 ici, 8.4 requis, commandes à utiliser), code 1 |
| Raccourci Windows | `.\artisan.cmd --version` depuis PowerShell | Réussi avec CRLF | « Laravel Framework 13.42.0 ». Avec les fins de ligne LF du dossier de travail actuel, trois erreurs « 'em' n'est pas reconnu » précèdent le résultat : voir QA-3 |

### Installation complète en PowerShell

Seconde copie propre, construite de la même façon dans `D:\projet_encours\stockflow-qa-ps` (339 fichiers, mêmes absences vérifiées), après suppression complète de la première :

```powershell
$env:APP_PORT='18080'; $env:FORWARD_DB_PORT='13307'; $env:VITE_PORT='15173'
pwsh -File scripts/install.ps1 -Demo -Yes
```

| Étape du script | Résultat |
| :-- | :-- |
| [1] à [4] | Prérequis présents, `.env` créé, image `stockflow-qa-ps-app` construite, MySQL prêt |
| [5] Dépendances PHP | « Package operations: 117 installs » |
| [6] Clé de l'application | Générée |
| [7] Dépendances JavaScript | « added 37 packages in 1m » |
| [8] Compilation | « built in 1m 24s » |
| [9] Base de démonstration | « Démonstration prête : 61 produits, 1344 mouvements, 295 ventes. », seeder en 165 s |
| [10] Contrôle du stock | « 1344 mouvements rejoués sur 122 lignes de stock, aucun écart. » |
| [11] Contrôle de l'application | `http://localhost:18080/up : 200` |

Code de sortie 0, durée totale 936 s (15 min 36 s), soit le double de l'essai Bash sur le même poste : `npm ci`, la compilation et le seeder ont été deux à trois fois plus lents, signe de la variabilité due à la charge du poste plutôt que d'une différence entre les scripts.

Ensuite : `pwsh -File scripts/start.ps1 -NoOpen` (code 0, 28 s, « StockFlow répond sur http://localhost:18080 »), puis connexion par formulaire avec jeton CSRF en PowerShell (`Invoke-WebRequest` avec session) : `GET /dashboard` 200 avec « Tableau de bord », `GET /internal/dashboard?period=last_30_days` 200 en `application/json`.

## Écarts de documentation

Aucune étape manquante : la commande unique documentée a suffi, sans intervention, du dossier vide à l'application connectée. Les écarts relevés sont mineurs.

| N° | Fichier et ligne | Constat | Correction proposée | Agent |
| :-- | :-- | :-- | :-- | :-- |
| DOC-1 | `docs/07-deployment/local-install.md:24-35` | La liste annonce 10 étapes « dans cet ordre » ; le script en affiche 11 en mode démonstration (le contrôle du stock est une étape à part) et 11 sans démonstration (migrations et données de référence séparées). Le contenu est juste, la numérotation ne correspond pas à l'affichage | Aligner la liste sur les numéros affichés par le script, ou préciser que la numérotation diffère | DOC (10) |
| DOC-2 | `docs/07-deployment/local-install.md:79` | Pour une seconde installation, le guide conseille de mettre les ports dans `.env`, sans citer `APP_URL`, qui reste `http://localhost:8080`. Sans effet constaté sur les pages (redirections faites sur le bon port), mais les adresses produites hors requête HTTP (commandes, tâches) pointeraient vers l'installation principale | Ajouter « et `APP_URL=http://localhost:18080` » à la phrase | DOC (10) |
| DOC-3 | `README.md:78`, `README.md:82`, `README.md:148`, `docs/07-deployment/local-install.md:32-33`, `docs/05-development/developer-guide.md:133`, `scripts/install.sh:149,167`, `scripts/install.ps1:171,189`, `CLAUDE.md:53,55` | Durées annoncées très au-dessus des durées mesurées : démonstration « plus de 10 minutes », mesurée 35 s de migrations et 56 s de seeder ; `npm run build` « 2 à 3 minutes », mesuré 34 s. Les guides `developer-guide.md:226` et `initialisation.md:189` donnent déjà une fourchette. Pas une erreur bloquante : une durée maximale prudente rassure plus qu'elle ne gêne | Écrire « de 2 à plus de 10 minutes selon la charge du poste » partout, comme `developer-guide.md:226` | DOC (10) pour les documents, DevOps (9) pour les scripts, CTO pour `CLAUDE.md` |
| DOC-4 | `docs/06-testing/test-plan.md:9-12` | Indiquait qu'il n'existait pas de suite Unit, alors que `tests/Unit/PeriodTest.php` (26 tests) existe et que `phpunit.xml` déclare `defaultTestSuite="Unit,Feature"` | Corrigé dans cette tâche | QA (7), fait |

## Anomalies

| N° | Gravité | Constat | Preuve | Correction proposée | Agent |
| :-- | :-- | :-- | :-- | :-- | :-- |
| QA-1 | Mineure | Relancé sans démonstration sur une base de démonstration, le script annonce « Administrateur : admin@stockflow.example » et « Le compte existait déjà : mot de passe inchangé ». Ce compte n'existe pas : le seul administrateur est `admin@stockflow.test`. Le seeder n'a rien créé puisqu'un administrateur existe, mais le message oriente vers une adresse fausse | `scripts/install.sh:200-209`, `scripts/install.ps1:227-236` ; requête `SELECT email, role FROM users` : six comptes `@stockflow.test`, aucun `admin@stockflow.example` | Quand le seeder n'a pas créé de compte, ne pas afficher `ADMIN_EMAIL` mais « Un administrateur existait déjà : identifiants inchangés », ou lire l'adresse du premier administrateur en base | DevOps (9) |
| QA-2 | Information | Une seconde installation reste dépendante des variables d'environnement de ports pour toute commande `docker compose up` ultérieure ; oubliées, elle tenterait de prendre 8080, 3307 et 5173, déjà occupés. C'est documenté (`local-install.md:79`) | Constaté en préparant les commandes | Aucune, sinon DOC-2 | Sans objet |
| QA-3 | Mineure | Dans le dossier de travail actuel du dépôt, `artisan.cmd` a des fins de ligne LF (`git ls-files --eol` : `w/lf`, attribut `eol=crlf`). `cmd.exe` affiche alors trois fois « 'em' n'est pas reconnu en tant que commande interne », puis exécute la commande. Un clone n'est pas touché : Git écrit ce fichier en CRLF, et la copie convertie en CRLF répond proprement | `.\artisan.cmd --version` dans la copie, avant et après conversion | Réécrire le fichier en CRLF dans le dépôt de l'utilisateur avant le commit (la normalisation de Git le fera aussi à la prochaine extraction) | DevOps (9) |

Aucune anomalie bloquante.

## Nettoyage

Pour chaque copie, depuis son dossier et avec les mêmes variables de ports :

```bash
docker compose --profile test down -v --rmi local
```

puis suppression du dossier (`stockflow-qa`, puis `stockflow-qa-ps`). Contrôle final : aucun conteneur, volume, réseau ni image dont le nom contient `qa` (`docker ps -a`, `docker volume ls`, `docker network ls`, `docker images`) ; les deux dossiers n'existent plus. L'installation de l'utilisateur tourne toujours (`stackflow-db-1` « healthy », `stackflow-app-1`, `stackflow-web-1`), ses trois volumes `stackflow_*` sont intacts et `http://localhost:8080/up` répond 200. Les images partagées `mysql:8.4` et `nginx:1.28-alpine` n'ont pas été supprimées, puisqu'elles servent aussi à l'installation de l'utilisateur.

Fichiers modifiés dans le dépôt : ce rapport et `docs/06-testing/test-plan.md` (DOC-4). Aucun commit.

## Conclusion

Oui : l'installation depuis un clone propre fonctionne sans aide. La commande unique documentée, en Bash comme en PowerShell, mène d'un dossier sans `.env`, `vendor` ni `node_modules` à une application en marche, chargée du jeu de démonstration, dont le stock est cohérent, en 8 à 16 minutes sur ce poste. La connexion par formulaire, le tableau de bord et son point JSON répondent, le script se relance sans rien casser et les garde-fous de production refusent les commandes destructrices sans rien supprimer.

Les huit étapes du scénario sont réussies et chacune a été exécutée. Restent trois anomalies mineures (QA-1, QA-3, et l'information QA-2) et quatre écarts de documentation, dont un corrigé ici (DOC-4) ; aucun n'empêche l'installation.

Réserve : la copie a été faite à partir du dossier de travail, pas d'un vrai `git clone`, puisque rien n'est commité. Le contrôle devra être refait par un `git clone` réel une fois la mission commitée et poussée, en particulier pour les fins de ligne de `artisan.cmd` (QA-3).

## Vérification depuis un vrai clone (T-910)

Exécutée le 19/11/2026, après le commit local de la mission (branche `phase-3/initialisation`, commit `740c89b`, dossier de travail propre). Rien n'est poussé : le clone part du dépôt local. Mêmes règles qu'en T-907 : ports 18080, 13307 et 15173, projet Compose `stackflow` de l'utilisateur non touché.

```bash
git clone --branch phase-3/initialisation D:/projet_encours/stackflow D:/projet_encours/stockflow-clone
```

Clone en 11 s, 340 fichiers, projet Compose `stockflow-clone`. Absents du clone : `.env`, `.env.production`, `vendor/`, `node_modules/`, `public/build/`, `backups/`.

| N° | Point vérifié | Statut | Preuve |
| :-- | :-- | :-- | :-- |
| 1 | Fins de ligne, droits, BOM reçus par le clone | Réussi | `file artisan.cmd` : « with CRLF line terminators » ; `git ls-files --eol` : 338 fichiers LF, `artisan.cmd` seul en CRLF, 1 binaire ; `git ls-files -s scripts` : `100755` pour les six `.sh` (`backup`, `deploy`, `install`, `prod-compose`, `restore`, `start`), `100644` pour les deux `.ps1` ; `scripts/install.ps1` et `scripts/start.ps1` commencent par le BOM `ef bb bf` |
| 2 | `artisan.cmd --version` par cmd.exe, conteneurs démarrés | Réussi | `cmd.exe /d /c "cd /d D:\projet_encours\stockflow-clone && artisan.cmd --version"` : « Laravel Framework 13.42.0 », code 0, plus aucun message « 'em' n'est pas reconnu ». La commande n'attend pas de terminal : elle répond avec une sortie capturée et sans entrée interactive. Voir la remarque ci-dessous sur l'environnement de l'outil et l'écart DOC-5 |
| 3 | `APP_PORT=18080 FORWARD_DB_PORT=13307 VITE_PORT=15173 bash scripts/install.sh --demo --yes` | Réussi | Code 0, 986 s (16 min 26 s) : image `stockflow-clone-app` construite, 117 paquets Composer, 37 paquets npm (2 min), compilation en 1 min 30 s, `DemoSeeder` en 130 s (« 61 produits, 1344 mouvements, 295 ventes »), étape 10 « 1344 mouvements rejoués sur 122 lignes de stock, aucun écart », étape 11 `/up : 200`. Les libellés des étapes 8 et 9 affichent désormais « jusqu'à quelques minutes » et « quelques minutes » (DOC-3 pris en compte dans le script) |
| 4 | Relance sans démonstration, message final (QA-1) | Réussi | `bash scripts/install.sh` : code 0, 449 s. « .env existe déjà : conservé tel quel. », « APP_KEY déjà définie : conservée. », « Nothing to migrate. ». Empreinte de `.env` identique avant et après. Message final : « Un administrateur existe déjà : compte inchangé, connectez-vous avec ses identifiants habituels. » ; l'adresse `admin@stockflow.example` n'est plus annoncée. QA-1 est corrigée |
| 5 | Accès | Réussi | `/up` 200 ; `/login` 200, jeton CSRF de 40 caractères ; `POST /login` avec `admin@stockflow.test` et le mot de passe public de démonstration : 302 vers `http://localhost:18080/` ; `/dashboard` 200, « Tableau de bord » présent 3 fois ; `/internal/dashboard?period=last_30_days` 200 en `application/json` (`active_products` 60, `low_count` 8, `out_count` 7) ; `php artisan stock:verify` : aucun écart |

Après l'installation et la relance, `git status` dans le clone ne montre aucun fichier modifié ni ajouté : les fichiers produits (`.env`, `public/build/`, journaux) sont bien ignorés.

Remarque sur le point 2. Dans l'environnement de l'outil qui a mené le contrôle, la variable `NoDefaultCurrentDirectoryInExePath=1` est définie : cmd.exe y refuse de lancer un programme du dossier courant sans chemin, et `artisan.cmd` seul répond « n'est pas reconnu ». Avec la variable vidée (`set NoDefaultCurrentDirectoryInExePath=`), ou avec `.\artisan.cmd`, la commande répond normalement. Un poste Windows ordinaire n'a pas cette variable ; ce n'est pas un défaut du projet.

Durées : les deux installations de T-910 ont été deux fois plus longues que le premier essai Bash de T-907 (476 s), comme l'essai PowerShell (936 s). Les étapes ralenties sont `npm ci`, la compilation et le seeder, qui dépendent de la charge du poste ; les libellés « quelques minutes » des scripts restent justes.

### Écart supplémentaire

| N° | Fichier et ligne | Constat | Correction proposée | Agent |
| :-- | :-- | :-- | :-- | :-- |
| DOC-5 | `docs/05-development/developer-guide.md:113` | La colonne « Windows (cmd ou PowerShell) » donne `artisan.cmd migrate:status`. Sous PowerShell, cette forme échoue : « Le terme « artisan.cmd » n'est pas reconnu comme nom d'applet de commande », car PowerShell ne lance jamais un programme du dossier courant sans `.\`. `.\artisan.cmd --version` fonctionne (vérifié dans le clone). Même remarque pour `README.md:51` et `developer-guide.md:332`, qui ne précisent pas le shell | Écrire « cmd : `artisan.cmd migrate:status` ; PowerShell : `.\artisan.cmd migrate:status` » | DOC (10) |

### Bilan des anomalies de T-907

| N° | État après T-910 |
| :-- | :-- |
| QA-1 | Corrigée et vérifiée (point 4) |
| QA-2 | Inchangée, information seulement |
| QA-3 | Sans objet pour un clone : `artisan.cmd` arrive en CRLF et ne produit plus d'erreur (points 1 et 2) |
| Réserve de la conclusion (copie et non clone) | Levée : le scénario est confirmé sur un vrai `git clone` |

### Nettoyage

Depuis le clone, `APP_PORT=18080 FORWARD_DB_PORT=13307 VITE_PORT=15173 docker compose --profile test down -v --rmi local` (code 0 : conteneurs, réseau `stockflow-clone_default`, volumes `dbdata`, `vendor`, `node_modules` et image `stockflow-clone-app` supprimés), puis suppression du dossier `D:\projet_encours\stockflow-clone`. Contrôle : aucun conteneur, volume, réseau ni image dont le nom contient `clone` ou `qa`. L'installation de l'utilisateur tourne toujours (trois conteneurs `stackflow-*`, volumes `stackflow_*` intacts, `http://localhost:8080/up` 200) et son dossier de travail reste propre (`git status` vide avant l'ajout de cette section).

### Conclusion de T-910

L'installation depuis un vrai `git clone` fonctionne sans aide : les fichiers arrivent avec les bonnes fins de ligne, les bons droits et le BOM attendu, la commande unique installe l'application et la démonstration sans écart de stock, la relance est sans casse et annonce désormais un message juste, et la connexion réelle aboutit au tableau de bord. Seul reste l'écart de documentation DOC-5, mineur.
