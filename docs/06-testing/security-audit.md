# Audit de sécurité de StockFlow

Tâche T-605. Audit en lecture seule du code applicatif de la branche `phase-3/initialisation` (dernier commit `a0859fe`), mené le 14/11/2026. Aucun fichier de code n'a été modifié, aucune commande n'a été exécutée contre l'application : les constats viennent de la lecture des sources, et les points qui demandent une exécution sont listés à la fin.

Périmètre lu : `routes/`, `bootstrap/app.php`, tout `app/` (contrôleurs, Form Requests, middlewares, services, modèles, policy, exports, notifications), les vues Blade, `resources/js/`, `config/session.php`, `config/auth.php`, `config/stockflow.php`, `.env.example`, `.gitignore`, `.dockerignore`, `docker/`, `compose.yaml`, `compose.prod.yaml`, `scripts/`, `database/seeders/`, `database/migrations/` (types de colonnes).

## Synthèse

L'application est dans un bon état général. Les fondamentaux sont tenus et vérifiables dans le code : autorisation côté serveur sur chaque route métier par `can:` adossé à la matrice de `App\Enums\Role`, contrôle fin par `SalePolicy` et par le filtrage des requêtes (un vendeur ne voit ni ne vend hors de son périmètre), prix et totaux toujours relus en base dans la transaction, quantités strictement positives contrôlées à la fois par les Form Requests et par `StockService`, aucune affectation de masse exploitable, CSRF sur tous les formulaires et sur les appels Fetch, échappement systématique dans Blade et en JavaScript, aucune requête SQL construite avec une donnée de l'utilisateur, exports CSV protégés contre l'injection de formules, image de production sans `.env`. Aucune non-conformité critique n'a été relevée.

Deux non-conformités majeures sont à corriger avant la mise en production. La première (SEC-01) tient au déploiement derrière un proxy inverse : Laravel ne fait confiance à aucun proxy, si bien que toutes les requêtes semblent venir de la même adresse IP. Conséquences : la limitation de débit de la connexion devient collective (un tiers peut empêcher tout le personnel de se connecter avec vingt requêtes par minute), le blocage par couple e-mail et IP permet de verrouiller un compte précis, les adresses IP du journal d'audit sont inexploitables, et l'en-tête HSTS n'est jamais envoyé. La seconde (SEC-02) concerne l'option « Rester connecté », proposée sur l'écran de connexion alors que l'architecture l'exclut parce que les postes de la boutique sont partagés : un cookie de longue durée y garde la session d'un vendeur pour le suivant, et un changement de mot de passe ne ferme pas les autres sessions.

Les douze non-conformités mineures relèvent de la défense en profondeur et de l'écart entre la documentation et le code : blocage de connexion non journalisé, longueur maximale de mot de passe supérieure à la limite de Bcrypt, image non réencodée, historique protégé seulement au niveau Eloquent, variables d'environnement non documentées, ports de développement exposés, sauvegardes lisibles, page légale à compléter. Priorités : SEC-01 et SEC-02 d'abord (effort petit pour chacune), puis SEC-03, SEC-04, SEC-06 et SEC-09, qui se corrigent en quelques lignes.

## Points de contrôle

