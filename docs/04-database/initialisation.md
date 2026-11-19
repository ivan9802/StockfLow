# Initialisation de la base de données

Ce guide décrit comment construire une base StockFlow propre depuis zéro, y charger les données de référence ou le jeu de démonstration, la réinitialiser et vérifier sa cohérence. Toutes les commandes ont été exécutées le 19/11/2026 sur une base dédiée `stockflow_verif` (tâche T-903) ; les durées et volumes cités en sont tirés.

Les commandes s'exécutent dans le conteneur `app`. Sous Git Bash, exporter d'abord `MSYS_NO_PATHCONV=1` pour que les chemins ne soient pas convertis.

## Ce qu'il faut savoir avant de commencer

- La source de vérité du schéma est `database/migrations`. Le fichier `docs/04-database/schema.sql` en est un export (`mysqldump --no-data`) destiné à la lecture ; il ne sert pas à créer la base.
- Le stock ne s'écrit jamais à la main. Les seeders passent par les services métier, comme l'application : chaque quantité a son mouvement, et `php artisan stock:verify` le prouve.
- Le jeu de démonstration contient des comptes au mot de passe public. Il ne se charge jamais en production.

## Créer la base

En développement, la base `stockflow` et l'utilisateur `stockflow` sont créés par l'image MySQL au premier démarrage du service `db`, à partir de `DB_DATABASE`, `DB_USERNAME` et `DB_PASSWORD` de `.env` :

```bash
docker compose up -d
docker compose ps        # attendre que db soit « healthy »
```

Pour une base supplémentaire (essai, vérification, recette) dans le même serveur, la créer avec le compte root du conteneur, puis donner les droits à l'utilisateur de l'application :

```bash
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE IF NOT EXISTS stockflow_verif CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON stockflow_verif.* TO \`stockflow\`@\`%\`;"'
```

Toute commande artisan vise alors cette base en surchargeant la variable : `docker compose exec -T -e DB_DATABASE=stockflow_verif app php artisan ...`. La surcharge n'a d'effet que si la configuration n'est pas en cache (`php artisan config:clear` au besoin). Avant toute écriture, contrôler la base réellement utilisée :

```bash
docker compose exec -T -e DB_DATABASE=stockflow_verif app php artisan tinker --execute 'echo DB::selectOne("select database() d")->d;'
```

`php artisan db:show` affiche aussi le nom de la base dans la ligne « Database », mais sa liste de tables couvre tous les schémas accessibles à l'utilisateur : sur une base vide, elle montre les tables de `stockflow`. Ce n'est pas un signe d'erreur.

## Migrer

```bash
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan migrate:status
```

Onze migrations créent 27 tables (InnoDB, `utf8mb4_unicode_ci`) : les 20 tables de l'application (dont `notifications` et `document_sequences`), plus `migrations`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches` et `failed_jobs`. Il n'y a pas de table `password_reset_tokens` : l'application n'envoie pas d'e-mail.

Les migrations ajoutent huit contraintes `CHECK` par instruction SQL, qui n'existent donc que sous MySQL :

| Table | Contrainte | Règle |
| :-- | :-- | :-- |
| stock_levels | chk_stock_levels_quantity | quantity >= 0 |
| stock_movements | chk_stock_movements_quantity | quantity > 0 |
| purchase_order_items | chk_poi_ordered | quantity_ordered > 0 |
| purchase_order_items | chk_poi_received | quantity_received <= quantity_ordered |
| goods_receipt_items | chk_gri_quantity | quantity > 0 |
| sale_items | chk_sale_items_quantity | quantity > 0 |
| stock_transfers | chk_transfer_warehouses | from_warehouse_id <> to_warehouse_id |
| stock_transfer_items | chk_transfer_items_quantity | quantity > 0 |

Les méthodes `down()` fonctionnent : `php artisan migrate:rollback --force` défait les onze migrations dans l'ordre inverse et ne laisse que la table `migrations`, puis `migrate` reconstruit le même schéma.

## Charger les données de référence

`ReferenceDataSeeder` crée ce sans quoi l'application ne démarre pas : les deux emplacements (`ENT` entrepôt central, `PDV` point de vente Plateau, décision D-020) et un premier administrateur. C'est lui que lance `php artisan migrate --seed` (par `DatabaseSeeder`).

