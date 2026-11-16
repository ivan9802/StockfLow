# StockFlow : instructions permanentes du projet

Application web de gestion de stock pour une PME ivoirienne de distribution de matériel informatique et bureautique (un entrepôt central, un point de vente, environ 800 références). Projet à vocation académique et professionnelle : il doit être démontrable en soutenance et déployable.

Le cahier des charges d'origine est `cahier_des_charges_stockflow.md`. La version analysée et complétée est `docs/01-analysis/requirements.md`. En cas de contradiction, c'est `requirements.md` puis `DECISIONS.md` qui font foi.

## Stack

- PHP et Laravel dans les versions tranchées par `DECISIONS.md` (D-001), architecture MVC avec couche de services métier.
- Blade, Bootstrap 5, JavaScript natif, Fetch API. Pas de framework JS, pas de jQuery, pas de Tailwind.
- MySQL 8.4 LTS (InnoDB, D-002). Les tests de concurrence tournent sur MySQL, jamais sur SQLite.
- PHPUnit pour les tests, Laravel Pint pour le style.
- Dépendances ajoutées et justifiées : `barryvdh/laravel-dompdf` (rapports PDF), `picqer/php-barcode-generator` (codes-barres en SVG). Toute autre dépendance passe par une entrée dans `DECISIONS.md` avant installation.

## Règles métier qui ne se négocient pas

1. Le stock est tenu par produit et par emplacement dans `stock_levels`. Il n'existe pas de colonne `current_stock` sur `products`.
2. Aucune quantité ne peut devenir négative. Une vente, une sortie ou un transfert dont la quantité dépasse le disponible est refusé en bloc.
3. Toute variation de `stock_levels.quantity` passe par `App\Services\StockService` et produit exactement une ligne dans `stock_movements`, avec stock avant et stock après. Aucun contrôleur, modèle, seeder de démonstration ou commande ne modifie une quantité autrement.
4. Chaque opération de stock s'exécute dans `DB::transaction()` avec `lockForUpdate()` sur les lignes `stock_levels` concernées. Quand plusieurs lignes sont verrouillées, elles le sont toujours dans l'ordre croissant de `(product_id, warehouse_id)` pour éviter les interblocages.
5. L'audit d'une opération de stock s'écrit dans la même transaction que l'opération (D-019). `stock_movements` et `audit_logs` sont en ajout seul : ni modification ni suppression. Une erreur se corrige par un nouveau mouvement, jamais par une retouche de l'historique.
6. Un document validé (vente, réception, transfert, inventaire) ne se supprime pas. Une vente ou un transfert s'annule, et l'annulation génère les mouvements inverses. Une réception ou un inventaire validé ne s'annule pas : l'erreur se corrige par un ajustement manuel justifié (D-018).
7. Les justifications sont obligatoires pour les sorties internes, les ajustements manuels, les annulations et les écarts d'inventaire.
8. Les montants sont en francs CFA, stockés en entiers (le XOF n'a pas de centimes).

## Conventions de code

- Code, noms de tables, de colonnes, de classes et de routes en anglais. Interface, messages de validation et documentation en français.
- Contrôleurs minces : validation dans un Form Request, autorisation par Policy ou Gate, logique dans un service, réponse.
- Les rôles sont un enum PHP (`App\Enums\Role`) : `admin`, `stock_manager`, `sales_agent`. Les autorisations se vérifient côté serveur à chaque requête, y compris sur les routes appelées en Fetch. Masquer un bouton dans une vue ne constitue pas un contrôle d'accès.
- Requêtes via Eloquent ou le Query Builder avec paramètres liés. Pas de SQL concaténé.
- Affichage : `{{ }}` partout dans Blade. `{!! !!}` est interdit sauf sur du contenu généré par le serveur et documenté (SVG de code-barres).
- Formats : montants `1 200 000 F CFA`, dates `JJ/MM/AAAA`, fuseau `Africa/Abidjan`, locale `fr`.
- Fichiers téléversés : images uniquement (jpeg, png, webp), 2 Mo au plus, nom régénéré, contenu contrôlé, stockage sur le disque privé `local`, servies par une route réservée aux utilisateurs connectés.
- Pas de secret dans Git. `.env.example` documente chaque variable.

## Façon de travailler

- Lire `AGENTS.md` avant toute intervention : il décrit les rôles, l'ordre des interventions et la revue croisée.
- `TASKS.md` tient le backlog. Une tâche ne passe à « Terminé » que si ses critères de validation ont été vérifiés par une commande réellement exécutée (tests, artisan, requête HTTP).
- Une décision qui touche le périmètre, la stack ou une règle métier est soumise à l'utilisateur, puis consignée dans `DECISIONS.md`. Le détail technique va dans `docs/02-architecture/architecture-decisions.md`.
- Une branche par phase, fusionnée dans `main` une fois la phase validée. Commits courts, en français, un sujet par commit.
- Ne jamais annoncer un test passé sans l'avoir lancé. Ne jamais masquer une erreur. Signaler ce qui n'est pas terminé.
- Texte français : pas de tiret cadratin, pas d'emoji, titres avec une seule majuscule initiale, ton sobre.

## Commandes utiles

Tout s'exécute dans le conteneur `app` : préfixer par `docker compose exec -T app` (sous Git Bash, exporter `MSYS_NO_PATHCONV=1` pour que les chemins ne soient pas convertis).

- Démarrer : `docker compose up -d` (application sur http://localhost:8080, MySQL sur le port 3307).
- Tests : démarrer d'abord la base de test, `docker compose --profile test up -d db-test` (plusieurs minutes après un redémarrage du poste), puis `php artisan test` (Unit et Feature, sur `db-test` en mémoire vive). L'arrêter ensuite avec `docker compose stop db-test` : elle ralentit fortement la machine. Concurrence : `php artisan test --testsuite=Concurrency`, quelques minutes.
- Cohérence du stock : `php artisan stock:verify` (doit répondre « aucun écart »).
- Base de démonstration : `php artisan migrate:fresh --seed --seeder=DemoSeeder` (plus de 10 minutes sous Windows).
- Style : `vendor/bin/pint` avant chaque commit.
- Assets : `npm run build` (2 à 3 minutes sous Windows). Une vue qui appelle `@vite` sur une nouvelle entrée exige de l'ajouter dans `vite.config.js`.

Pièges connus :
- Ne jamais lancer deux suites de tests en même temps : elles partagent la base `db-test`, et le `migrate:fresh` de `RefreshDatabase` supprime les tables sous les processus de l'autre suite (constaté le 14/11/2026 sur les tests de concurrence).
- Les tests à processus réels utilisent `DatabaseTruncation`, pas `RefreshDatabase` : une transaction de test cacherait les données aux autres processus.
- Le nom `store()` est réservé par la classe `Factory` de Laravel : l'état « point de vente » de `WarehouseFactory` s'appelle `pointOfSale()`.
- Une réponse en flux (export CSV) n'a pas de méthode `status()` dans les tests : utiliser `getStatusCode()`.
