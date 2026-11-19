# Journal des versions

Format inspiré de « Keep a Changelog ». Dates au format JJ/MM/AAAA.

## Version du 19/11/2026 (non publiée)

Modifications de la branche `phase-3/initialisation`, non commitées au 19/11/2026.

### Ajouté

- Tableau de bord refondu : onze périodes prédéfinies et une période personnalisée validées par le serveur, comparaison facultative avec la période précédente, indicateurs à l'instant et indicateurs de la période, cinq graphiques Chart.js, actualisation sans rechargement, adresse partageable. Une seule vue pour tous les profils ; la vue séparée du vendeur est supprimée. Détail : `docs/10-ui-ux-dashboard/changelog.md`.
- Design system v2 : rayons, badges, ombres, états de chargement, squelettes, en-têtes de tableaux figés, écran de connexion en deux colonnes. Animations désactivées quand le système demande moins d'animations.
- Scripts d'installation locale `scripts/install.ps1` (PowerShell 7) et `scripts/install.sh` (Linux, macOS, Git Bash), avec les options démonstration, confirmation et contrôle des prérequis. Scripts de démarrage quotidien `scripts/start.ps1` et `scripts/start.sh`.
- Intégration continue GitHub Actions (`.github/workflows/ci.yml`) : validation de Composer, Pint, compilation des assets, migrations et tests sur MySQL 8.4, tests de concurrence, audit des dépendances Composer et npm. Le workflow n'a pas encore tourné sur GitHub.
- Contrôles de santé des conteneurs `app` et `web` en production, rotation des journaux des conteneurs (5 fichiers de 10 Mo par service, SEC-20).
- Documentation : installation locale, déploiement sans Docker, intégration continue, versions de référence (`docs/07-deployment/`), initialisation de la base (`docs/04-database/initialisation.md`), guide du développeur (`docs/05-development/developer-guide.md`).
- Variables documentées dans `.env.example` : `APP_PREVIOUS_KEYS`, `LOG_DAILY_DAYS`, `SESSION_EXPIRE_ON_CLOSE`, avec des commentaires sur `SESSION_SECURE_COOKIE` en production.

### Modifié

- PHP 8.4 exigé par `composer.json` (`^8.4`, au lieu de `^8.3`), en accord avec les dépendances verrouillées. Messages d'`artisan` et d'`artisan.cmd` alignés.
- Journaux Laravel conservés 30 jours par défaut au lieu de 14.
- Le seeder de référence ne réaffiche plus un mot de passe administrateur fourni par `ADMIN_PASSWORD`.

### Sécurité et configuration

Corrections issues de l'audit de configuration (`docs/05-development/backend-config-audit.md`, section Suivi) et de l'audit de préparation au déploiement (`docs/06-testing/security-audit.md`, section du 19/11/2026) :

- `TRUSTED_PROXIES` lu au moment de la requête par un middleware dédié : la valeur est prise en compte depuis `.env` comme depuis le cache de configuration (M-1).
- Cookie de session `Secure` par défaut quand `APP_ENV=production` (M-2, partie application).
- Sessions chiffrées par défaut et terminées à la fermeture du navigateur, pour les postes partagés (SEC-16).
- En-têtes de sécurité posés sur toutes les réponses, y compris `/up`, la maintenance et les pages 404 (SEC-21).
- Routes de service de fichiers du framework retirées : le disque `local` n'est plus servi directement (SEC-22).
- Port du serveur Vite lu dans la configuration pour la politique de contenu.
- Garde-fou de production : `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` et `db:wipe` refusés quand `APP_ENV=production`, même avec `--force`.
- Configuration de production en deux fichiers : `.env.production` pour l'application, `.env.secrets` (modèle `docker/env.secrets.example`) pour `DB_ROOT_PASSWORD` et `ADMIN_PASSWORD`, lu par Docker Compose seulement. Le mot de passe root de MySQL et le mot de passe initial de l'administrateur ne sont plus dans l'environnement de PHP (SEC-17).
- `scripts/prod-compose.sh` : toutes les commandes Docker Compose de production passent par lui et chargent les deux fichiers.
- `scripts/deploy.sh` refuse désormais une `APP_URL` hors `https://`, un cookie de session sans `Secure`, des mots de passe MySQL vides, d'exemple ou identiques, des secrets rangés dans `.env.production` et des fichiers de configuration non ignorés par Git (SEC-15). Option `--local-rehearsal` pour une répétition en HTTP sur un poste, jamais sur un serveur réel.
- Image de production durcie : code en `root:root`, non modifiable par `www-data`, sans `git`, `unzip` ni client MySQL (SEC-18). Traces d'exception des journaux sans arguments des fonctions (`zend.exception_ignore_args` dans `docker/php/php.ini`, M-4).
- Contrôle de santé de MySQL en production sans mot de passe sur la ligne de commande (SEC-19). En développement, `compose.yaml` passe encore le mot de passe public de `.env.example` par `-p`.
- En-têtes `X-Content-Type-Options`, `X-Frame-Options` et `Referrer-Policy` posés aussi par Nginx, fichiers statiques compris.

