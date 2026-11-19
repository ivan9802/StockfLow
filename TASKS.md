# Backlog et avancement StockFlow

Statuts : À faire, En cours, En revue, Bloqué, Terminé. Une tâche passe à « Terminé » quand ses critères de validation ont été vérifiés par une commande réellement lancée ou par une relecture consignée.

Agents : PO (product owner), ARCH (architecte), UX (designer), BE (backend), FE (frontend), DB (base de données), QA (tests), SEC (sécurité), OPS (DevOps), DOC (rédacteur), CTO (agent principal).

Dernière mise à jour : 19/11/2026. Résultats vérifiés : 126 tests Unit et Feature, 3 tests de concurrence, `stock:verify` sans écart sur la démonstration.

## Phase 0 : diagnostic et organisation

| ID | Tâche | Agent | Priorité | Statut | Dépendances | Résultat attendu | Critères de validation |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| T-001 | Inspecter l'environnement (outils, versions, services) | CTO | Haute | Terminé | | État des prérequis | Versions relevées par commande : PHP 8.2.12, Composer 2.10.2, Node 22, MariaDB 10.4.32, Docker 29 (démon arrêté), Git 2.55 |
| T-002 | Recenser les agents et les skills disponibles | CTO | Haute | Terminé | | Tableau des skills dans `AGENTS.md` | Seuls des skills réellement chargés sont cités |
| T-003 | Vérifier le support de Laravel 11 | CTO | Haute | Terminé | | Recommandation D-001 | Dates lues sur laravel.com le 13/11/2026 |
| T-004 | Rédiger `CLAUDE.md`, `AGENTS.md`, `DECISIONS.md`, `TASKS.md` | CTO | Haute | Terminé | T-001 | Instructions permanentes du projet | Fichiers présents à la racine |

## Phase 1 : analyse et cadrage

| ID | Tâche | Agent | Priorité | Statut | Dépendances | Résultat attendu | Critères de validation |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| T-101 | Analyse du cahier des charges, exigences, user stories, backlog produit, cas d'utilisation, classes métier | PO | Haute | En revue | T-004 | `docs/01-analysis/requirements.md` | Cohérent avec `database-design.md` et `DECISIONS.md` ; chaque story a des critères testables |
| T-102 | Validation par l'utilisateur des décisions proposées (D-001 à D-003, D-006 à D-009, D-016) | CTO | Haute | Terminé | T-101, T-201 | Statuts mis à jour dans `DECISIONS.md` | Accord explicite de l'utilisateur |

## Phase 2 : architecture et conception

| ID | Tâche | Agent | Priorité | Statut | Dépendances | Résultat attendu | Critères de validation |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| T-200 | Modèle de données : MCD, MLD, dictionnaire, index, règles d'intégrité | ARCH, DB | Haute | Terminé | T-004 | `docs/02-architecture/database-design.md` | Couvre les 12 points de la mission ; relu par PO et ARCH |
| T-201 | Architecture technique, diagrammes de séquence, arborescence, conventions, risques, ADR | ARCH | Haute | En revue | T-200 | `system-architecture.md`, `architecture-decisions.md` | Un ADR par décision du registre ; diagrammes Mermaid valides |
| T-202 | Charte d'interface et wireframes | UX | Haute | Terminé | T-200 | `docs/03-design/ui-guidelines.md`, `wireframes.md` | Contrastes AA calculés ; tous les écrans des modules couverts |
| T-203 | Revue croisée des livrables de conception et correction des incohérences | CTO | Haute | Terminé | T-101, T-201, T-202 | Documents alignés | Aucun statut, nom de table ou règle contradictoire entre les documents |
| T-204 | Schéma SQL de référence généré depuis les migrations | DB | Moyenne | Terminé | T-303 | `docs/04-database/schema.sql` | Produit par `mysqldump --no-data` sur une base migrée |

## Phase 3 : initialisation

