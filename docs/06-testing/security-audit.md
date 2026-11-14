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