SEC-14 (mentions légales et durées de conservation) reste ouverte.

### Décisions

- D-021 (acceptée) : Chart.js 4 pour les graphiques du tableau de bord, installé par npm et compilé par Vite, sans CDN. Remplace D-015.
- D-022 (acceptée) : images de produits non réencodées au téléversement (écart SEC-07 accepté).
- D-023 (acceptée) : historique en ajout seul protégé au niveau de l'application et contrôlé par `stock:verify`, sans déclencheur MySQL (écart SEC-08 accepté).
- D-024 (acceptée) : licence propriétaire, `composer.json` aligné sur le README.

### Corrigé

- Montants qui passaient à la ligne dans les cartes d'indicateurs.
- Tableaux d'activité écrasés ou tronqués sur le tableau de bord.
- Majuscule fautive au nom du mois dans la date.
- Tableau de bord en erreur 500 sur une base sans emplacement.

## 1.0.0 (en cours de recette)

### Ajouté

- Authentification par e-mail avec limitation des tentatives, changement de mot de passe imposé après attribution par l'administrateur, trois profils aux droits vérifiés côté serveur sur chaque route.
- Catalogue : produits avec référence et code-barres EAN-13 générés, images contrôlées, catégories, fournisseurs, recherche instantanée et filtres.
- Stock par produit et par emplacement (entrepôt central et point de vente), historique complet des mouvements avec stock avant et après.
- Sorties internes et ajustements manuels avec motif obligatoire.
- Commandes fournisseurs, réceptions totales ou partielles, réception sans commande, solde d'une commande.
- Transferts entre emplacements en une étape, annulables, avec bon de transfert.
- Point de vente : disponibilité en direct, refus en bloc d'une vente en stock insuffisant, protection contre le double envoi, bon de sortie imprimable, annulation motivée.
- Inventaires : comptage sur tablette enregistré ligne par ligne, théorique relevé à la saisie, écarts justifiés, validation par l'administrateur, régularisation automatique.
- Alertes de réapprovisionnement au franchissement du seuil, centre d'alertes.
- Tableau de bord en direct, rapports CSV et PDF, journal d'audit consultable.
- Commande `stock:verify` qui rejoue l'historique pour vérifier chaque niveau de stock.
- Jeu de démonstration de trois mois d'activité.
- Environnement Docker de développement et de production, scripts de déploiement, de sauvegarde et de restauration.

### Corrigé pendant le développement

- Interblocage à la création d'un compteur de références lors de ventes simultanées (14/11/2026).
- Fichier non image accepté au téléversement s'il portait une extension d'image (14/11/2026).
- Erreur sur le tableau de bord due à une colonne ambiguë (14/11/2026).
- Écart d'inventaire compté deux fois quand une vente avait lieu avant le comptage : le théorique est désormais relevé à la saisie (révision de D-007, 13/11/2026).