| ID | Tâche | Agent | Priorité | Statut | Dépendances | Résultat attendu | Critères de validation |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| T-301 | Environnement Docker Compose (PHP-FPM, Nginx, MySQL, Node) | OPS | Haute | Terminé | T-102 | `compose.yaml`, `docker/` | `docker compose up` démarre ; `php -v` et `mysql --version` dans les conteneurs. Bloqué tant que D-001 à D-003 ne sont pas tranchées et que le démon Docker est arrêté |
| T-302 | Création du projet Laravel, `.env.example`, locale `fr`, fuseau `Africa/Abidjan`, Vite et Bootstrap 5 | BE, FE | Haute | Terminé | T-301 | Squelette qui s'affiche | Page d'accueil servie en HTTP 200 ; `npm run build` sans erreur |
| T-303 | Migrations de toutes les tables, contraintes `CHECK`, index | DB | Haute | Terminé | T-302 | `database/migrations/` | `php artisan migrate:fresh` sans erreur sur MySQL ; contraintes vérifiées par test |
| T-304 | Modèles Eloquent, enums, relations, factories | BE | Haute | Terminé | T-303 | `app/Models`, `app/Enums`, `database/factories` | Tests unitaires des relations |
| T-305 | Authentification, compte actif, limitation des tentatives, changement de mot de passe | BE, FE | Haute | Terminé | T-304 | Connexion et déconnexion | Tests fonctionnels : succès, échec, compte désactivé, blocage après 5 essais |
| T-306 | Rôles, Gates, Policies, middleware de rôle | BE | Haute | Terminé | T-305 | Matrice des permissions appliquée | Un test par cellule sensible de la matrice (HTTP 403 attendu) |
| T-307 | Gabarit d'interface : barre latérale, barre supérieure, composants Blade de base | FE, UX | Haute | Terminé | T-302, T-202 | `resources/views/layouts`, `components` | Rendu vérifié à 1280, 768 et 360 px |
| T-308 | Middleware d'en-têtes de sécurité, pages 403, 404, 419, 500 | BE, SEC | Moyenne | Terminé | T-302 | En-têtes présents | Test fonctionnel sur les en-têtes |

## Phase 4 : développement métier

| ID | Tâche | Agent | Priorité | Statut | Dépendances | Résultat attendu | Critères de validation |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| T-401 | Catégories : CRUD | BE, FE | Haute | Terminé | T-306, T-307 | Module catégories | Tests CRUD et refus de suppression d'une catégorie non vide |
| T-402 | Fournisseurs : CRUD, suppression logique | BE, FE | Haute | Terminé | T-401 | Module fournisseurs | Tests CRUD et permissions |
| T-403 | Produits : CRUD, SKU et code-barres générés, image, recherche instantanée, filtres | BE, FE | Haute | Terminé | T-402 | Module produits | Tests d'unicité, de téléversement, de recherche ; réponse de recherche sous 500 ms sur 800 produits |
| T-404 | Emplacements et lignes `stock_levels` créées avec le produit | BE, DB | Haute | Terminé | T-403 | État des stocks par emplacement | Chaque produit a une ligne par emplacement actif |
| T-405 | `StockService` : augmentation, diminution, verrouillage ordonné, mouvement, exceptions | BE, QA | Haute | Terminé | T-404 | Moteur de stock | Tests écrits avant le code : stock négatif refusé, mouvement créé, stock avant et après exacts |
| T-406 | Sorties internes et ajustements manuels avec motif obligatoire, historique des mouvements | BE, FE | Haute | Terminé | T-405 | Module mouvements | Tests de validation du motif et de permissions |
| T-407 | Commandes fournisseurs et réceptions partielles, bon de réception | BE, FE | Haute | Terminé | T-405 | Module achats | Test : commande reçue en deux fois, statuts `partially_received` puis `received`, sur-réception refusée |
| T-408 | Transferts entre emplacements, annulation, bon de transfert | BE, FE | Haute | Terminé | T-405 | Module transferts | Test : deux mouvements de même référence, somme des stocks inchangée, transfert refusé si stock insuffisant |
| T-409 | Ventes : panier, vérification de disponibilité, bon de sortie, annulation, historique | BE, FE | Haute | Terminé | T-405 | Module ventes | Tests : vente refusée en bloc si une ligne manque de stock, annulation qui restitue le stock |
| T-410 | Inventaires : session, comptage, écarts, justification, validation, régularisation | BE, FE | Haute | Terminé | T-405 | Module inventaires | Tests : écart sans justification refusé, validation réservée à l'admin, mouvements `inventory_adjustment` créés |
| T-411 | Journal d'audit : service, écoute des modèles et des connexions, consultation filtrée | BE, FE | Haute | Terminé | T-306 | Module audit | Tests : création, modification, suppression, connexion tracées ; journal non modifiable |