| N° | Point de contrôle | Statut | Preuve |
| :-- | :-- | :-- | :-- |
| 1 | Authentification | Partiel | Conforme : hachage par le cast `hashed` (`app/Models/User.php:28`), régénération de session (`app/Http/Controllers/Auth/LoginController.php:61`), invalidation à la déconnexion (`LoginController.php:77-79`), limitation à 5 échecs par couple e-mail et IP (`LoginController.php:32`, `app/Http/Requests/Auth/LoginRequest.php:25-28`) plus `throttle:20,1` (`routes/web.php:20`), compte désactivé refusé à la connexion (`LoginController.php:40`) et à chaque requête, JSON compris (`app/Http/Middleware/EnsureUserIsActive.php:21-33`), changement de mot de passe forcé (`app/Http/Middleware/EnsurePasswordIsChanged.php:19-25`), message d'échec identique (`LoginController.php:51-55`). Écarts : SEC-01, SEC-02, SEC-03, SEC-04. |
| 2 | Autorisations | Partiel | Chaque route de `routes/modules.php` porte le droit attendu par `app/Enums/Role.php:31-67`, sauf `/internal/dashboard` (`routes/modules.php:149`, SEC-05, sans fuite constatée). IDOR vérifiés et fermés : vente d'un autre vendeur (`app/Policies/SalePolicy.php:11-14`, `app/Http/Controllers/SaleController.php:88,110,118`), vente depuis un autre emplacement (`SaleController.php:136,146`), validation d'inventaire par un gestionnaire (`routes/modules.php:77-80`, droit `inventories.validate` réservé à l'administrateur, `Role.php:62`), lignes d'inventaire ou de commande d'un autre document (`app/Services/InventoryService.php:97`, `app/Services/PurchaseService.php:177`), retrait de ses propres droits par l'administrateur (`app/Http/Controllers/UserController.php:71`). |
| 3 | Affectation de masse | Conforme | Aucun `create()`/`update()` ne reçoit la requête brute : champs listés explicitement (`UserController.php:45-54`, `75-81`), `validated()` de Form Requests sans champ sensible (`CategoryController.php:28`, `SupplierController.php:103,115`), `role` et `is_active` réservés à l'administrateur derrière `users.manage`, `must_change_password` jamais lu dans la requête, `user_id`, `status`, `total_amount`, `unit_price` fixés par les services (`app/Services/SalesService.php:61,72`). `preventSilentlyDiscardingAttributes` actif hors production (`app/Providers/AppServiceProvider.php:56`). |
| 4 | Intégrité métier | Partiel | Prix relus en base (`SalesService.php:61`), coût d'une réception sur commande repris de la commande (`PurchaseService.php:213`), quantités `min:1` (`app/Http/Requests/LinesRequest.php:26`, `MovementRequest.php:22`) et contrôle `quantity <= 0` dans `StockService::move()` (`app/Services/StockService.php:168`), aucune écriture de `stock_levels.quantity` hors de `StockService` (recherche sur `app/` et `database/seeders/`). Écarts : SEC-08 (historique protégé au seul niveau Eloquent), SEC-09 (mouvement manuel sur un produit retiré). |
| 5 | CSRF | Conforme | Middleware CSRF du groupe `web` sans exclusion (`bootstrap/app.php:18-28`), `@csrf` dans les 23 vues contenant un formulaire POST, jeton `X-CSRF-TOKEN` sur chaque appel Fetch (`resources/js/lib/http.js:22`), méta `csrf-token` dans les deux gabarits (`resources/views/layouts/app.blade.php:6`, `guest.blade.php:6`). |
| 6 | XSS | Conforme | Un seul `{!! !!}` (`resources/views/products/show.blade.php:96`), sur un SVG produit par `app/Services/BarcodeGenerator.php:50-56` à partir d'un code validé `^[0-9]{8,13}$` (`ProductRequest.php` règle `barcode`). Chaque `innerHTML` JavaScript passe par `escapeHtml()` (`http.js:53-57`) : `sale-cart.js:21-27,36-58`, `product-search.js:31,42-56`, `line-items.js:56,115` ; `live-list.js:41` insère un fragment Blade déjà échappé. Notifications (`resources/views/alerts/index.blade.php:122`) et journal d'audit (`resources/views/audit-logs/_table.blade.php:24,33-34`) en `{{ }}`. Fenêtre de confirmation en `textContent` (`resources/js/app.js:80-82`). |
| 7 | Injection SQL | Conforme | Tous les `whereRaw`, `selectRaw`, `DB::raw` sont des chaînes constantes (`app/Models/Product.php` scope `stockStatus`, `app/Services/DashboardService.php:28-35,55,70`, `app/Services/AlertService.php:65`, `InventoryService.php:142`, `app/Console/Commands/VerifyStockIntegrity.php:33-38`). Le seul `DB::statement` est paramétré (`app/Services/ReferenceGenerator.php:37-41`). Le tri par colonne de l'URL passe par une liste blanche (`app/Http/Controllers/ProductController.php:21,33,45`). Les `like` échappent `%` et `_`. |
| 8 | Téléversement d'images | Partiel | Conforme : types `image` + `mimes:jpeg,png,webp` + décodage `getimagesize` (`app/Http/Requests/ProductRequest.php:33-41`), 2 Mo (`config/stockflow.php:30`), nom régénéré par `store()` (`app/Services/ProductService.php:149`), disque privé `local` (`ProductService.php:17`), service authentifié avec `nosniff` et cache privé (`ProductController.php:108-117`), PHP non exécutable hors `index.php` (`docker/nginx/default.conf:24-34`). Écart : SEC-07 (pas de réencodage). Le disque privé est plus strict que la règle « disque public » de `CLAUDE.md` : c'est la documentation qui est à mettre à jour. |
| 9 | Exports CSV et PDF | Conforme | Neutralisation de `=`, `+`, `-`, `@`, tabulation et retour chariot (`app/Exports/CsvWriter.php:42`), écriture en flux et lecture par lots (`CsvWriter.php:21`, `app/Http/Controllers/ReportController.php:45,85,121`), PDF plafonné à `pdf_max_rows` = 2000 (`ReportController.php:59,99`), exports réservés à `reports.view` (gestionnaire et administrateur) et tracés (`ReportController.php:36,80,118`). Le prix d'achat n'apparaît que dans ces exports réservés. Charge des PDF non limitée en fréquence : voir SEC-10. |
| 10 | En-têtes, CSP, cookies, HSTS | Non conforme | CSP, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` posés sur toutes les réponses web (`app/Http/Middleware/SecurityHeaders.php:38-55`, `bootstrap/app.php:19`). Cookies `HttpOnly` et `SameSite=lax` (`config/session.php:185,202`). Écarts : HSTS jamais envoyé derrière le proxy (`SecurityHeaders.php:57-59`, SEC-01), attribut `Secure` sans valeur par défaut en production (`config/session.php:172`, SEC-06). |
| 11 | Secrets | Partiel | `.env` ignoré par Git (`.gitignore:3`) et par Docker (`.dockerignore:2-4`), supprimé de l'image (`docker/php/Dockerfile:62`), `.env.example` sans valeur de production, mot de passe de démonstration refusé en production (`database/seeders/DemoSeeder.php:55-57`), administrateur initial avec mot de passe aléatoire et changement forcé (`database/seeders/ReferenceDataSeeder.php:42,50`). Écarts : SEC-11, SEC-12, SEC-13. |
| 12 | Déni de service applicatif | Partiel | Conforme : `throttle:20,1` sur la connexion, `throttle:10,1` sur le mot de passe (`routes/web.php:20,31`), `throttle:120,1` sur les points JSON (`routes/modules.php:147`), 100 lignes au plus par document (`LinesRequest.php:14,24`), 2000 comptages par envoi (`InventoryController.php:113`), 8 résultats de recherche (`ProductSearchController.php:17`), pagination sur toutes les listes. Écarts : limitation collective derrière le proxy (SEC-01), tableau `quantities` sans borne et exports sans limitation de fréquence (SEC-10). |
| 13 | Journalisation | Partiel | Tracés : connexion, échec, déconnexion (`LoginController.php:47,65,74`), création et modification d'utilisateur par le trait `Auditable` (`app/Models/User.php:17`), réinitialisation et changement de mot de passe (`UserController.php:95`, `PasswordController.php:42`), annulations, validations, réceptions, transferts, mouvements manuels (services), exports (`ReportController.php`). Secrets exclus (`app/Services/AuditService.php:20,61`, `app/Models/Concerns/Auditable.php:16`), écriture dans la transaction de l'opération. Écarts : blocage de connexion non tracé (SEC-03), adresse IP faussée (SEC-01). |
| 14 | Données personnelles (loi n° 2013-450) | Partiel | Page `/legal` publique, liée depuis les deux gabarits, qui décrit les données, finalités, droits et l'ARTCI (`resources/views/legal.blade.php`). `customer_name` facultatif. Journal d'audit réservé à l'administrateur. Écarts : SEC-14 (durées de conservation non chiffrées, pas de purge, cookie « se souvenir de moi » non déclaré, champs d'identité « à renseigner »). |

## Non-conformités

### Critique

Aucune.

### Majeure

#### SEC-01 : adresse du client et HTTPS ignorés derrière le proxy inverse

Description. La production place un proxy inverse TLS devant le conteneur nginx, qui n'écoute que sur 127.0.0.1 (`compose.prod.yaml:5-6,30`). Ni nginx (`docker/nginx/default.conf:24-28`, pas de `set_real_ip_from`) ni Laravel (`bootstrap/app.php:18-28`, pas de `trustProxies`) ne prennent en compte `X-Forwarded-For` et `X-Forwarded-Proto`. Pour PHP, toutes les requêtes viennent donc de la passerelle Docker, en HTTP. Effets :
- la limitation `throttle:20,1` de `routes/web.php:20`, indexée sur l'IP pour un invité, devient un compteur unique pour toute l'entreprise ;
- la clé `login|e-mail|IP` de `LoginRequest.php:27` se réduit à l'e-mail : cinq échecs suffisent à bloquer un compte donné ;
- `ip_address` du journal d'audit (`AuditService.php`) contient la même adresse pour tout le monde ;
- `$request->isSecure()` est toujours faux : l'en-tête HSTS de `SecurityHeaders.php:57-59` n'est jamais envoyé, et les URL générées par `route()` et `redirect()` peuvent sortir en `http://`.

Scénario d'exploitation. Depuis n'importe quel poste qui atteint la page de connexion, un script envoie 20 POST `/login` par minute avec des identifiants quelconques : chaque membre du personnel reçoit une erreur 429 et personne ne peut ouvrir de session tant que le script tourne. Variante ciblée : cinq essais par minute avec l'adresse de l'administrateur, qui ne peut plus se connecter. Après coup, le journal d'audit ne permet pas d'identifier le poste à l'origine des échecs.

Correction proposée.
1. Dans `bootstrap/app.php`, à l'intérieur de `withMiddleware` :
   ```php
   $middleware->trustProxies(
       at: env('TRUSTED_PROXIES', '127.0.0.1,172.16.0.0/12'),
       headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO
           | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT,
   );
   ```
   et documenter `TRUSTED_PROXIES` dans `.env.example` (sous-réseau du réseau Docker de production, jamais `*` si le conteneur est joignable autrement que par le proxy).
2. Exiger du proxy inverse amont qu'il écrase (et non complète) `X-Forwarded-For` et pose `X-Forwarded-Proto: https`, et le consigner dans la documentation de déploiement.
3. En production, forcer le schéma : `URL::forceScheme('https')` dans `AppServiceProvider::boot()` quand `app()->isProduction()`.
4. Ajouter un test HTTP qui envoie `X-Forwarded-Proto: https` depuis une IP de confiance et vérifie la présence de `Strict-Transport-Security`, et un test qui vérifie que deux IP différentes ont des compteurs de connexion distincts.

Effort : petit.

#### SEC-02 : option « Rester connecté » sur des postes partagés

Description. L'écran de connexion propose une case « Rester connecté sur cet appareil » (`resources/views/auth/login.blade.php:31`), transmise à `Auth::attempt($credentials, $request->boolean('remember'))` (`LoginController.php:42`). L'architecture l'exclut explicitement : « L'option se souvenir de moi n'est pas proposée : les postes de la boutique sont partagés » (`docs/02-architecture/system-architecture.md`, section 6). Le cookie `remember_web_*` de Laravel vit environ 400 jours. De plus, un changement de mot de passe par l'utilisateur (`app/Http/Controllers/Auth/PasswordController.php:37-46`) ne renouvelle pas `remember_token` et ne ferme pas les autres sessions : seule la réinitialisation par l'administrateur le fait (`UserController.php:92-94`).

Scénario d'exploitation. Une vendeuse coche la case sur le poste de caisse, ferme le navigateur sans se déconnecter et quitte son service. Le collègue suivant ouvre l'application : il est connecté sous son nom, saisit des ventes qui lui seront imputées dans `stock_movements` et `audit_logs`, et la traçabilité par auteur, qui est l'objet même du journal, est faussée. Si elle soupçonne l'usage de son compte et change son mot de passe, le cookie déjà posé reste valide.

Correction proposée.
1. Retirer la case de `login.blade.php`, la règle `remember` de `LoginRequest.php`, et appeler `Auth::attempt($credentials)` sans second argument.
2. Dans `PasswordController::update()`, après l'enregistrement : renouveler `remember_token` (`'remember_token' => Str::random(60)` dans le `forceFill`) et supprimer les autres sessions du compte : `DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();` (ou `Auth::logoutOtherDevices($validated['password'])` avec le middleware `AuthenticateSession`).
3. Ajouter un test qui vérifie l'absence du cookie `remember_web_*` après connexion, même avec `remember=1` dans la requête.

Effort : petit.

### Mineure

#### SEC-03 : blocage de connexion non journalisé

Description. Quand le seuil de cinq échecs est atteint, `LoginController.php:32-37` lève l'erreur sans écrire dans `audit_logs`, et le refus par `throttle:20,1` (`routes/web.php:20`) s'arrête avant le contrôleur. L'architecture demande que « chaque échec et chaque blocage » soient journalisés.

Scénario. Une attaque par essais répétés sur un compte n'apparaît dans le journal que par ses cinq premiers échecs de chaque minute ; les blocages, qui sont le signal d'alerte, ne laissent aucune trace.

Correction. Dans la branche `tooManyAttempts`, appeler `$this->audit->record(AuditAction::LoginFailed, null, 'Connexion bloquée (trop de tentatives) pour '.$request->string('email'));` (ou une nouvelle valeur `LoginLocked` de `AuditAction`). Pour le refus du middleware `throttle`, écouter l'événement `Illuminate\Auth\Events\Lockout` ou définir un limiteur nommé avec une réponse personnalisée qui trace.

Effort : petit.

#### SEC-04 : longueur maximale de mot de passe au-delà de la limite de Bcrypt

Description. Les règles acceptent jusqu'à 200 caractères (`PasswordController.php:27`, `UserController.php:109`), alors que l'architecture fixe le plafond à 72, limite au-delà de laquelle Bcrypt ignore la suite de la saisie. Le dépôt ne publie pas `config/hashing.php` : rien ne garantit que le framework refuse un mot de passe plus long.

Scénario. Un utilisateur choisit une phrase de passe de 90 caractères dont seuls les 72 premiers comptent ; deux phrases qui ne diffèrent qu'après le 72e octet ouvrent la même session. L'effet est surtout une fausse impression de robustesse.

Correction. Remplacer `max:200` par `max:72` dans les deux règles, ou mieux, centraliser dans `Password::defaults(fn () => Password::min(12)->max(72))` (`AppServiceProvider.php`). Vérifier aussi la longueur en octets pour les caractères accentués (règle personnalisée `strlen($value) <= 72`).

Effort : petit.

#### SEC-05 : point JSON du tableau de bord sans droit `can:`

Description. `Route::get('/dashboard', DashboardDataController::class)` (`routes/modules.php:149`) est la seule route métier sans middleware `can:`, contrairement à la règle énoncée en tête de `routes/web.php` et de `routes/modules.php`. Le contrôle est fait à l'intérieur : `DashboardService::kpis()` filtre par profil (`DashboardService.php:41,51`) et le compteur d'alertes est conditionné à `alerts.view` (`DashboardDataController.php:25`). Aucune fuite n'est constatée aujourd'hui.

Scénario. Aucun dans l'état actuel. Le risque est qu'un indicateur ajouté plus tard dans le contrôleur, hors du bloc `dashboard.full`, soit servi au vendeur sans que la route ne le signale.

Correction. Déclarer un droit explicite, par exemple `'dashboard.view' => $all` dans `Role::permissions()`, et l'appliquer : `->middleware('can:dashboard.view')`. Ajouter la route au test `tests/Feature/Security/PermissionMatrixTest.php`.

Effort : petit.

#### SEC-06 : cookie de session sans `Secure` par défaut, sessions en clair avec mots de passe provisoires

Description. `'secure' => env('SESSION_SECURE_COOKIE')` (`config/session.php:172`) dépend entièrement du fichier `.env.production`, absent du dépôt ; `.env.example:46` le met à `false`. Par ailleurs `SESSION_ENCRYPT=false` (`config/session.php:50`) alors que les mots de passe provisoires générés par l'administrateur transitent par la session flash (`UserController.php:56-58,98`) et sont donc écrits en clair dans la table `sessions` jusqu'au chargement de la page suivante.

Scénario. Un oubli de `SESSION_SECURE_COOKIE=true` à l'installation laisse partir le cookie de session sur une éventuelle requête HTTP (lien en `http://`, redirection, SEC-01). Une copie de la base (sauvegarde, SEC-13) prise au mauvais moment contient un mot de passe provisoire lisible.

Correction. `'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')` ; `SESSION_ENCRYPT=true` dans `.env.example` et en production (le coût est négligeable avec le pilote `database`).

Effort : petit.

#### SEC-07 : image téléversée enregistrée sans réencodage

Description. L'architecture prévoit que l'image soit « réencodée et réduite avec GD avant enregistrement, ce qui supprime les métadonnées et tout contenu étranger au format ». `ProductService::storeImage()` (`ProductService.php:149`) enregistre le fichier tel quel après les contrôles de `ProductRequest.php:33-41`.

Scénario. Une photo prise au téléphone conserve ses métadonnées EXIF (coordonnées GPS, modèle d'appareil). Un fichier polyglotte (JPEG valide contenant du HTML ou du JavaScript) passe les contrôles ; il reste inoffensif tant qu'il est servi avec `image/*` et `nosniff` (`ProductController.php:113-116`), mais la seule barrière est l'en-tête.

Correction. Dans `storeImage()`, décoder avec `imagecreatefromstring(file_get_contents($image->getRealPath()))`, réduire à une taille maximale (par exemple 1200 px de côté avec `imagescale`), réencoder en WebP ou JPEG (`imagewebp` / `imagejpeg`) dans un fichier temporaire, puis `Storage::disk(self::IMAGE_DISK)->putFileAs(self::IMAGE_DIRECTORY, $tmp, Str::random(40).'.webp')`. Ajouter un test qui vérifie l'absence de bloc EXIF dans le fichier stocké.

Effort : moyen.

#### SEC-08 : historique en ajout seul protégé uniquement au niveau Eloquent

Description. Le trait `AppendOnly` (`app/Models/Concerns/AppendOnly.php:13-21`) bloque `updating` et `deleting` sur les modèles `StockMovement` et `AuditLog`. Ces événements ne sont pas déclenchés par le Query Builder (`StockMovement::query()->update()`, `DB::table('audit_logs')->delete()`), et le compte MySQL de l'application (`MYSQL_USER`, `compose.prod.yaml:44-45`) a tous les droits sur sa base. Aucune ligne de code actuelle ne fait ce contournement.

Scénario. Une commande de maintenance ou une future fonctionnalité écrite avec le Query Builder modifie ou purge des lignes d'historique sans erreur. Un accès SQL avec le compte applicatif (fuite de `.env`) permet d'effacer les traces d'une sortie frauduleuse.

Correction. Ajouter une migration qui crée des déclencheurs MySQL `BEFORE UPDATE` et `BEFORE DELETE` sur `stock_movements` et `audit_logs`, avec `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historique en ajout seul'`. À défaut, retirer au compte applicatif les droits `UPDATE` et `DELETE` sur ces deux tables (`REVOKE`), dans un script d'initialisation de la base de production. Couvrir par un test qui tente un `DB::table('stock_movements')->update(...)` et attend une exception.

Effort : moyen.

#### SEC-09 : mouvement manuel accepté sur un produit retiré ou un emplacement inactif

Description. `MovementRequest.php:20-21` valide `exists:products,id` et `exists:warehouses,id`, qui acceptent un produit supprimé logiquement et un emplacement désactivé. `MovementController.php:63-64` passe ensuite directement à `StockService`, sans le contrôle `activeProducts()` utilisé par les ventes, transferts et réceptions.

Scénario. Un gestionnaire (ou une requête forgée avec son compte) saisit une entrée par ajustement sur un produit retiré du catalogue : la quantité apparaît dans `stock_levels` d'un produit invisible à l'écran d'état des stocks, ce qui fausse les inventaires et la valeur du stock, et permet de contourner la règle de `ProductService::delete()` qui interdit de retirer un produit encore en stock.

Correction. `Rule::exists('products', 'id')->whereNull('deleted_at')` et `Rule::exists('warehouses', 'id')->where('is_active', true)` dans `MovementRequest`. Pour une entrée, refuser aussi un produit inactif si la règle métier le prévoit. Même correction sur `supplier_id` de `PurchaseOrderRequest` et `DirectReceiptRequest`.

Effort : petit.

#### SEC-10 : tableau de réception sans borne et exports sans limitation de fréquence

Description. `GoodsReceiptController::storeFromOrder()` valide `'quantities' => ['required', 'array']` sans taille maximale (`GoodsReceiptController.php:79`). Les routes `reports.*` (`routes/modules.php:125-130`) n'ont pas de `throttle`, alors qu'un PDF de 2000 lignes avec DomPDF mobilise un processus PHP-FPM plusieurs secondes.

Scénario. Avec un compte gestionnaire, une boucle de requêtes sur `/reports/stock.pdf` occupe tous les processus PHP-FPM et ralentit le point de vente. Une réception envoyée avec des centaines de milliers de clés `quantities` consomme mémoire et temps de validation. L'auteur doit disposer d'un compte gestionnaire, d'où la gravité mineure.

Correction. `'quantities' => ['required', 'array', 'max:'.LinesRequest::MAX_LINES]` (rendre la constante publique) ; `->middleware('throttle:10,1')` sur le groupe `reports.*` (ou un limiteur nommé `exports` par utilisateur).

Effort : petit.

#### SEC-11 : ports de développement publiés sur toutes les interfaces avec des mots de passe connus

Description. `compose.yaml:52` publie MySQL (`3307`) sur toutes les interfaces, avec les valeurs par défaut `secret` et `root-secret` (`.env.example:31,33`, `compose.yaml` services `db` et `db-test`). L'application (`compose.yaml:28`, `APP_DEBUG=true`) et Vite (`compose.yaml:18`) le sont aussi.

Scénario. Un poste de développement connecté à un réseau partagé (Wi-Fi public, réseau d'école lors de la soutenance) expose une base MySQL accessible en `root` avec un mot de passe publié dans le dépôt, et une application en mode débogage.

Correction. Préfixer les trois publications par `127.0.0.1:` (par exemple `"127.0.0.1:${FORWARD_DB_PORT:-3307}:3306"`), et le noter dans le README.

Effort : petit.

#### SEC-12 : variables d'environnement lues par le code et absentes de `.env.example`

Description. `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` (`database/seeders/ReferenceDataSeeder.php:42-46`) et `COMPANY_NAME`, `COMPANY_ADDRESS`, `COMPANY_PHONE`, `COMPANY_EMAIL`, `COMPANY_RCCM`, `COMPANY_PUBLISHER`, `COMPANY_HOST` (`config/stockflow.php:10-16`) ne figurent pas dans `.env.example`, alors que `CLAUDE.md` exige que chaque variable y soit documentée.

Scénario. L'installateur ignore qu'il peut fixer le mot de passe initial de l'administrateur hors de la sortie console, et la page légale part en production avec « à renseigner » (voir SEC-14).

Correction. Ajouter ces variables, vides ou avec une valeur d'exemple non sensible, et un commentaire : `ADMIN_PASSWORD=` « laisser vide pour un mot de passe tiré au hasard, affiché une fois ; 12 caractères au moins sinon ». Ajouter aussi `TRUSTED_PROXIES` (SEC-01).

Effort : petit.

#### SEC-13 : sauvegardes lisibles par tous et mot de passe root en ligne de commande

Description. `scripts/backup.sh:15-19` crée le dossier et les fichiers avec le masque par défaut (en général lecture pour tous) ; ils contiennent les hachages de mots de passe, les sessions, les noms de clients et le journal d'audit. Le mot de passe root MySQL est passé par `-p"$MYSQL_ROOT_PASSWORD"`, visible dans la liste des processus du conteneur pendant la sauvegarde.

Scénario. Un autre compte du serveur, ou un service mal cloisonné, lit `backups/stockflow-db_*.sql.gz` et récupère toute la base.

Correction. Ajouter `umask 077` en tête du script ; passer le mot de passe par l'environnement : `sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot ...'` (même chose dans `scripts/restore.sh:21`). Mieux, utiliser un compte MySQL de sauvegarde limité à `SELECT, LOCK TABLES, SHOW VIEW, TRIGGER`.

Effort : petit.

#### SEC-14 : page légale incomplète sur la conservation et les cookies

Description. La page `/legal` indique des durées de conservation non chiffrées (« le temps de la relation de travail ou commerciale, puis selon les durées légales », `resources/views/legal.blade.php:38-39`) et aucune purge n'existe pour les adresses IP et agents utilisateurs du journal d'audit, les adresses e-mail des tentatives échouées, les sessions expirées et les notifications. Elle déclare « deux cookies techniques » (`legal.blade.php:51`), ce qui est faux tant que SEC-02 n'est pas corrigée. Les mentions d'identité valent « à renseigner » par défaut (`config/stockflow.php:14-16`).

Scénario. En cas de contrôle de l'ARTCI ou de demande d'un salarié, l'entreprise ne peut pas justifier de durées de conservation définies pour les traces nominatives d'activité.

Correction. Faire fixer par l'entreprise (responsable du traitement) une durée pour l'IP et l'agent utilisateur du journal (par exemple 12 mois), l'écrire dans la page, et ajouter une commande planifiée qui met à `NULL` `ip_address` et `user_agent` des lignes plus anciennes (sans supprimer la ligne d'audit, qui reste en ajout seul : cette anonymisation doit être explicitement autorisée par une décision dans `DECISIONS.md`, car elle touche une table protégée). Corriger la phrase sur les cookies après SEC-02. Refuser le démarrage en production si `COMPANY_RCCM` vaut « à renseigner », ou au minimum l'afficher dans la liste de contrôle de déploiement.

Effort : moyen.

## Points à vérifier par un test

Ces points ne peuvent pas être tranchés à la lecture : ils dépendent de la version installée du framework, de la configuration de production ou du comportement du navigateur. Le dossier `vendor/` n'est pas présent sur le poste (il vit dans un volume Docker), ce qui a empêché de lire le code du framework.

1. Énumération par le temps de réponse. Vérifier que `SessionGuard::attempt()` de la version installée de Laravel égalise la durée entre adresse inconnue et mot de passe faux (mécanisme `Timebox`). Test : mesurer 50 tentatives de chaque cas ; un écart systématique de l'ordre du coût Bcrypt (environ 200 ms au coût 12) signale une fuite. À défaut, envelopper l'appel dans `Illuminate\Support\Timebox`.
2. Comportement de Bcrypt au-delà de 72 octets (SEC-04) : vérifier dans `vendor/laravel/framework/config/hashing.php` si l'option `limit` existe et sa valeur par défaut, puis tester une connexion avec un mot de passe tronqué.
3. En-têtes réels : `curl -I` sur `/login`, une page authentifiée, une réponse JSON `/internal/products/search`, une erreur 404, une erreur 419, une erreur 500, `/up` et `/products/{id}/image` ; vérifier la présence de la CSP et des autres en-têtes sur chacune.
4. CSP et attributs `style` : la politique `style-src 'self' 'nonce-...'` bloque les attributs `style` en ligne encore présents (`resources/js/modules/line-items.js:71`, `resources/views/components/line-items.blade.php:18,57`, `resources/views/goods-receipts/create.blade.php:12`, `resources/views/inventories/show.blade.php:122`, `resources/views/purchase-orders/form.blade.php:16`, `resources/views/purchase-orders/_table.blade.php:31`, `resources/views/transfers/create.blade.php:8`, `resources/views/goods-receipts/from-order.blade.php:13`). Ouvrir ces pages avec la console du navigateur : chaque violation signalée est un défaut de mise en page (remplacer par des classes CSS), pas une faille, mais il ne faut pas le corriger en ajoutant `'unsafe-inline'`.
5. Cookies en production : après correction de SEC-01 et SEC-06, vérifier derrière le proxy TLS que le cookie de session porte `Secure; HttpOnly; SameSite=Lax` et que `Strict-Transport-Security` est présent.
6. `APP_DEBUG=false` et `APP_ENV=production` dans le fichier d'environnement de production (absent du dépôt) : provoquer une erreur 500 et vérifier qu'aucune trace n'est affichée.
7. Droits du compte MySQL de production (SEC-08) : `SHOW GRANTS FOR 'stockflow'@'%';`.
8. Type de contenu servi pour une image (`/products/{id}/image`) : vérifier `Content-Type: image/webp|png|jpeg` et `X-Content-Type-Options: nosniff`, y compris pour un fichier polyglotte de test.
9. Options de DomPDF : aucun `config/dompdf.php` n'est publié ; vérifier que `isRemoteEnabled` et `isPhpEnabled` valent `false` dans la configuration par défaut du paquet installé.
10. Dépendances : lancer `composer audit` et `npm audit` (critère de T-606).
11. Rendu des fragments `X-Partial` après expiration de session : `resources/js/modules/live-list.js:29-41` suit la redirection vers `/login` et insère la page de connexion dans le tableau. Ce n'est pas une faille, mais à corriger pour l'ergonomie (tester `response.redirected`).

## Corrections attendues pour clore T-605

| Identifiant | Gravité | Effort | Fichiers principaux |
| :-- | :-- | :-- | :-- |
| SEC-01 | Majeure | Petit | `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `.env.example`, documentation de déploiement |
| SEC-02 | Majeure | Petit | `resources/views/auth/login.blade.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Controllers/Auth/PasswordController.php` |
| SEC-03 | Mineure | Petit | `app/Http/Controllers/Auth/LoginController.php` |
| SEC-04 | Mineure | Petit | `app/Providers/AppServiceProvider.php`, `PasswordController.php`, `UserController.php` |
| SEC-05 | Mineure | Petit | `app/Enums/Role.php`, `routes/modules.php`, `tests/Feature/Security/PermissionMatrixTest.php` |
| SEC-06 | Mineure | Petit | `config/session.php`, `.env.example` |
| SEC-07 | Mineure | Moyen | `app/Services/ProductService.php` |
| SEC-08 | Mineure | Moyen | nouvelle migration (déclencheurs) ou script de droits MySQL |
| SEC-09 | Mineure | Petit | `app/Http/Requests/MovementRequest.php`, `PurchaseOrderRequest.php`, `DirectReceiptRequest.php` |
| SEC-10 | Mineure | Petit | `app/Http/Controllers/GoodsReceiptController.php`, `routes/modules.php` |
| SEC-11 | Mineure | Petit | `compose.yaml` |
| SEC-12 | Mineure | Petit | `.env.example` |
| SEC-13 | Mineure | Petit | `scripts/backup.sh`, `scripts/restore.sh` |
| SEC-14 | Mineure | Moyen | `resources/views/legal.blade.php`, `config/stockflow.php`, nouvelle commande planifiée, `DECISIONS.md` |

Une non-conformité qui n'est pas corrigée doit être acceptée explicitement par l'utilisateur et consignée dans `DECISIONS.md`, conformément au critère de validation de T-605.

## Suivi des corrections (14/11/2026)

Corrections faites par l'agent principal après l'audit, vérifiées par les tests `Auth`, `Security` et `Modules` (64 tests réussis, 325 vérifications) et par un déploiement de production local.

| Réf. | Statut | Correction |
| :-- | :-- | :-- |
| SEC-01 | Corrigé | `trustProxies` dans `bootstrap/app.php`, piloté par `TRUSTED_PROXIES` (`*` en production, vide en développement), documenté dans `.env.example` et `deployment.md` |
| SEC-02 | Corrigé | Case « Rester connecté » supprimée ; au changement de mot de passe, `remember_token` renouvelé et autres sessions du compte fermées |
| SEC-03 | Corrigé | Blocage après trop d'échecs tracé dans `audit_logs` |
| SEC-04 | Corrigé | Longueur maximale ramenée à 72 caractères (changement de mot de passe et création de compte) |
| SEC-05 | Corrigé | `/internal/dashboard` porte `can:stock.view` |
| SEC-06 | Corrigé | `SESSION_ENCRYPT=true` par défaut ; `SESSION_SECURE_COOKIE=true` exigé en production par `deployment.md` |
| SEC-07 | Accepté | Pas de réencodage GD : le contrôle par `getimagesize`, le nom régénéré, le disque privé et l'en-tête `nosniff` couvrent le risque d'exécution. À reprendre si les images devaient être servies sans contrôle d'accès |
| SEC-08 | Accepté | Protection de l'historique au niveau Eloquent et par la commande `stock:verify`, qui détecte toute retouche. Un déclencheur MySQL est une piste pour une version ultérieure |
| SEC-09 | Corrigé | Mouvement manuel refusé sur un produit retiré ou un emplacement inactif |
| SEC-10 | Corrigé | Tableau `quantities` limité à 500 entrées, exports limités à 20 par minute |
| SEC-11 | Corrigé | En développement, les ports ne sont publiés que sur `127.0.0.1` |
| SEC-12 | Corrigé | `ADMIN_*`, `COMPANY_*` et `TRUSTED_PROXIES` documentées dans `.env.example` |
| SEC-13 | Corrigé | `umask 077` dans `backup.sh`, mot de passe MySQL passé par `MYSQL_PWD` |
| SEC-14 | Ouvert | Page `/legal` : durées de conservation chiffrées et identité réelle de l'entreprise à fournir par le client (variables `COMPANY_*`) ; purge des adresses IP du journal à décider |
| CSP et `style` | Corrigé | Les attributs `style` des vues et du JavaScript, bloqués par la CSP, sont remplacés par des classes CSS |

Audit des dépendances du 14/11/2026 : `composer audit` et `npm audit` ne signalent aucune vulnérabilité connue. Reste à faire : vérifier les en-têtes sur les réponses d'erreur, et ajouter ces deux audits à la chaîne de livraison.

## Audit de préparation au déploiement (19/11/2026)

Tâche T-905 de la Mission 1. Audit préventif en lecture seule de la branche `phase-3/initialisation` (dernier commit `72ae83d`, plus les modifications non commitées du tableau de bord). Aucun fichier n'a été modifié hors de ce rapport, aucun test, aucune migration ni aucune commande d'écriture n'a été lancé. Les valeurs des fichiers `.env` et `.env.production` du poste n'ont été comparées qu'à l'historique Git, sans être recopiées.

### Synthèse

La préparation au déploiement est saine sur l'essentiel. Aucun secret n'a jamais été versionné : ni `.env`, ni `.env.production`, ni sauvegarde, ni clé n'apparaissent dans l'historique des trois branches, et les valeurs réelles des fichiers d'environnement du poste (clé d'application, mots de passe MySQL de production, mot de passe initial de l'administrateur) sont absentes de `git log -p`. L'image de production est construite sans dépendance de développement, tourne sous `www-data`, ne contient pas de `.env`, et le script de déploiement refuse un `APP_DEBUG` actif ou un `APP_ENV` autre que `production`. Toutes les routes d'écriture de l'application passent par le groupe `web` (CSRF), par une autorisation `can:` et par une validation. Le nouveau tableau de bord est correct du point de vue de la sécurité : période validée par liste blanche et format de date strict, expressions SQL construites à partir de noms de colonnes constants, insertion HTML échappée, données JSON encodées par `@json` avec les options d'échappement de Blade, valeur du stock et prix d'achat réservés aux gestionnaires et à l'administrateur. `composer audit` et `npm audit` ne signalent aucune vulnérabilité.

Aucune non-conformité critique ni majeure n'est relevée. Les neuf non-conformités mineures (SEC-15 à SEC-23) touchent surtout le durcissement de l'exploitation : le script de déploiement ne vérifie ni l'attribut `Secure` du cookie de session (le seul `.env.production` éprouvé le met à `false`) ni l'abandon des mots de passe d'exemple ; le mot de passe root de MySQL et le mot de passe initial de l'administrateur sont injectés dans l'environnement du conteneur PHP ; le code de l'image appartient à l'utilisateur qui l'exécute ; les journaux Docker ne tournent pas ; les réponses 404 et `/up` partent sans en-têtes de sécurité. Côté suivi, SEC-07 et SEC-08 restent acceptées sans être consignées dans `DECISIONS.md`, et SEC-14 reste ouverte. Priorités avant une mise en service réelle : SEC-15 et SEC-17, puis SEC-18 et SEC-20, toutes d'effort petit.

### Points de contrôle

| N° | Point de contrôle | Statut | Preuve |
| :-- | :-- | :-- | :-- |
| 1 | Secrets dans le dépôt et l'historique | Conforme | Fichiers suivis sensibles : aucun hors `.env.example` (`git ls-files`). Fichiers ajoutés un jour dans l'historique (`git log --all --diff-filter=A`) : aucun `.env*` hors `.env.example`, aucun `.sql` hors `docs/04-database/schema.sql` (schéma sans données), aucun `.gz`, `.key`, `.pem`, `auth.json`. Recherche de motifs (`APP_KEY=base64:`, clés privées, jetons) dans `git log --all -p` : seulement des valeurs d'exemple (`.env.example:31,33`, `docs/07-deployment/deployment.md:109`), le mot de passe public de démonstration (`database/seeders/DemoSeeder.php:37`) et celui des tests. Les valeurs réelles de `APP_KEY`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` et `ADMIN_PASSWORD` de `.env.production` et la clé de `.env` : zéro occurrence dans l'historique. Clés de développement et de production distinctes. Démonstration impossible en production : `DemoSeeder.php:55-56` refuse `isProduction()`, et `scripts/deploy.sh:15` impose `APP_ENV=production`. |
| 2 | Fichiers `.env` | Partiel | Ignorés par Git (`.gitignore:3-5`, `git check-ignore` confirmé) et par Docker (`.dockerignore:2-4,21`), supprimés de l'image (`docker/php/Dockerfile:62`), fournis au démarrage (`compose.prod.yaml:16`). `.env.example` ne contient que des valeurs de développement (`APP_KEY` vide ligne 6, `ADMIN_PASSWORD` vide ligne 67). Écarts : SEC-15 (valeurs d'exemple acceptées en production), SEC-17 (tout le fichier injecté dans le conteneur PHP). |
| 3 | Configuration de production | Partiel | `APP_DEBUG=false` et `APP_ENV=production` exigés (`scripts/deploy.sh:15-16`), débogage désactivé par défaut (`config/app.php:42`). Journaux : pile `daily`, 14 fichiers (`config/logging.php:68-72`), `LOG_LEVEL=warning` recommandé (`docs/07-deployment/deployment.md:79`) et présent dans `.env.production`, volume dédié (`compose.prod.yaml:20`). Aucune écriture de mot de passe, de jeton ni de corps de requête dans les journaux : un seul `Log::` (`app/Listeners/CheckStockThresholds.php:24`, identifiant de mouvement), champs secrets masqués (`app/Models/User.php:23`). `display_errors = Off` (`docker/php/opcache-prod.ini:6`), `expose_php = Off` (`docker/php/php.ini:3`), `server_tokens off` (`docker/nginx/default.conf:9`). Pages d'erreur dédiées (`resources/views/errors/`). `/up` (`bootstrap/app.php:16`) répond 200 sans information interne. Écarts : SEC-20 (journaux Docker), SEC-21 (en-têtes absents sur 404 et `/up`). |
| 4 | Permissions, utilisateur, ports | Partiel | Production : `USER www-data` (`Dockerfile:66`), `storage` et `bootstrap/cache` créés et attribués à `www-data` (`Dockerfile:62-64`). Root seulement en développement (`docker/php/fpm-dev.conf:5-6`, étape `dev` du `Dockerfile`), constaté par `id` dans le conteneur de développement (`uid=0`). Ports : `web` sur `127.0.0.1` seulement (`compose.prod.yaml:34`), MySQL sans port publié (`compose.prod.yaml:50`), développement sur `127.0.0.1` (`compose.yaml:18,28,52`). Écart : SEC-18 (code applicatif modifiable par `www-data`, outils superflus dans l'image). |
| 5 | Sessions et cookies | Partiel | `HttpOnly` (`config/session.php:185`), `SameSite=lax` (`session.php:202`), sérialisation JSON (`session.php:231`), régénération à la connexion (`app/Http/Controllers/Auth/LoginController.php:67`), invalidation et nouveau jeton à la déconnexion (`LoginController.php:84-85`), `remember_token` renouvelé et session régénérée au changement de mot de passe (`app/Http/Controllers/Auth/PasswordController.php:42,52`). Cookie observé sur `/login` : `httponly; samesite=lax; Max-Age=7200`. Chiffrement : `true` dans `.env.example:43` et `.env.production`, mais défaut `false` dans `session.php:50`. Écarts : SEC-15 (`Secure` non garanti), SEC-16 (session qui survit à la fermeture du navigateur sur poste partagé). |
| 6 | CSRF | Conforme | Aucune exclusion (`validateCsrfTokens`, `$except` absents de `app/`, `bootstrap/`, `routes/`, `config/`). Les 35 routes POST, PUT, PATCH et DELETE de l'application portent le groupe `web` (sortie de `route:list --json`). 25 `@csrf`, aucun formulaire POST sans jeton. Jeton `X-CSRF-TOKEN` sur chaque appel Fetch (`resources/js/lib/http.js:22`). Seule route d'écriture hors `web` : `PUT storage/{path}` du framework, protégée par signature et inaccessible derrière nginx (SEC-22). |
| 7 | Validation des entrées | Conforme | Chaque route d'écriture qui reçoit des données passe par un Form Request (`app/Http/Requests/`) ou par `$request->validate()` (`PasswordController.php:25`, `GoodsReceiptController.php:78`, `InventoryController.php:51,112`, `UserController.php:101-125`, `WarehouseController.php:33`). Routes sans validation parce qu'elles ne lisent aucune donnée hors de l'identifiant de route : `POST /logout`, `POST /alerts/read`, `DELETE` des catégories, fournisseurs et produits, `POST /purchase-orders/{id}/confirm`, `POST /inventories/{id}/submit`, `POST /inventories/{id}/validate`, `POST /users/{id}/reset-password`. Toutes portent un `can:` sauf `/logout`. |
| 8 | Données sensibles exposées | Conforme | `/internal/products/search` : prix de vente seulement, jamais le prix d'achat (`app/Http/Controllers/Internal/ProductSearchController.php:13,34,36-46`). `/internal/dashboard` : `can:stock.view` et `throttle:120,1` (`routes/modules.php:147,149`) ; valeur du stock et commandes en attente réservées à `dashboard.full` (`app/Services/DashboardService.php:105-110`), séries des mouvements et répartition valorisée par emplacement retirées pour le vendeur (`DashboardService.php:74-75`), ventes, palmarès et dernières ventes limités aux siennes (`DashboardService.php:265,289,305`). Page : mêmes contrôles (`resources/views/dashboard/index.blade.php:68-80,127`, `app/Http/Controllers/DashboardController.php:24-25`). Prix d'achat ailleurs derrière `@can('prices.view-purchase')` (`resources/views/products/_table.blade.php:32`, `products/show.blade.php:42`). Comptes : `password` et `remember_token` cachés (`User.php:23`), gestion réservée à `users.manage`. |
| 9 | Scripts | Partiel | `scripts/backup.sh` : `umask 077` (ligne 10), mot de passe par `MYSQL_PWD` (ligne 21), contrôle d'intégrité (lignes 27-31). `scripts/restore.sh` : confirmation tapée (lignes 16-17), `MYSQL_PWD` (lignes 23-24), chemins entre guillemets. `scripts/deploy.sh` : arrêt à la première erreur (ligne 9), contrôles de l'environnement (lignes 14-17). `artisan.cmd` : aucun secret, transmet les arguments de l'utilisateur à sa propre commande Docker, sans élévation de droits. Pas de script `.ps1`. Les sauvegardes locales (`backups/`) sont ignorées par Git (`.gitignore:30`) et par Docker (`.dockerignore:20`). Écarts : SEC-15 (contrôles incomplets de `deploy.sh`), SEC-19 (mot de passe root sur la ligne de commande du contrôle de santé). `restore.sh:23` redonne `ALL PRIVILEGES` au compte applicatif, ce qui prolonge SEC-08. |
| 10 | Dépendances | Conforme | `composer.lock` et `package-lock.json` suivis par Git. Image : `composer install --no-dev` et `dump-autoload --no-dev` (`Dockerfile:41,43`), `npm ci` sur le fichier verrouillé (`Dockerfile:48`) dans une étape séparée dont seul `public/build` est copié (`Dockerfile:59,75`), ni Composer ni Node dans l'image finale. `composer validate` : valide. `composer audit --locked` : aucune alerte. `npm audit` (avec et sans dépendances de développement) : 0 vulnérabilité. Chart.js 4.6.1 ajouté par D-021 ; `package.json` et `package-lock.json` sont modifiés et non commités : ils doivent partir dans le même commit, sinon `npm ci` échoue à la construction. Outils système superflus en production : voir SEC-18. |
| 11 | Code du tableau de bord | Conforme | Période validée côté serveur : liste blanche (`app/Http/Requests/DashboardPeriodRequest.php:23`), `date_format:Y-m-d` et ordre des dates (lignes 24-25), booléen (ligne 26), autorisation `stock.view` (ligne 17) ; contrôle de date réelle et plafond de 731 jours (`app/Support/Period.php:69-80`). `bucketExpression()` (`Period.php:171-179`) n'est appelée qu'avec les constantes `'sold_at'` et `'created_at'` (`DashboardService.php:189,204`) ; aucune saisie n'atteint `selectRaw` ou `groupByRaw`, les bornes passent par `whereBetween` lié. JavaScript : les deux `innerHTML` échappent les valeurs venues du serveur (`resources/js/modules/dashboard.js:33-34,305-306`), le reste en `textContent` (lignes 44, 287, 291, 311, 330), libellés des graphiques dessinés sur `canvas`. JSON dans la page : `<script type="application/json">` avec `@json` (`resources/views/dashboard/index.blade.php:60`), compilé avec `JSON_HEX_TAG \| JSON_HEX_APOS \| JSON_HEX_AMP \| JSON_HEX_QUOT` (vérifié dans `CompilesJson.php` du framework installé) : un nom de produit contenant `</script>` ne peut pas fermer la balise. Remarque : `GET /dashboard` (`routes/web.php:38`) n'a pas de `can:` dans la route, l'autorisation est faite par `DashboardPeriodRequest::authorize()` ; ce n'est pas une faille, mais un écart à la convention de `routes/web.php:11-13`. |
| 12 | Suivi de SEC-07, SEC-08, SEC-14 | Non conforme | SEC-07 : toujours pas de réencodage (`app/Services/ProductService.php:149`), statut « Accepté ». SEC-08 : aucune migration ne crée de déclencheur, statut « Accepté ». Ni l'une ni l'autre n'est consignée dans `DECISIONS.md` (aucune occurrence de `SEC-`), contrairement à la règle de clôture de T-605 : SEC-23. SEC-14 : toujours ouverte, durées de conservation non chiffrées (`resources/views/legal.blade.php:38-39`), identité « à renseigner » par défaut (`config/stockflow.php:14-16`), aucune commande de purge ; `.env.production` du poste ne définit que `COMPANY_NAME`. |

### Non-conformités nouvelles

#### Critique

Aucune.

#### Majeure

Aucune.

#### Mineure

##### SEC-15 : contrôles du script de déploiement incomplets (cookie `Secure`, mots de passe d'exemple)

Description. `scripts/deploy.sh:14-17` vérifie `APP_ENV`, `APP_DEBUG` et `APP_KEY`, mais pas `SESSION_SECURE_COOKIE`, que `docs/07-deployment/deployment.md:82` exige à `true` et dont `config/session.php:172` ne donne aucune valeur par défaut. Le seul `.env.production` éprouvé (celui du déploiement local du 14/11) contient `SESSION_SECURE_COOKIE=false`. Le script n'empêche pas non plus d'installer avec les mots de passe publiés de `.env.example:31,33`, et `config/session.php:50` garde `false` comme défaut de `SESSION_ENCRYPT`. Le suivi de SEC-06 la déclare corrigée par la documentation seule.

Scénario. L'installateur reprend le `.env.production` de la répétition locale, ou copie `.env.example` et ne change que les trois valeurs contrôlées. Le service démarre derrière HTTPS avec un cookie de session sans `Secure` et une base dont les mots de passe figurent dans le dépôt. Tant que HSTS n'est pas mémorisé par le navigateur (premier accès, autre poste), un lien ou une redirection en `http://` fait partir le cookie de session en clair sur le réseau de la boutique.

Correction proposée. Dans `deploy.sh`, après la ligne 17 : `grep -q '^SESSION_SECURE_COOKIE=true' .env.production`, `grep -q '^SESSION_ENCRYPT=true' .env.production`, et refus si `DB_PASSWORD` vaut `secret` ou `DB_ROOT_PASSWORD` vaut `root-secret`. Refuser aussi une clé présente deux fois (exactement une ligne `^APP_DEBUG=`). Dans `config/session.php`, défauts sûrs : `'encrypt' => env('SESSION_ENCRYPT', true)` et `'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')`. Passer le statut de SEC-06 à « Partiel » jusque-là.

Agent : DevOps (script), Backend (`config/session.php`). Effort : petit.

##### SEC-16 : session conservée deux heures après fermeture du navigateur sur des postes partagés

Description. `SESSION_LIFETIME=120` et `expire_on_close` à `false` (`config/session.php:35,37`, `.env.example:42`) : le cookie de session est persistant (`Max-Age=7200` observé sur `/login`). L'architecture rappelle que les postes de la boutique sont partagés (raison de la suppression de « Rester connecté », SEC-02).

Scénario. Une vendeuse ferme le navigateur du poste de caisse sans se déconnecter. Dans les deux heures, le collègue suivant le rouvre et se retrouve connecté sous son nom ; ses ventes sont imputées à la vendeuse dans `stock_movements` et `audit_logs`.

Correction proposée. `SESSION_EXPIRE_ON_CLOSE=true` dans `.env.example` et dans la liste de `deployment.md` ; envisager une durée d'inactivité plus courte (30 à 60 minutes) en production, à faire valider par l'utilisateur. Rappeler dans le guide utilisateur que la déconnexion reste la règle.

Agent : Backend (`.env.example`, `config/session.php`), Rédacteur (guides). Effort : petit.

##### SEC-17 : mot de passe root MySQL et mot de passe initial de l'administrateur dans l'environnement du conteneur PHP

Description. `compose.prod.yaml:16` charge tout `.env.production` dans le conteneur `app` (`env_file`), y compris `DB_ROOT_PASSWORD`, qui ne sert qu'au conteneur `db`, et `ADMIN_PASSWORD`, qui ne sert qu'au premier `db:seed`. L'image PHP officielle règle `clear_env = no` (`/usr/local/etc/php-fpm.d/docker.conf:12`, constaté dans le conteneur) : ces valeurs sont donc lisibles par PHP (`getenv()`, `$_SERVER`). Le `.env.production` du poste contient encore `ADMIN_PASSWORD` [masqué], alors que `deployment.md:112` demande de le retirer après la première connexion.

Scénario. Une faille future de lecture de fichier ou d'exécution dans PHP, ou une page de débogage activée par erreur, livre le mot de passe root de MySQL, qui donne plus que le compte applicatif (création de comptes, toutes les bases). Une copie de `.env.production` faite pour la sauvegarde (`backup-restore.md:83`) garde indéfiniment le mot de passe initial de l'administrateur.

Correction proposée. Séparer les variables : un fichier `.env.db` (ou des secrets Docker) pour `MYSQL_ROOT_PASSWORD`, lu par le seul service `db` ; ne laisser à `app` que `DB_USERNAME` et `DB_PASSWORD`. Faire échouer `deploy.sh` (ou afficher un avertissement) si `ADMIN_PASSWORD` est encore renseigné alors qu'un administrateur existe.

Agent : DevOps. Effort : petit.

##### SEC-18 : code de l'image de production modifiable par l'utilisateur qui l'exécute, outils superflus

Description. `docker/php/Dockerfile:57-59` copie tout le code, `vendor` et `public/build` avec `--chown=www-data:www-data`, puis l'image tourne sous `www-data` (ligne 66). PHP-FPM peut donc réécrire `app/`, `config/`, `routes/`, `vendor/` et `public/index.php`, alors que seuls `storage/` et `bootstrap/cache/` ont besoin d'être inscriptibles (lignes 62-64). L'étape `prod` hérite de `base`, qui installe `git`, `unzip` et `default-mysql-client` (ligne 13, présents dans le conteneur), inutiles à l'exécution (les sauvegardes passent par le conteneur `db`).

Scénario. Une faille d'écriture de fichier (téléversement mal contrôlé, dépendance vulnérable) permet de déposer une porte dérobée dans un fichier PHP du code, qui survit jusqu'à la reconstruction de l'image et s'exécute au prochain redémarrage de PHP-FPM. Les outils présents facilitent ensuite l'exploration et l'exfiltration.

Correction proposée. Copier le code sans `--chown` (propriétaire `root`, lecture seule pour `www-data`) et ne donner à `www-data` que `storage` et `bootstrap/cache`, comme le fait déjà la ligne 64. Installer `git` et `unzip` uniquement dans l'étape `dev` ; retirer `default-mysql-client` de l'étape `prod` s'il n'est pas utilisé.

Agent : DevOps. Effort : petit.

##### SEC-19 : mot de passe root MySQL sur la ligne de commande du contrôle de santé

Description. Les contrôles de santé lancent `mysqladmin ping ... -p$$MYSQL_ROOT_PASSWORD` (`compose.prod.yaml:54`, `compose.yaml:56,83`). Le mot de passe apparaît dans les arguments du processus, visibles par `ps` sur l'hôte, toutes les dix secondes en production. SEC-13 a corrigé ce point dans les scripts de sauvegarde, pas ici.

Scénario. Un autre compte de l'hôte, ou un outil de supervision qui collecte la liste des processus, enregistre le mot de passe root de MySQL.

Correction proposée. `mysqladmin ping -h 127.0.0.1 --silent` sans identifiants suffit (la commande répond que le serveur est vivant même quand l'authentification est refusée), ou `MYSQL_PWD="$$MYSQL_ROOT_PASSWORD" mysqladmin ping ...`.

Agent : DevOps. Effort : petit.

##### SEC-20 : journaux des conteneurs sans rotation

Description. `compose.prod.yaml` ne déclare aucune section `logging` pour `app`, `web` et `db` (lignes 8-57), et aucun document de `docs/07-deployment/` ne mentionne `max-size`. Le pilote `json-file` par défaut de Docker conserve alors les journaux sans limite, sauf réglage du démon. Les journaux de Laravel tournent bien (`config/logging.php:68-72`), mais pas les journaux d'accès de nginx ni ceux de MySQL.

Scénario. Après quelques mois, ou sous une rafale de requêtes (balayage automatisé de la page de connexion), le disque se remplit ; MySQL s'arrête faute de place et la boutique ne peut plus vendre.

Correction proposée. Ajouter à chaque service : `logging: { driver: json-file, options: { max-size: "10m", max-file: "5" } }` (ou un bloc commun par ancre YAML), et le documenter dans `deployment.md`.

Agent : DevOps. Effort : petit.

##### SEC-21 : en-têtes de sécurité absents des erreurs hors route et de `/up`

Description. Les en-têtes CSP, `X-Frame-Options` et `X-Content-Type-Options` sont posés par `SecurityHeaders`, attaché au seul groupe `web` (`bootstrap/app.php:19`). Une adresse inconnue (404 rendue avant tout groupe de middlewares) et `/up` repartent sans eux, constaté avec `curl -D -` sur `/route-inexistante` et `/up`. Nginx n'ajoute d'en-tête que pour `/storage/` (`docker/nginx/default.conf:21`). Le suivi du 14/11 laissait ce contrôle « à faire ».

Scénario. Faible : les pages d'erreur ne reflètent pas de saisie. Une page 404 de l'application peut toutefois être encadrée par un site tiers (absence de `frame-ancestors`), et HSTS ne sera jamais envoyé sur ces réponses.

Correction proposée. Ajouter dans `docker/nginx/default.conf`, au niveau `server`, `add_header X-Content-Type-Options nosniff always;`, `add_header X-Frame-Options DENY always;` et `add_header Referrer-Policy strict-origin-when-cross-origin always;` (la CSP avec nonce reste à Laravel), en reprenant ces lignes dans le bloc `/storage/`, car `add_header` ne s'hérite pas dans un bloc qui en déclare. Ou bien ajouter `SecurityHeaders` en middleware global (`$middleware->append(...)`).

Agent : DevOps (nginx) ou Backend (middleware global). Effort : petit.

##### SEC-22 : routes de service de fichiers du framework enregistrées sans usage

Description. Le disque `local` porte `'serve' => true` (`config/filesystems.php:36`). Laravel enregistre alors `GET storage/{path}` et `PUT storage/{path}` hors du groupe `web`, sans authentification ni CSRF (sortie de `route:list`). Elles exigent une URL signée par `APP_KEY` (`ServeFile` et `ReceiveFile` du framework) et l'application n'en produit aucune (`temporaryUrl` absent du code). Aujourd'hui nginx répond 404 avant PHP (`default.conf:19-22`, constaté par `curl`). Les images passent par la route authentifiée `products.image`.

Scénario. Aucun dans l'état actuel. Si la règle nginx `/storage/` change, ou si `APP_KEY` fuit, ces routes permettent de lire et d'écrire des fichiers du disque privé en contournant l'authentification de `products.image`.

Correction proposée. `'serve' => false` sur le disque `local` (vérifier ensuite que `route:list` ne montre plus `storage.local`).

Agent : Backend. Effort : petit.

##### SEC-23 : acceptation de SEC-07 et SEC-08 non consignée

Description. Le suivi du 14/11 marque SEC-07 (pas de réencodage des images) et SEC-08 (historique protégé au seul niveau Eloquent) comme « Accepté ». La règle de clôture de T-605, rappelée plus haut dans ce document, veut qu'une non-conformité non corrigée soit acceptée explicitement par l'utilisateur et consignée dans `DECISIONS.md`. `DECISIONS.md` ne contient aucune entrée correspondante (aucune occurrence de `SEC-`).

Scénario. Lors de la soutenance ou d'un contrôle, rien ne prouve que le risque résiduel sur l'historique en ajout seul, règle métier n° 5 de `CLAUDE.md`, a été accepté par le responsable du projet et non oublié.

Correction proposée. Soumettre les deux acceptations à l'utilisateur, puis ajouter deux entrées dans `DECISIONS.md` (justification et condition de réexamen, par exemple « déclencheurs MySQL en version 1.1 »). Faire de même pour SEC-14 si elle reste ouverte au déploiement.

Agent : Rédacteur (après accord de l'utilisateur). Effort : petit.

### Récapitulatif

| Réf. | Gravité | Agent | Fichiers principaux |
| :-- | :-- | :-- | :-- |
| SEC-15 | Mineure | DevOps, Backend | `scripts/deploy.sh`, `config/session.php` |
| SEC-16 | Mineure | Backend, Rédacteur | `config/session.php`, `.env.example`, `docs/07-deployment/deployment.md` |
| SEC-17 | Mineure | DevOps | `compose.prod.yaml`, `scripts/deploy.sh` |
| SEC-18 | Mineure | DevOps | `docker/php/Dockerfile` |
| SEC-19 | Mineure | DevOps | `compose.prod.yaml`, `compose.yaml` |
| SEC-20 | Mineure | DevOps | `compose.prod.yaml`, `docs/07-deployment/deployment.md` |
| SEC-21 | Mineure | DevOps ou Backend | `docker/nginx/default.conf` ou `bootstrap/app.php` |
| SEC-22 | Mineure | Backend | `config/filesystems.php` |
| SEC-23 | Mineure | Rédacteur | `DECISIONS.md` |

Non-conformités précédentes toujours ouvertes : SEC-14 (ouverte), SEC-07 et SEC-08 (acceptées, non consignées, voir SEC-23).

### Commandes exécutées

Toutes en lecture ; aucune ne modifie un fichier, une base ou un conteneur.

- `git ls-files`, `git check-ignore -v .env .env.production backups/x`, `git branch -a`, `git stash list`, `git log --all --oneline`, `git diff` (fichiers du tableau de bord, `package.json`, `DECISIONS.md`)
- `git log --all --diff-filter=A --name-only` filtré sur `.env`, `.sql`, `.gz`, `.key`, `.pem`, sauvegardes, `auth.json`
- `git log --all -p` filtré sur les motifs de secrets (sortie masquée), puis comptage des occurrences des valeurs réelles de `.env` et `.env.production` dans `git log --all -p` (seuls la longueur et le nombre d'occurrences ont été affichés)
- `git log --oneline -- composer.lock package-lock.json`, `git ls-files --stage scripts/`
- Lecture des noms de variables de `.env` et `.env.production` (valeurs effacées par `sed`) et des seules valeurs non secrètes (`APP_ENV`, `APP_DEBUG`, `LOG_*`, `SESSION_*`, `TRUSTED_PROXIES`)
- `docker compose ps`
- `docker compose exec -T app php artisan route:list --except-vendor` et `route:list --json` (analyse des middlewares par `php -r`)
- `docker compose exec -T app php artisan config:show session`, `config:show logging.channels.daily`, `config:show app`, `php artisan --version`
- `docker compose exec -T app composer audit --locked`, `composer validate --no-check-publish`, `npm audit`, `npm audit --omit=dev`
- `docker compose exec -T app sh -c 'ls -ld storage storage/logs bootstrap/cache; id'`, `grep clear_env /usr/local/etc/php-fpm.d/*`, `dpkg -l git unzip default-mysql-client`, lecture de `FilesystemServiceProvider.php`, `ServeFile.php`, `ReceiveFile.php` et `CompilesJson.php` du framework installé
- `curl -D -` sur `http://127.0.0.1:8080/login`, `/route-inexistante`, `/up` ; `curl` GET et PUT sur `/storage/x.png` (404 renvoyé par nginx, rien d'écrit)
- `grep` et lecture des fichiers du périmètre (`.gitignore`, `.dockerignore`, `.env.example`, `compose*.yaml`, `docker/`, `scripts/`, `artisan.cmd`, `bootstrap/app.php`, `config/`, `routes/`, `app/Http/`, `app/Services/DashboardService.php`, `app/Support/Period.php`, `resources/views/dashboard/`, `resources/js/`, `database/seeders/`, `docs/07-deployment/`)
