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
| Pilotage | Tableau de bord en direct, alertes de réapprovisionnement, exports CSV et PDF, journal d'audit |

## Prérequis

- Docker Desktop (ou Docker Engine) avec Docker Compose v2.
- Git.
- 4 Go de mémoire libre.

PHP, Composer, Node et MySQL tournent dans les conteneurs : rien d'autre n'est à installer sur le poste.

## Installation en développement

```bash
git clone https://github.com/ivan9802/StockfLow.git stockflow
cd stockflow
cp .env.example .env

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app npm install
docker compose exec app npm run build
docker compose exec app php artisan migrate --seed
```

L'application répond sur http://localhost:8080. La dernière commande affiche le mot de passe provisoire du premier administrateur (`admin@stockflow.example`), à changer à la première connexion. Pour le fixer à l'avance : `ADMIN_PASSWORD="..." php artisan migrate --seed`.

Sous Windows, le premier `npm run build` et le chargement de la démonstration sont lents (plusieurs minutes) parce que les fichiers du projet sont lus à travers le montage Windows. C'est normal.

## Jeu de démonstration

```bash
docker compose exec app php artisan migrate:fresh --seed --seeder=DemoSeeder
```

Trois mois d'activité simulée : 60 produits sur 7 catégories, 6 fournisseurs, plusieurs centaines de mouvements, des commandes en cours, un inventaire validé et un autre en attente de validation, des produits en stock normal, sous le seuil et en rupture. Toutes les données passent par les services métier, comme en exploitation.

Comptes de démonstration, mot de passe commun `demo-stockflow-2026` :

| Profil | Adresse |
| :-- | :-- |
| Administrateur | admin@stockflow.test |
| Gestionnaire de stock | awa.kone@stockflow.test, yao.kouassi@stockflow.test |
| Vendeur (point de vente) | aminata.diallo@stockflow.test, serge.bamba@stockflow.test |
| Ancien vendeur, compte désactivé | ibrahim.toure@stockflow.test |

Ces comptes ne doivent jamais exister en production. Le seeder refuse de s'exécuter quand `APP_ENV=production`.

## Commandes utiles

| Commande (préfixer par `docker compose exec app`) | Effet |
| :-- | :-- |
| `php artisan test` | Tests unitaires et fonctionnels, sur la base MySQL de test |
| `php artisan test --testsuite=Concurrency` | Ventes simultanées par processus réels (quelques minutes) |
| `CONCURRENCY_ROUNDS=50 php artisan test --testsuite=Concurrency` | Même chose sur 50 tours |
| `php artisan stock:verify` | Rejoue l'historique et vérifie qu'il redonne exactement chaque niveau de stock |
| `vendor/bin/pint` | Mise en forme du code PHP |
| `npm run dev` | Serveur Vite avec rechargement à chaud |

## Architecture en bref

Monolithe Laravel 13 (PHP 8.4), rendu serveur Blade, Bootstrap 5, JavaScript natif et Fetch, MySQL 8.4.

Toute variation de stock passe par `App\Services\StockService`, dans une transaction, avec verrouillage des lignes concernées (`lockForUpdate`) dans un ordre constant. Chaque variation écrit une ligne dans `stock_movements`, table en ajout seul. Une contrainte `CHECK` empêche en dernier recours tout stock négatif dans la base.

Documentation détaillée :

| Document | Contenu |
| :-- | :-- |
| `docs/01-analysis/requirements.md` | Exigences, user stories, critères d'acceptation, matrice des droits |
| `docs/02-architecture/system-architecture.md` | Couches, services, diagrammes de séquence, sécurité, risques |
| `docs/02-architecture/database-design.md` | Modèle conceptuel et logique, dictionnaire de données, index |
| `docs/02-architecture/architecture-decisions.md` | Décisions d'architecture et leurs raisons |
| `docs/03-design/` | Charte d'interface et maquettes |
| `DECISIONS.md` | Registre court des décisions |
| `TASKS.md` | Backlog et avancement |

## Environnements

| Environnement | Base | Fichier |
| :-- | :-- | :-- |
| Développement | MySQL 8.4 du service `db` (port 3307 sur le poste) | `compose.yaml` |
| Tests | MySQL 8.4 du service `db-test`, en mémoire vive | `phpunit.xml` |
| Production | Voir `docs/07-deployment/` | `compose.prod.yaml` |

## Licence

Code propriétaire, réalisé pour un usage interne et académique.