## Phase 5 : interface et tableau de bord

| ID | Tâche | Agent | Priorité | Statut | Dépendances | Résultat attendu | Critères de validation |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| T-501 | Tableau de bord : KPI, dernières opérations, répartition par emplacement, variante vendeur | BE, FE | Haute | Terminé | T-409, T-410 | Page d'accueil | Test : les KPI égalent les agrégats recalculés en SQL sur la base de démonstration |
| T-502 | Alertes de seuil : événement, notification en base, cloche, centre d'alertes | BE, FE | Haute | Terminé | T-405 | Alertes dans l'interface | Test : une alerte au franchissement du seuil, pas de doublon ensuite |
| T-503 | Exports CSV (état des stocks, mouvements, ventes) | BE | Haute | Terminé | T-406 | Fichiers CSV | Test : en-têtes, encodage UTF-8 avec BOM, nombre de lignes |
| T-504 | Rapports PDF (état de stock, historique des mouvements, bons) | BE, FE | Haute | Terminé | T-503 | Fichiers PDF | Test : réponse `application/pdf` non vide ; relecture visuelle |
| T-505 | Actualisation dynamique des stocks et recherches en Fetch | FE | Moyenne | En revue | T-403, T-409 | Interactions sans rechargement | Vérification dans le navigateur ; routes JSON testées |
| T-506 | Jeu de démonstration par les services (60 produits, 150 mouvements et plus) | DB | Haute | Terminé | T-410 | `DemoSeeder` | `migrate:fresh --seed` sans erreur ; test de cohérence mouvements et niveaux |

## Phase 6 : tests et sécurisation

| ID | Tâche | Agent | Priorité | Statut | Dépendances | Résultat attendu | Critères de validation |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| T-601 | Plan de tests et matrice de couverture des critères d'acceptation | QA | Haute | Terminé | T-101 | `docs/06-testing/test-plan.md` | Chaque user story « Must » reliée à un test |
| T-602 | Tests de concurrence : deux ventes simultanées par processus séparés sur MySQL | QA | Haute | En cours | T-409 | `tests/Concurrency` | Stock final jamais négatif sur 50 exécutions ; une seule vente réussit quand le stock ne couvre pas les deux |
| T-603 | Test d'intégrité : rejouer tous les mouvements redonne les niveaux de stock | QA | Haute | Terminé | T-506 | Test et commande `stock:verify` | Zéro écart sur la base de démonstration |
| T-604 | Tests de permissions par requête HTTP directe sur toutes les routes | QA, SEC | Haute | Terminé | T-411 | Tests fonctionnels | Aucune route accessible hors matrice |
| T-605 | Audit de sécurité (authentification, autorisations, CSRF, XSS, injection, téléversement, secrets, en-têtes) | SEC | Haute | En cours | T-604 | `docs/06-testing/security-audit.md` | Non-conformités classées et corrigées ou acceptées explicitement |
| T-606 | Revue de code et correction des anomalies | CTO, QA | Haute | À faire | T-605 | Anomalies soldées | Suite complète verte ; `pint --test` sans écart ; `composer audit` et `npm audit` relevés |

## Phase 7 : documentation et déploiement