```bash
docker compose exec -T app php artisan db:seed --class=ReferenceDataSeeder --force
```

L'administrateur est créé seulement s'il n'en existe aucun, avec `must_change_password` à vrai :

| Variable | Défaut | Rôle |
| :-- | :-- | :-- |
| ADMIN_EMAIL | admin@stockflow.example | Adresse de connexion |
| ADMIN_NAME | Administrateur | Nom affiché |
| ADMIN_PASSWORD | vide | Mot de passe provisoire. Vide : 16 caractères tirés au hasard et affichés une seule fois |

Les variables se lisent dans `.env` ou dans l'environnement du conteneur. Préfixer la commande sur le poste (`ADMIN_PASSWORD=... docker compose exec ...`) ne les transmet pas : passer par `-e ADMIN_PASSWORD=...` ou par `.env`. Quand `ADMIN_PASSWORD` est fourni, le seeder ne le réaffiche pas ; un mot de passe tiré au hasard est affiché, puisque c'est le seul moyen de le connaître.

Le seeder peut être relancé sans risque : les emplacements sont créés par `firstOrCreate` et l'administrateur n'est pas recréé.

## Charger la démonstration

Jamais en production. Sur un poste de développement ou une base de recette :

```bash
docker compose exec -T app php artisan migrate:fresh --seed --seeder=DemoSeeder
```

`migrate:fresh` supprime toutes les tables de la base visée avant de migrer. Le seeder simule ensuite trois mois d'activité (92 jours qui se terminent la veille du chargement) en appelant les services métier : réceptions, transferts, ventes et annulations, sorties internes, inventaire validé, commandes en cours, inventaire en attente de validation.

Volumes obtenus le 19/11/2026, identiques sur deux chargements successifs :

| Élément | Volume | Exigence du cahier |
| :-- | --: | :-- |
| Produits | 61, dont 60 actifs et 1 retiré de la vente | 50 au moins |
| Catégories | 7 | 5 au moins |
| Fournisseurs | 6 | 5 au moins |
| Emplacements | 2 (ENT, PDV) | 2 au moins |
| Utilisateurs | 6 : 1 administrateur, 2 gestionnaires, 3 vendeurs dont 1 désactivé | 3 rôles, plusieurs utilisateurs |
| Lignes de stock | 122 (61 produits sur 2 emplacements) | |
| Mouvements | 1 344 | 100 au moins |
| Ventes | 295, dont 9 annulées (553 lignes) | |
| Commandes fournisseurs | 19 (1 brouillon, 9 commandées, 2 partiellement reçues, 7 reçues) | |
| Bons de réception | 16 | |
| Transferts | 29 | |
| Inventaires | 2 (1 validé, 1 en attente de validation) | |
| Journal d'audit | 474 lignes | |
| Alertes (notifications) | 120 | |

État des 60 produits actifs, stock total comparé au seuil d'alerte : 45 en stock normal, 8 sous le seuil, 7 en rupture.

