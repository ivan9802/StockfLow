# Intégration continue

Le workflow `.github/workflows/ci.yml` s'exécute sur GitHub Actions à chaque push, sur toutes les branches, et à chaque pull request. Une nouvelle exécution sur la même branche annule la précédente.

## Ce qui est vérifié

Job `tests` (Ubuntu 24.04, 40 minutes au plus) :

| Étape | Commande | Échoue si |
| :-- | :-- | :-- |
| Validation de Composer | `composer validate --no-check-publish` | `composer.json` invalide ou désaccordé de `composer.lock` |
| Dépendances PHP | `composer install` | Résolution impossible |
| Configuration | `cp .env.example .env` puis `php artisan key:generate --force` | |
| Style | `vendor/bin/pint --test` | Un fichier PHP n'est pas au format Pint |
| Assets | `npm ci` puis `npm run build` | Erreur de compilation Vite ou Sass |
| Migrations | `php artisan migrate --force` | Migration incompatible avec MySQL 8.4 |
| Tests | `php artisan test --testsuite=Unit,Feature` | Un test échoue |
| Concurrence | `php artisan test --testsuite=Concurrency` | Une vente simultanée produit un stock négatif ou incohérent |

Les deux suites s'enchaînent dans le même job, jamais en parallèle : elles partagent la même base.

Job `audit` (indépendant, pour qu'une alerte de sécurité n'empêche pas de voir le résultat des tests) :

| Étape | Commande |
| :-- | :-- |
| Dépendances PHP | `composer audit --locked` |
| Dépendances JavaScript livrées | `npm audit --omit=dev` |

Ce job échoue dès qu'une vulnérabilité connue touche une dépendance. La correction passe par une mise à jour de la dépendance (`composer update <paquet>` ou `npm update <paquet>`), consignée dans `CHANGELOG.md`.

## Base de données des tests

Les tests tournent sur un vrai MySQL 8.4, service `mysql` du job, publié sur `127.0.0.1:3306` du runner. Base `stockflow_test`, utilisateur `stockflow`, mot de passe `secret` : ces valeurs n'existent que dans le runner jetable.

`phpunit.xml` fixe `DB_HOST=db-test`, nom du service de test de `compose.yaml`. En CI, ce nom n'existe pas. `phpunit.xml` n'a pas besoin d'être modifié, pour deux raisons vérifiées dans le code des dépendances installées :

1. Les balises `<env>` de PHPUnit n'écrasent une variable que si elle est absente de l'environnement, sauf attribut `force="true"` (`vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php`, condition `$force || getenv($name) === false`). `phpunit.xml` n'utilise pas `force`.
2. `php artisan test` retire de l'environnement les variables lues dans `.env` avant de lancer PHPUnit, mais pas celles définies hors de `.env` : le dépôt de variables de Laravel est immuable et ne supprime jamais une variable « externe » (`vendor/vlucas/phpdotenv/src/Repository/Adapter/ImmutableWriter.php`, méthode `delete`).

Le job définit donc `DB_CONNECTION`, `DB_HOST=127.0.0.1`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` et `DB_PASSWORD` au niveau du job : elles l'emportent sur `phpunit.xml` et sur `.env`. Les processus lancés par les tests de concurrence héritent de ces variables (ils reçoivent `DB_HOST` et `DB_DATABASE` explicitement, le reste par héritage de l'environnement).

En local, rien ne change : sans variable `DB_HOST` dans l'environnement du conteneur, `phpunit.xml` impose `db-test`.

Le service MySQL de GitHub Actions ne permet pas de passer des options de démarrage au serveur. Le jeu de caractères et la collation viennent donc de la configuration Laravel (`utf8mb4`, `utf8mb4_unicode_ci`, `config/database.php`), qui les applique à chaque table créée.

## Vérification réalisée

- Syntaxe YAML validée localement avec la bibliothèque `yaml` de Node : document valide, déclencheurs `push` et `pull_request`, deux jobs, chaque étape a `uses` ou `run`. PyYAML et `actionlint` ne sont pas installés sur le poste.
- Le workflow n'a jamais tourné sur GitHub : la première exécution aura lieu au premier push de la branche qui le contient. À surveiller en particulier : `vendor/bin/pint --test` (des fichiers modifiés depuis le dernier passage de Pint feraient échouer l'étape) et la durée de la suite de concurrence.

## Lancer les mêmes contrôles en local

Dans le conteneur `app` (préfixe `docker compose exec -T app`), base de test démarrée :

```bash
composer validate --no-check-publish
vendor/bin/pint --test
npm run build
php artisan test --testsuite=Unit,Feature
php artisan test --testsuite=Concurrency
composer audit --locked
npm audit --omit=dev
```

Ne lancez jamais deux suites de tests en même temps sur `db-test`.