| ID | Tâche | Agent | Priorité | Statut | Dépendances | Résultat attendu | Critères de validation |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| T-701 | `README.md`, `CHANGELOG.md`, guide d'installation, variables d'environnement | DOC | Haute | En cours | T-606 | Documentation de démarrage | Installation rejouée depuis un clone neuf en suivant le README |
| T-702 | Documentation des modules et de la base (dictionnaire, schéma SQL) | DOC, DB | Moyenne | En cours | T-606 | `docs/04-database`, `docs/05-development` | Conforme au code livré |
| T-703 | Guide utilisateur et guide administrateur | DOC | Haute | En cours | T-606 | `docs/08-user-guides/` | Chaque parcours de `requirements.md` y figure |
| T-704 | Configuration de production, script de déploiement, migrations en production | OPS | Haute | En cours | T-606 | `compose.prod.yaml`, `scripts/deploy.*` | Déploiement rejoué sur une pile propre |
| T-705 | Sauvegarde et restauration de la base et des images | OPS | Haute | En cours | T-704 | `scripts/backup.*`, `scripts/restore.*`, procédure | Restauration testée une fois avec comparaison des volumes |
| T-706 | Vérification après déploiement et bilan final | CTO | Haute | À faire | T-705 | Bilan | Parcours de démonstration exécutés sur l'environnement cible |

## Phase 8 : refonte de l'interface et tableau de bord de pilotage

| ID | Tâche | Agent | Priorité | Statut | Dépendances | Résultat attendu | Critères de validation |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| T-801 | Audit de l'interface existante | UX, CTO | Haute | Terminé | | `docs/10-ui-ux-dashboard/audit-interface.md` | Défauts relevés avec écran et gravité, vérifiés dans Chrome |
| T-802 | Design system v2 : rayons, ombres, états de chargement, composants | UX, FE | Haute | Terminé | T-801 | `_design-system.scss`, `design-system.md` | Rendu vérifié à 1440, 820 et 390 px, aucune erreur de console ni blocage CSP |
| T-803 | Animations, respect de `prefers-reduced-motion` | FE | Moyenne | Terminé | T-802 | `_motion.scss`, `animations.md` | Parcours Chrome avec et sans réduction des animations |
| T-804 | Période d'analyse validée côté serveur, comparaison avec la période précédente | BE | Haute | Terminé | | `App\Support\Period`, `DashboardPeriodRequest` | 26 tests unitaires sur les préréglages, comparaisons et refus |
| T-805 | Indicateurs, séries et top produits par période, filtrés par profil | BE | Haute | Terminé | T-804 | `DashboardService` | 12 tests fonctionnels : montants, variations, transferts exclus, périmètre du vendeur, validation |
| T-806 | Tableau de bord : cartes, sélecteur, cinq graphiques Chart.js, actualisation par Fetch | FE | Haute | Terminé | T-805 | `dashboard/index.blade.php`, `modules/dashboard.js` | Changement de période sans rechargement vérifié dans Chrome, chiffres identiques au serveur |
| T-807 | Documentation de la refonte | DOC | Moyenne | Terminé | T-806 | `docs/10-ui-ux-dashboard/` | Sept documents, règles de calcul conformes au code |
| T-808 | Test de bout en bout automatisé dans le dépôt (Playwright) | QA | Moyenne | À faire | T-806 | `tests/Browser` | Le script de vérification Chrome reste aujourd'hui hors dépôt |

## Mission 1 : préparation au déploiement et à l'installation (19/11/2026)

Aucun commit ni push pendant cette mission. Les agents sont les rôles d'`AGENTS.md`, invoqués par l'agent générique de Claude Code ; le projet ne contient pas de fichier d'agent dédié.