Les mouvements couvrent du 19/08/2026 à 08:00 au 18/11/2026, sur 77 jours distincts (pas d'activité le dimanche). Les dates sont calculées à partir du jour du chargement : une base chargée un autre jour couvre une autre période, et ses volumes peuvent légèrement différer, puisque la place des dimanches (sans vente) et des lundis (réassort, commandes) change.

Les tirages aléatoires partent d'une graine fixe (`mt_srand(2026)`) : deux chargements le même jour donnent la même base. La reproductibilité n'a pas été vérifiée sur deux jours différents. La vérification du 19/11/2026 a porté sur tous les volumes ci-dessus, le chiffre d'affaires (35 019 500 F CFA), la valeur du stock (26 355 400 F CFA) et une empreinte MD5 de l'ensemble des mouvements et du catalogue, identiques d'un chargement à l'autre.

### Comptes de démonstration

Mot de passe commun, public et réservé à la démonstration : `demo-stockflow-2026`.

| Profil | Adresse |
| :-- | :-- |
| Administrateur | admin@stockflow.test |
| Gestionnaire de stock | awa.kone@stockflow.test, yao.kouassi@stockflow.test |
| Vendeur (point de vente) | aminata.diallo@stockflow.test, serge.bamba@stockflow.test |
| Ancien vendeur, compte désactivé | ibrahim.toure@stockflow.test |

Les cinq premiers comptes sont actifs et sans changement de mot de passe imposé. Les vendeurs sont affectés au point de vente. Le jeu de démonstration ne crée pas l'administrateur de `ReferenceDataSeeder` (`admin@stockflow.example`).

### Protection contre la production

`DemoSeeder` lève une exception dès sa première instruction quand `APP_ENV=production`, avant toute requête : `php artisan db:seed --class=DemoSeeder --force` échoue avec « Le jeu de démonstration ne se charge jamais en production. » et la base n'est pas modifiée (vérifié par comptage avant et après).

Le garde-fou du seeder ne suffisait pas à lui seul : `migrate:fresh --force` supprime toutes les tables avant d'appeler le seeder. Lors de la vérification du 19/11/2026, la base est ainsi arrivée vide alors que le seeder avait refusé de tourner. Depuis, `app/Providers/AppServiceProvider.php` appelle `DB::prohibitDestructiveCommands($this->app->isProduction())`. En production, `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` et `db:wipe` refusent donc de s'exécuter, même avec `--force`. Une restauration passe par `scripts/restore.sh`. Ce nouveau comportement n'a pas été rejoué sur une base dans le cadre de ce guide.

## Réinitialiser

| Besoin | Commande |
| :-- | :-- |
| Base vide avec emplacements et administrateur | `php artisan migrate:fresh --seed --force` |
| Base de démonstration | `php artisan migrate:fresh --seed --seeder=DemoSeeder` |
| Défaire toutes les migrations | `php artisan migrate:rollback --force` (une fois par lot) ou `php artisan migrate:reset --force` |
| Supprimer une base de vérification | `docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "DROP DATABASE stockflow_verif;"'` |

En production, les commandes artisan de ce tableau sont refusées (voir plus haut). La suppression d'une base par le client `mysql` n'est pas concernée par ce garde-fou. Une réinitialisation efface les comptes créés à la main. Sur la base de développement `stockflow`, s'assurer d'abord qu'aucun compte utile n'y a été ajouté, ou travailler sur une base dédiée comme décrit plus haut.

## Vérifier

```bash
docker compose exec -T app php artisan stock:verify
```

La commande rejoue l'historique en lecture seule : pour chaque couple produit et emplacement, la somme signée des mouvements doit égaler `stock_levels.quantity`, et chaque mouvement doit partir du `stock_after` du précédent. Code de sortie 0 si tout concorde, 1 sinon. Sortie obtenue après chacun des deux chargements de la démonstration :

```text
Stock cohérent : 1344 mouvements rejoués sur 122 lignes de stock, aucun écart.
```

Requêtes de contrôle utiles (client `mysql` du conteneur `db`, avec `--default-character-set=utf8mb4` pour que les accents s'affichent correctement) :

```sql
-- Répartition des produits actifs : normal, sous le seuil, rupture
SELECT etat, COUNT(*) FROM (
  SELECT p.id, CASE WHEN COALESCE(SUM(l.quantity), 0) = 0 THEN 'rupture'
                    WHEN SUM(l.quantity) <= p.alert_threshold THEN 'sous_seuil'
                    ELSE 'normal' END AS etat
  FROM products p LEFT JOIN stock_levels l ON l.product_id = p.id
  WHERE p.is_active = 1 AND p.deleted_at IS NULL
  GROUP BY p.id, p.alert_threshold) t
GROUP BY etat;

-- Utilisateurs par rôle
SELECT role, COUNT(*), SUM(is_active) FROM users GROUP BY role;

-- Mouvements par nature
SELECT type, direction, COUNT(*), SUM(quantity) FROM stock_movements GROUP BY type, direction;
```

## Durées constatées

Poste Windows 11, Docker Desktop, MySQL 8.4.13 dans le service `db`, le 19/11/2026.

| Opération | Durée |
| :-- | --: |
| `migrate` sur base vierge | 60 à 68 s |
| `migrate:rollback` complet | 10 s |
| `db:seed --class=ReferenceDataSeeder` | 2 à 4 s |
| `migrate:fresh --seed --seeder=DemoSeeder` | 102 s puis 111 s, dont 51 s et 67 s pour le seeder |
| `stock:verify` sur la démonstration | 4 s |

Les durées varient beaucoup avec la charge du poste : des chargements de la démonstration de plus de 10 minutes ont été observés auparavant sous Windows. Lancer cette commande en arrière-plan ou avec un délai d'attente large. Le service `db-test`, quand il tourne, ralentit l'ensemble.

## Compatibilité MySQL

- Version : MySQL 8.4 LTS (D-002). Les contraintes `CHECK` exigent MySQL 8.0.16 ou plus ; MariaDB n'est pas prise en charge.
- Modes SQL : Laravel ouvre chaque connexion avec `strict => true`, soit `ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES, NO_ZERO_IN_DATE, NO_ZERO_DATE, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION` (relevé sur la session de l'application). Le serveur a les mêmes modes par défaut.
- Le mode strict est indispensable. Essai fait sur `stock_levels.quantity` (colonne non signée) : en mode strict, une valeur négative est rejetée (erreur 1264, « Out of range ») ; sans mode strict, MySQL la ramène à 0 avec un simple avertissement et la contrainte `CHECK` ne se déclenche pas, puisqu'elle voit déjà 0. Ne jamais passer `strict` à `false` dans `config/database.php`.
- Les autres contraintes ont été provoquées une à une dans une transaction annulée : `chk_transfer_warehouses`, `chk_poi_received`, `chk_sale_items_quantity` et `chk_stock_movements_quantity` répondent par l'erreur 3819 (« Check constraint ... is violated »). Toutes sont à l'état `ENFORCED`.
- Jeu de caractères : serveur, base, tables, colonnes et connexion en `utf8mb4` / `utf8mb4_unicode_ci`, sans exception.
- Fonctions de date : `DATE_FORMAT`, `DATE_SUB`, `DATE` et `WEEKDAY` (utilisées par `App\Support\Period::bucketExpression()` pour les graphiques du tableau de bord) sont natives de MySQL. Les requêtes du tableau de bord ont été exécutées sur la démonstration, par jour, par semaine et par mois, sans erreur `ONLY_FULL_GROUP_BY`. Elles ne fonctionneraient pas sous SQLite, qui n'a ni `DATE_FORMAT` ni `WEEKDAY`.
- Fuseau : le serveur tourne en GMT (`time_zone = SYSTEM`) et l'application en `Africa/Abidjan`, qui est aussi GMT sans heure d'été. Les dates sont écrites par PHP, pas par `NOW()`.

## Dépannage

| Symptôme | Cause et remède |
| :-- | :-- |
| `Access denied for user 'stockflow'` alors que la base de développement répond | Une commande lancée avec `-e APP_ENV=production` charge `.env.production` au lieu de `.env`, avec d'autres identifiants. C'est voulu : ne pas tester la production sur la base de développement |
| La commande écrit dans `stockflow` malgré `-e DB_DATABASE=...` | Configuration en cache : `php artisan config:clear`, puis vérifier par `select database()` |
| `db:show` liste des tables sur une base vide | Il liste tous les schémas accessibles ; regarder la ligne « Database » |
| Accents remplacés par des `?` ou des losanges dans le client `mysql` | Affichage seulement : ajouter `--default-character-set=utf8mb4` au client |
| `SQLSTATE[HY000] [2002]` ou base injoignable après un redémarrage du poste | MySQL met plusieurs minutes à démarrer : attendre l'état « healthy » de `db` dans `docker compose ps` |
| Tables supprimées pendant une suite de tests | Deux suites lancées en même temps sur `db-test` : n'en lancer qu'une à la fois |
| `stock:verify` signale des écarts | Une quantité a été modifiée hors de `StockService` (requête manuelle, import). Ne pas retoucher l'historique : corriger par un ajustement manuel justifié, ou recharger la base si c'est une base de démonstration |
| Le compte administrateur n'est pas créé | Un administrateur existe déjà : le seeder ne le recrée pas et ne change pas son mot de passe |
| Le mot de passe fixé par `ADMIN_PASSWORD` ne marche pas | La variable n'a pas atteint le conteneur : la mettre dans `.env` ou passer `-e ADMIN_PASSWORD=...` à `docker compose exec` |