| ID | Tâche | Agent | Priorité | Statut | Dépendances | Fichiers autorisés | Critères de validation |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| T-901 | Gouvernance : état du dépôt, incohérences documentation et code, backlog de la mission | CTO | Haute | Terminé | | `TASKS.md`, `DECISIONS.md` | Tâches attribuées sans chevauchement de fichiers |
| T-902 | Audit de la configuration Laravel : variables réellement lues, sessions, cache, journaux, stockage, erreurs, base, dépendances Composer | BE (4) | Haute | Terminé | T-901 | `app/`, `routes/`, `config/`, rapport `docs/05-development/backend-config-audit.md` | Chaque variable de `env()` comparée à `.env.example` ; `php artisan config:show` et `composer validate` exécutés |
| T-903 | Base de données : reconstruction depuis une base vierge isolée, migrations, contraintes, seeders, démonstration | DB (6) | Haute | Terminé | T-901 | `database/seeders/`, `database/factories/`, `docs/04-database/` (aucune migration attribuée) | `migrate:fresh` puis `ReferenceDataSeeder` et `DemoSeeder` sur une base dédiée, `stock:verify` sans écart |
| T-904 | Environnements, scripts d'installation Windows et Linux, intégration continue, journaux, sauvegardes | DevOps (9) | Haute | Terminé | T-901 | `docker/`, `compose*.yaml`, `scripts/`, `.github/`, `docs/07-deployment/` | Scripts exécutés au moins une fois ; workflow GitHub Actions validé syntaxiquement |
| T-905 | Audit de sécurité préventif du déploiement | SEC (8) | Haute | Terminé | T-901 | Lecture seule ; `docs/06-testing/security-audit.md` | Constats avec fichier et ligne, aucun secret recopié |
| T-906 | Documentation développeur : README, journal des versions, guide de développement | DOC (10) | Haute | Terminé | T-902, T-903, T-904 | `README.md`, `CHANGELOG.md`, `docs/05-development/` (hors rapport du backend) | Chaque commande documentée existe dans le code ou les scripts |
| T-907 | Vérification de l'installation depuis un clone propre, dans un environnement isolé | QA (7) | Haute | Terminé | T-904, T-906 | `docs/06-testing/installation-check.md` | Les huit étapes du scénario exécutées, résultat de chacune consigné |
| T-908 | Corrections issues des rapports, attribuées agent par agent | CTO | Haute | Terminé | T-902 à T-907 | Selon attribution | Chaque correction vérifiée par une commande |
| T-908a | Corrections backend M-1, M-2 (configuration), M-3, mineures de `backend-config-audit.md`, SEC-16, SEC-21, SEC-22, garde-fou contre `migrate:fresh` en production | BE (4) | Haute | Terminé | T-902 | `app/`, `bootstrap/app.php`, `config/`, `composer.json`, `composer.lock` (via `composer update --lock` seulement), `.env.example` (attribution explicite du CTO) | Tests Auth et Security verts, `composer validate --strict` sans avertissement |
| T-908b | Corrections DevOps M-2 (contrôle dans `deploy.sh`), M-4 (`zend.exception_ignore_args`), SEC-15, SEC-17 à SEC-20, message des scripts d'installation quand `ADMIN_PASSWORD` est fourni, en-têtes de sécurité aussi posés par Nginx, conservation des journaux alignée sur 30 jours, retrait du bloc Nginx `/storage/` | DevOps (9) | Haute | Terminé | T-904 | `docker/`, `scripts/` | Après la fin de T-904, pour ne pas croiser ses modifications |
| T-908c | Alignement de `system-architecture.md` et `database-design.md` sur le code (M-5 du backend, écarts 4 de la base de données) | ARCH (2) | Moyenne | Terminé | T-902, T-903 | `docs/02-architecture/system-architecture.md`, `docs/02-architecture/database-design.md` | Chaque écart de M-5 corrigé ou justifié |
| T-908d | Retouches de la documentation de la base (garde-fou de production) et du commentaire de `DatabaseSeeder` | DB (6) | Basse | Terminé | T-908a | `docs/04-database/`, `database/seeders/` | Pint sans écart |
| T-908e | Suite de tests par défaut Unit et Feature (`phpunit.xml`), `AGENTS.md` (`resources/scss/`), retrait de `VITE_APP_NAME` | CTO | Basse | Terminé | T-906 | `phpunit.xml`, `AGENTS.md`, `.env.example` | `phpunit --list-tests` : 183 tests, aucun de concurrence |
| T-908f | Documentation de production après T-908b : guide administrateur, README, guide du développeur, journal des versions | DOC (10) | Moyenne | Terminé | T-908b | `README.md`, `CHANGELOG.md`, `docs/05-development/developer-guide.md`, `docs/08-user-guides/admin-guide.md` | Plus aucune commande de production hors `scripts/prod-compose.sh` |
| T-908g | `.gitignore` (`.env.secrets`) et commentaires de `.env.example` | CTO | Haute | Terminé | T-908b | `.gitignore`, `.env.example` | `git check-ignore` confirme que `.env.secrets` est ignoré |
| T-908h | Retouches après la vérification QA : QA-1 (message administrateur des scripts), QA-3 (`artisan.cmd` en CRLF), DOC-1 à DOC-3 | DevOps (9), DOC (10), CTO | Basse | Terminé | T-907 | `scripts/install.*`, `docs/07-deployment/local-install.md`, `README.md`, `developer-guide.md`, `artisan.cmd`, `CLAUDE.md` | `bash -n`, analyseur PowerShell, `file artisan.cmd` : CRLF |
| T-909 | Rapport final de la mission | CTO | Haute | Terminé | T-908 | | Rapport en douze points, anomalies restantes explicites |

## Anomalies et points ouverts

| ID | Date | Constat | Suite |
| :-- | :-- | :-- | :-- |
| A-001 | 13/11/2026 | Démon Docker arrêté sur le poste | L'utilisateur doit lancer Docker Desktop avant la phase 3 si D-003 est acceptée |
| A-002 | 13/11/2026 | Extensions PHP `gd`, `intl`, `sodium` désactivées dans `C:\xampp\php\php.ini` | Sans objet si D-003 est acceptée ; sinon à activer avec l'accord de l'utilisateur |
| A-004 | 13/11/2026 | Les diagrammes Mermaid de `requirements.md` et de `system-architecture.md` n'ont pas été passés dans un moteur de rendu | T-101 et T-201 restent « En revue » jusqu'au contrôle du rendu (sur GitHub après le premier push, ou en local) |
| A-005 | 13/11/2026 | Revue croisée : défaut de calcul de D-007 trouvé par l'architecte, références dérivées de la clé primaire incompatibles avec l'ajout seul, annulations sans colonnes | Corrigé le jour même : D-007 révisée, D-017 à D-020 ajoutées, modèle de données et documents alignés |
| A-006 | 14/11/2026 | Interblocage MySQL à la création d'un compteur de références (première vente de l'année avec plusieurs vendeurs simultanés), trouvé par le test de concurrence à huit processus | Corrigé : création et incrément du compteur en une instruction atomique `INSERT ... ON DUPLICATE KEY UPDATE`. Les trois tests de concurrence passent |
| A-007 | 14/11/2026 | Un fichier texte renommé en `.png` passait la validation d'image | Corrigé : le contenu doit se décoder comme une image (`getimagesize`). Test ajouté |
| A-008 | 14/11/2026 | Tableau de bord en erreur 500 : colonne `is_active` ambiguë dans la répartition par emplacement, trouvée par le test d'affichage de tous les écrans | Corrigé : colonnes qualifiées par leur table |
| A-009 | 14/11/2026 | T-505 : les interactions JavaScript (panier, comptage, filtres) ne sont vérifiées qu'au travers de leurs points JSON, pas dans un navigateur | Test Playwright à ajouter ; T-505 reste « En revue » |
| A-010 | 14/11/2026 | T-602 : le critère prévoit 50 tours de concurrence, seuls 5 tours ont été exécutés | Relancer avec `CONCURRENCY_ROUNDS=50` |
| A-011 | 19/11/2026 | `MovementController::store` ouvre lui-même une transaction, contrairement à la règle « contrôleur mince » (relevé par l'architecte, T-908c) | Mission 2 : décider s'il faut un service pour les mouvements manuels |
| A-012 | 19/11/2026 | Noms des méthodes de test en français, alors que CLAUDE.md demande du code en anglais | Décision à prendre : consigner la pratique ou renommer |
| A-013 | 19/11/2026 | Aucun test d'architecture ne vérifie que seul `StockService` écrit `stock_levels.quantity` | Mission 2, QA |
| A-014 | 19/11/2026 | `docs/02-architecture/architecture-decisions.md` sans ADR pour D-017 à D-023 révisées ni D-021 | Architecte, mission 2 |
| A-003 | 13/11/2026 | Le dépôt distant s'appelle `StockfLow` et le dossier local `stackflow` | Sans effet sur le code ; le nom du produit reste StockFlow |
