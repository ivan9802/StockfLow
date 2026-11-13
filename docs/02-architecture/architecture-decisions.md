# Décisions d'architecture StockFlow

Ce document détaille les décisions du registre `DECISIONS.md`. Les numéros et les statuts sont les mêmes : ADR-001 correspond à D-001, et ainsi de suite. Une décision « Proposée » attend l'accord de l'utilisateur et ne doit pas être considérée comme acquise par les autres documents.

Les dates de support de Laravel viennent de laravel.com/docs/releases, consulté le 13/11/2026. L'état du poste de développement a été relevé le même jour.

| ADR | Sujet | Statut |
| :-- | :-- | :-- |
| ADR-001 | Version de Laravel et de PHP | Proposée |
| ADR-002 | Base de données | Proposée |
| ADR-003 | Docker Compose | Proposée |
| ADR-004 | Stock par emplacement | Acceptée |
| ADR-005 | Rôles et autorisations | Acceptée |
| ADR-006 | Transfert en une étape | Proposée |
| ADR-007 | Inventaire sans gel des mouvements | Proposée |
| ADR-008 | Seuils d'alerte | Proposée |
| ADR-009 | Séparation des tâches | Proposée |
| ADR-010 | Authentification écrite à la main | Acceptée |
| ADR-011 | Dépendances PDF et codes-barres | Acceptée |
| ADR-012 | Montants en entiers | Acceptée |
| ADR-013 | Alertes par notifications en base | Acceptée |
| ADR-014 | Tests et concurrence | Acceptée |
| ADR-015 | Graphiques sans bibliothèque | Acceptée |
| ADR-016 | Réception directe sans commande | Proposée |

## ADR-001 : version de Laravel et de PHP

Statut : Acceptée

Date : 13/11/2026

### Contexte

Le cahier des charges demande Laravel 11 sur PHP 8.2 ou plus. À la date de ce document :

| Version | PHP accepté | Correctifs de bogues | Correctifs de sécurité |
| :-- | :-- | :-- | :-- |
| Laravel 11 | | jusqu'au 03/09/2025 | jusqu'au 12/03/2026 : terminé |
| Laravel 12 | 8.2 à 8.5 | jusqu'au 13/08/2026 : terminé | jusqu'au 24/02/2027 |
| Laravel 13 | 8.3 à 8.5 | | jusqu'au premier trimestre 2028 |

Le poste de développement a PHP 8.2.12 (XAMPP), sans les extensions `gd`, `intl` et `sodium`, Composer 2.10 et Node 22. Laravel 13 exige PHP 8.3 au minimum : il ne peut pas tourner sur le PHP du poste. PHP 8.2 arrive lui-même en fin de vie fin 2026.

Le projet doit être démontrable en soutenance et déployable ensuite pour une entreprise. Une application de gestion mise en production sur un framework sans correctifs de sécurité part avec une dette immédiate.

### Options étudiées

| Option | Avantages | Inconvénients |
| :-- | :-- | :-- |
| A. Laravel 11 sur PHP 8.2 | conforme au cahier des charges à la lettre ; tourne sur le PHP déjà installé | plus aucun correctif de sécurité depuis le 12/03/2026 ; une faille publiée ne sera pas corrigée ; difficile à défendre pour une mise en production |
| B. Laravel 12 sur le PHP 8.2 du poste | aucune installation ; encore couvert par les correctifs de sécurité ; code très proche de Laravel 11 | fin des correctifs de sécurité le 24/02/2027, soit trois mois après le début du projet ; correctifs de bogues déjà arrêtés ; PHP 8.2 en fin de vie fin 2026 ; migration vers la version suivante à prévoir dès la livraison ; les extensions `gd` et `intl` restent à activer dans XAMPP |
| C. Laravel 13 sur PHP 8.4 en conteneur | correctifs de sécurité jusqu'au premier trimestre 2028 ; PHP 8.4 dans la plage acceptée (8.3 à 8.5) ; même environnement en développement et en production | s'écarte de la version écrite dans le cahier des charges ; dépend de Docker (ADR-003), dont le démon est arrêté sur le poste ; version récente, donc moins de retours d'expérience et de réponses en ligne |

Les trois versions partagent la structure allégée du projet (`bootstrap/app.php`, pas de `Kernel`). L'architecture décrite dans `system-architecture.md` vaut pour les trois. Le choix porte sur la durée de support, pas sur la conception.

### Décision

Proposition de l'architecte : option C, Laravel 13 sur PHP 8.4 en conteneur. Repli si Docker ne peut pas être utilisé : option B, en sachant qu'une montée de version sera due avant le 24/02/2027. L'option A est déconseillée.

La décision appartient à l'utilisateur. Elle modifie une exigence écrite du cahier des charges, donc elle reste « Proposée » tant qu'il ne l'a pas confirmée. L'écart devra être expliqué dans le rapport de soutenance : la version demandée n'est plus maintenue à la date de réalisation.

### Conséquences

- Si C est retenue : ADR-003 devient un prérequis, le XAMPP du poste n'est plus utilisé, `composer.json` exige `php ^8.4`.
- Si B est retenue : il faut activer `gd` et `intl` dans le `php.ini` de XAMPP, et inscrire au backlog la migration vers Laravel 13 et un PHP maintenu.
- Dans tous les cas, `CLAUDE.md` renvoie à cette décision pour les versions de PHP et de Laravel, et le rapport doit refléter la version retenue.
- Les dates de support sont à revérifier avant la mise en production.

## ADR-002 : base de données

Statut : Acceptée

Date : 13/11/2026

### Contexte

Le cahier des charges demande « MySQL 8.0 (ou MariaDB) » avec InnoDB. Le registre indique que MySQL 8.0 est en fin de vie et que la MariaDB 10.4 du poste est hors support. Le poste n'a que cette MariaDB 10.4.32 (XAMPP), sur le port 3306.

Les garanties du projet reposent sur le comportement du moteur : verrous de ligne posés par `SELECT ... FOR UPDATE`, contraintes `CHECK`, colonnes JSON du journal d'audit, détection des interblocages. Le critère de réussite n° 1 (aucun stock négatif sous accès concurrents) se prouve par des tests qui n'ont de valeur que sur le moteur de production.

### Options étudiées

| Option | Avantages | Inconvénients |
| :-- | :-- | :-- |
| A. MariaDB 10.4 de XAMPP | déjà installée, aucune mise en place | version hors support ; ce n'est pas le moteur de production ; type JSON, optimiseur et détails de verrouillage différents de MySQL ; les tests de concurrence ne prouveraient rien sur la cible |
| B. MySQL 8.0, comme écrit dans le cahier | conforme à la lettre | en fin de vie selon le registre ; à installer de toute façon |
| C. MySQL 8.4 LTS en conteneur, partout | version à support long ; même moteur en développement, en test et en production ; installation par une ligne de `compose.yaml` | dépend de Docker (ADR-003) ; s'écarte du numéro de version du cahier |
| D. MariaDB récente en conteneur, partout | autorisée par le cahier (« ou MariaDB ») ; même moteur partout | `CLAUDE.md` fixe MySQL 8 ; les écarts avec MySQL obligeraient à revalider le schéma et les tests |

### Décision

Proposition : option C, MySQL 8.4 LTS dans Docker pour le développement, les tests et la production. Le conteneur est publié sur un autre port que 3306 pour ne pas entrer en conflit avec XAMPP ni être confondu avec lui.

### Conséquences

- Le schéma de `database-design.md` est écrit pour MySQL : contraintes `CHECK`, colonnes `json`, `utf8mb4_unicode_ci`.
- Une base `stockflow_test` distincte vit sur le même serveur. SQLite est exclu, y compris pour les tests rapides.
- Si l'utilisateur refuse Docker, il faudra installer MySQL 8.4 directement sur le poste. Développer sur la MariaDB de XAMPP reste déconseillé.
- La date de fin de vie de MySQL 8.0 reprise du registre est à confirmer sur le site de l'éditeur avant d'être citée dans le rapport.

## ADR-003 : environnement Docker Compose

Statut : Acceptée

Date : 13/11/2026

### Contexte

Le poste est sous Windows 11 avec XAMPP : PHP 8.2.12 sans `gd`, `intl` ni `sodium`, MariaDB 10.4.32. Docker 29 est installé, son démon est arrêté. Le projet a besoin de `gd` (images, PDF) et de `intl` (formats). Les propositions ADR-001 et ADR-002 demandent PHP 8.4 et MySQL 8.4, absents du poste. L'application doit pouvoir être déployée telle qu'elle a été testée.

### Options étudiées

| Option | Avantages | Inconvénients |
| :-- | :-- | :-- |
| A. XAMPP tel quel | rien à installer, démarrage immédiat | PHP 8.2 seulement, extensions à activer à la main, MariaDB hors support ; environnement différent de la production ; installation difficile à reproduire sur un autre poste |
| B. Installation native de PHP 8.4 et de MySQL 8.4 sur Windows | pas de conteneur, bonnes performances de fichiers | installation manuelle longue et propre au poste ; cohabitation avec XAMPP ; rien de réutilisable pour le déploiement |
| C. Docker Compose écrit pour le projet (PHP-FPM, Nginx, MySQL) | versions fixées dans le dépôt ; mêmes images en développement et en production ; un nouveau poste démarre avec une commande ; les fichiers `docker/` servent au déploiement | démon à démarrer, consommation de mémoire ; accès aux fichiers Windows plus lent depuis un conteneur ; une couche de plus à comprendre et à expliquer en soutenance |
| D. Laravel Sail | fourni par Laravel, prêt à l'emploi | paquet supplémentaire, pensé pour le développement et pas pour la production ; masque la configuration au lieu de la montrer |

### Décision

Proposition : option C. Trois services (`app`, `web`, `db`) décrits dans `compose.yaml`, avec une variante `compose.prod.yaml`. Les fichiers de construction sont dans `docker/`.

### Conséquences

- Docker doit être démarré avant la phase d'initialisation. C'est un prérequis à vérifier avec l'utilisateur.
- Les commandes passent par le conteneur : `docker compose exec app php artisan ...`. Elles seront listées dans `CLAUDE.md` à la phase 3.
- Lenteur possible des fichiers montés depuis Windows : volumes nommés pour `vendor` et `node_modules`, ou dépôt placé dans le système de fichiers WSL.
- Vite peut rester sur le poste, avec le Node 22 déjà installé.
- Les images téléversées et les données MySQL sont dans des volumes persistants, à sauvegarder.
- Si la proposition est refusée, ADR-001 retombe sur son option B et ADR-002 demande une installation native de MySQL.

## ADR-004 : stock tenu par produit et par emplacement

Statut : Acceptée

Date : 13/11/2026

### Contexte

Le modèle du cahier des charges porte le stock dans `products.current_stock`. L'entreprise a un entrepôt central et un point de vente. Le premier problème cité par le cahier est la vente en boutique de produits qui n'y sont pas. Une quantité unique par produit ne dit pas où se trouve la marchandise.

### Options étudiées

| Option | Avantages | Inconvénients |
| :-- | :-- | :-- |
| A. `products.current_stock`, comme dans le cahier | modèle le plus simple ; correspond au critère de réussite n° 2 tel qu'il est écrit | aucune localisation ; transferts et réassort de la boutique impossibles à représenter ; un inventaire par site n'a pas de sens |
| B. Table `stock_levels`, une ligne par produit et par emplacement | localise le stock ; verrou de ligne fin (deux ventes sur deux sites ne se bloquent pas) ; accepte un troisième emplacement sans migration | une jointure ou une somme pour obtenir le total ; lignes à créer pour chaque produit et chaque emplacement |
| C. Pas de quantité stockée, stock calculé par somme des mouvements | pas de dérive possible entre niveau et historique | lecture de plus en plus coûteuse ; pas de ligne à verrouiller pour sérialiser deux ventes ; pas de contrainte `CHECK` possible sur une somme |
| D. Colonne unique plus `stock_levels` | lecture du total immédiate | deux sources pour la même information, donc une dérive de plus à surveiller |

### Décision

Option B. Le stock est tenu dans `stock_levels` avec la contrainte `UNIQUE(product_id, warehouse_id)`. La colonne `products.current_stock` n'existe pas. Le stock total d'un produit est la somme de ses lignes.

### Conséquences

- Le critère de réussite n° 2 se lit désormais ainsi : chaque variation de `stock_levels.quantity` correspond à exactement une ligne de `stock_movements`. À expliquer en soutenance.
- La ligne `stock_levels` est l'unité de verrouillage. L'ordre de verrouillage `(product_id, warehouse_id)` en découle.
- Un mouvement porte toujours un emplacement. Un transfert écrit deux mouvements.
- Les seuils d'alerte doivent préciser s'ils portent sur le total ou sur un emplacement (ADR-008).
- Le tableau de bord agrège par somme. Sur 800 références et deux emplacements, le coût est négligeable.

## ADR-005 : rôles fixes et autorisations par Gates et Policies

Statut : Acceptée

Date : 13/11/2026

Contexte : trois profils stables, décrits dans le cahier des charges, qui demande des middlewares et des Policies.

Options : un paquet de permissions (`spatie/laravel-permission`) avec rôles et permissions en base, ou un enum PHP et une colonne `users.role`. Le paquet apporte des tables, un cache et un écran de gestion pour une souplesse dont le projet n'a pas l'usage.

Décision : enum `App\Enums\Role`, colonne `users.role`, middleware `role` pour le filtrage grossier, Policies et Gates pour la décision fine.

Conséquences : ajouter un profil demande une modification de code et une migration de l'enum. Les règles d'accès sont lisibles dans les Policies et testables une à une.

## ADR-006 : transfert exécuté en une seule étape

Statut : Acceptée

Date : 13/11/2026

### Contexte

Les transferts déplacent de la marchandise entre l'entrepôt central et le point de vente, deux sites de la même entreprise à Abidjan. La question est de savoir si un transfert est un fait unique ou un processus en deux temps, avec expédition puis accusé de réception.

### Options étudiées

| Option | Avantages | Inconvénients |
| :-- | :-- | :-- |
| A. Une étape : sortie de la source et entrée à destination dans la même transaction | un seul écran, un seul statut utile ; le stock total ne varie jamais ; pas de marchandise « nulle part » dans les états ; simple à tester | la marchandise est disponible à destination dès la saisie, même si elle est encore en route ; une perte pendant le trajet n'apparaît qu'à l'inventaire ; pas de contrôle par le destinataire |
| B. Deux étapes : expédition (statut `in_transit`), puis réception confirmée à destination | fidèle au trajet physique ; le destinataire confirme les quantités reçues ; un écart de transport est tracé tout de suite | un statut, un écran et un jeu de règles en plus ; il faut représenter le stock en transit (emplacement virtuel ou colonne) ; cas d'annulation partielle à traiter ; valorisation et alertes à adapter |

### Décision

Proposition : option A. Le transfert est saisi quand la marchandise change effectivement de site. Sa référence relie les deux mouvements. Il peut être annulé par les mouvements inverses.

### Conséquences

- Consigne d'usage à écrire dans le guide utilisateur : le transfert se saisit à l'arrivée, ou au départ si le trajet est court, pour ne pas rendre vendable une marchandise absente du point de vente.
- Un manquant constaté à l'arrivée se traite par un ajustement manuel justifié à destination.
- L'annulation échoue si la destination n'a plus les quantités : le service lève `InsufficientStockException`.
- La colonne `status` de `stock_transfers` existe déjà. Passer à l'option B plus tard demanderait d'ajouter des valeurs à l'enum et un écran de réception, sans refonte des tables.

## ADR-007 : inventaire sans gel des mouvements

Statut : Acceptée

Date : 13/11/2026

### Contexte

Un inventaire compare le stock théorique au stock compté et régularise les écarts avec justification (F3.3). Le comptage du point de vente peut durer plusieurs heures. La boutique ne ferme pas pour autant, et le premier problème cité par le cahier est la saisie tardive des ventes.

### Options étudiées

| Option | Avantages | Inconvénients |
| :-- | :-- | :-- |
| A. Gel : aucun mouvement sur l'emplacement tant que la session est ouverte | écart exact, règle simple à expliquer | les ventes sont bloquées pendant le comptage, ou notées sur papier puis saisies après coup, ce qui recrée le problème d'origine |
| B. Pas de gel, théorique photographié à l'ouverture, écart `compté - théorique` appliqué au stock courant à la validation (première proposition du registre) | les ventes continuent ; une vente faite après le comptage d'un produit est correctement prise en compte, puisque l'écart s'ajoute au stock courant | une vente faite entre l'ouverture et le comptage du produit est comptée deux fois (voir ci-dessous) ; l'écart appliqué peut dépasser le stock courant |
| C. Pas de gel, théorique relevé au moment de la saisie de chaque ligne, écart appliqué au stock courant à la validation | les ventes continuent ; l'écart est juste quel que soit le moment des ventes ; la fenêtre d'erreur se réduit au délai entre le comptage physique et sa saisie | demande une colonne `counted_at` et change le sens de `theoretical_quantity`, donc une modification de `database-design.md` ; la saisie doit se faire au fil du comptage, sur tablette |

Défaut de l'option B, sur un exemple. À l'ouverture, le théorique d'un produit est 10. Trois unités sont vendues avant que le compteur arrive à ce rayon : le stock courant passe à 7. Le compteur trouve 7 unités, ce qui est exact. L'écart calculé est 7 moins 10, soit -3. À la validation, -3 est appliqué au stock courant de 7 : le stock devient 4 alors qu'il y a 7 unités en rayon. La vente a été retirée deux fois. Avec l'option C, le théorique relevé à la saisie est 7, l'écart est nul et rien n'est modifié.

### Décision

Option C, proposée à l'utilisateur. La première version du registre retenait l'option B ; la revue de l'architecte a montré son défaut de calcul et le registre comme `database-design.md` ont été révisés le 13/11/2026. Les mouvements restent autorisés pendant un inventaire, le théorique est relevé à la saisie de chaque ligne, la validation applique l'écart au stock courant.

L'option B n'était juste que si aucun mouvement ne touchait un produit entre l'ouverture de la session et son comptage. L'option C garde l'avantage recherché (pas de blocage des ventes) sans ce défaut.

### Conséquences

Avec l'option C :

- `inventory_items` reçoit `counted_at` et `counted_by`, et `theoretical_quantity` reste nulle jusqu'au comptage de la ligne ;
- consigne d'usage : saisir sur tablette au fil du comptage, puisque la fenêtre d'erreur se réduit au délai entre le comptage physique et sa saisie ;
- l'écran de validation signale les produits qui ont bougé depuis leur comptage, à titre d'information ;
- la validation est refusée en bloc si un écart négatif dépasse le stock courant ; la session reste en attente et peut être renvoyée en comptage (`pending_validation` vers `in_progress`, motif dans `review_comment`).

Dans tous les cas :

- une seule session ouverte par emplacement, contrôlée sous verrou de la ligne `warehouses` ;
- chaque écart non nul exige une justification et produit un mouvement `inventory_adjustment` ;
- la validation est réservée à l'administrateur (ADR-009).

## ADR-008 : seuils d'alerte

Statut : Acceptée

Date : 13/11/2026

Contexte : avec le stock par emplacement (ADR-004), la formule du cahier (`quantite_actuelle <= seuil_alerte`) doit préciser de quelle quantité il s'agit.

Options : un seuil unique comparé au stock total ; un seuil par emplacement seulement ; les deux. Le seuil total répond à la question « faut-il commander au fournisseur ? ». Il ne voit pas une boutique vide quand l'entrepôt est plein. Le seuil par emplacement seul oblige à saisir deux valeurs pour chacune des 800 références.

Décision : `products.alert_threshold` est comparé au stock total. `stock_levels.reorder_point`, facultatif, est comparé au stock de son emplacement et sert au réassort de la boutique depuis l'entrepôt.

Conséquences : deux portées d'alerte à distinguer dans les notifications et au tableau de bord. La comparaison reste « inférieur ou égal », comme dans le cahier. Un produit dont le seuil vaut zéro n'alerte qu'en rupture.

## ADR-009 : séparation des tâches

Statut : Acceptée

Date : 13/11/2026

Contexte : le cahier réserve à l'administrateur la validation des écarts d'inventaire et donne au vendeur la création de bons de sortie. Il ne dit pas qui annule une vente ni depuis quel emplacement un vendeur vend.

Options : laisser chaque profil corriger ses propres opérations, ce qui est plus simple mais permet de masquer une erreur ou un détournement ; ou imposer qu'une correction soit faite par un autre profil.

Décision : la validation d'un inventaire est réservée à l'administrateur. L'annulation d'une vente est réservée à l'administrateur et au gestionnaire de stock. Le vendeur ne vend que depuis son emplacement d'affectation (`users.warehouse_id`).

Conséquences : `users.warehouse_id` est obligatoire pour un vendeur. Un vendeur qui se trompe doit faire annuler sa vente par un autre profil, ce qui suppose qu'un gestionnaire soit joignable pendant les heures d'ouverture. Ces règles sont portées par `SalePolicy` et `InventorySessionPolicy` et testées par rôle. Le cas d'un administrateur qui compte puis valide lui-même n'est pas interdit par cette décision.

## ADR-010 : authentification écrite à la main

Statut : Acceptée

Date : 13/11/2026

Contexte : il faut une connexion par adresse électronique et mot de passe, une déconnexion et un changement de mot de passe. Les comptes sont créés par l'administrateur.

Options : Laravel Breeze ou Jetstream, qui génèrent l'authentification mais imposent Tailwind et des écrans hors besoin (inscription, vérification d'adresse) ; ou quelques contrôleurs et vues Blade écrits pour le projet, sur le garde `web` de Laravel.

Décision : authentification écrite à la main, sans Breeze ni Jetstream.

Conséquences : la régénération de session, la limitation des tentatives et la journalisation sont à écrire et à tester explicitement (voir la section sécurité de `system-architecture.md`). Pas de réinitialisation par courriel dans cette version : l'administrateur redéfinit le mot de passe d'un utilisateur.

## ADR-011 : dépendances pour le PDF et les codes-barres

Statut : Acceptée

Date : 13/11/2026

Contexte : le cahier demande des exports CSV et PDF et la génération de codes-barres. Laravel ne fournit ni moteur PDF ni générateur de codes-barres.

Options : pour le PDF, `barryvdh/laravel-dompdf` (PHP pur, gabarits Blade) ou un outil fondé sur un navigateur sans interface, plus fidèle mais lourd à installer et à déployer. Pour les codes-barres, `picqer/php-barcode-generator` (PHP pur, sortie SVG) ou une bibliothèque JavaScript côté navigateur, inutilisable dans un PDF. Pour le CSV, un paquet d'export ou `fputcsv`.

Décision : `barryvdh/laravel-dompdf` et `picqer/php-barcode-generator`. Le CSV est produit par `fputcsv` en flux, sans paquet.

Conséquences : dompdf consomme beaucoup de mémoire sur les longs tableaux, d'où un plafond de lignes pour le PDF et le CSV pour les gros volumes. Le SVG de code-barres est le seul contenu affiché sans échappement dans Blade. Toute autre dépendance passe par une nouvelle entrée du registre.

## ADR-012 : montants en francs CFA stockés en entiers

Statut : Acceptée

Date : 13/11/2026

Contexte : les prix et les totaux sont en francs CFA (XOF), monnaie sans subdivision.

Options : `DECIMAL(12,2)`, usage courant mais avec deux décimales toujours nulles ; nombres flottants, exclus pour des montants ; entiers.

Décision : entiers non signés, `INT` pour les prix unitaires et `BIGINT` pour les totaux.

Conséquences : aucun arrondi dans les calculs. Le formatage (`1 200 000 F CFA`) est fait à l'affichage par `App\Support\Format`. Une remise en pourcentage, si elle était ajoutée, devrait définir sa règle d'arrondi à l'unité.

## ADR-013 : alertes de stock par notifications en base

Statut : Acceptée

Date : 13/11/2026

Contexte : le cahier demande une notification visuelle dans l'interface dès qu'un produit passe sous son seuil (F4.2).

Options : calculer les alertes à l'affichage seulement, sans rien stocker, ce qui ne permet pas de savoir ce qui est nouveau ni ce qui a été lu ; envoyer des courriels, ce qui suppose un service d'envoi ; utiliser le canal `database` des notifications Laravel.

Décision : notifications Laravel sur le canal `database`, émises au franchissement du seuil et non à chaque mouvement sous le seuil.

Conséquences : la table `notifications` de Laravel est utilisée. Le listener s'exécute après le commit. La liste des produits en alerte du tableau de bord reste calculée sur l'état courant et ne dépend pas des notifications. Un canal courriel pourra être ajouté à la même classe de notification sans changer le reste.

## ADR-014 : tests PHPUnit et test de concurrence par processus réels

Statut : Acceptée

Date : 13/11/2026

Contexte : le critère de réussite n° 1 porte sur des soumissions simultanées. Un test ordinaire s'exécute dans un seul processus et, le plus souvent, dans une transaction annulée à la fin : il ne peut pas montrer l'attente sur un verrou.

Options : Pest ou PHPUnit pour l'écriture des tests ; pour la concurrence, simuler deux appels successifs dans le même processus, ou lancer plusieurs processus PHP réels contre MySQL.

Décision : PHPUnit, livré avec Laravel. La suite `tests/Concurrency` lance des processus réels en parallèle contre la base MySQL de test, sans transaction englobante, et vérifie l'état final.

Conséquences : ces tests sont plus lents et ont leur propre suite dans `phpunit.xml`. Ils exigent MySQL (ADR-002) et nettoient leurs données eux-mêmes. Ils font partie de la validation de toute modification de `StockService`.

## ADR-015 : graphiques du tableau de bord sans bibliothèque

Statut : Acceptée

Date : 13/11/2026

Contexte : le tableau de bord affiche quatre indicateurs chiffrés et une répartition du stock entre deux emplacements.

Options : Chart.js ou une bibliothèque équivalente, soit un poids de JavaScript et une dépendance npm pour un seul graphique ; ou des barres en HTML et CSS.

Décision : barres en HTML et CSS, sans bibliothèque de graphiques.

Conséquences : la politique CSP interdit l'attribut `style`, donc les barres utilisent l'élément `<progress>` ou des classes CSS. Un besoin de courbes d'évolution rouvrirait la question par une nouvelle décision.

## ADR-016 : réception directe sans commande fournisseur

Statut : Acceptée

Date : 13/11/2026

Contexte : le circuit normal est la commande fournisseur suivie d'une ou plusieurs réceptions. Une PME fait aussi des achats de dépannage, sans commande formalisée, et peut recevoir plus que la quantité commandée.

Options : imposer une commande avant toute entrée, ce qui pousse à créer des commandes fictives après coup ; ou accepter une entrée directe rattachée à un fournisseur.

Décision : un bon de réception peut exister sans commande (`goods_receipts.purchase_order_id` nul). Il reste rattaché à un fournisseur, à un emplacement et à un utilisateur, et produit les mêmes mouvements de type `purchase`.

Conséquences : `PurchaseService` expose `receiveDirect` à côté de `receive`. Les états d'achats doivent distinguer les réceptions sur commande des réceptions directes. Un excédent livré sur une commande se saisit en réception directe, puisque le cumul reçu ne peut pas dépasser le commandé. L'accès suit les mêmes droits que la réception sur commande.

## ADR-017 : références attribuées par une table de compteurs

Statut : Acceptée

Date : 13/11/2026

Contexte : la première version du modèle dérivait la référence d'un document de sa clé primaire, après insertion. La colonne `reference` étant unique et obligatoire, il fallait une valeur provisoire, et pour un mouvement manuel il fallait modifier une ligne de `stock_movements`, table en ajout seul. Le SKU posait la même difficulté, et `categories` n'avait pas de code pour le préfixer.

Options : colonne `reference` acceptant `NULL` puis mise à jour ; valeur provisoire unique ; table de compteurs verrouillée dans la transaction.

Décision : table `document_sequences(prefix, year, last_number)` avec contrainte d'unicité sur `(prefix, year)`. `ReferenceGenerator::next()` verrouille la ligne, l'incrémente et rend la référence avant l'insertion. `categories` reçoit une colonne `code` unique, préfixe des SKU.

Conséquences : aucune ligne n'est modifiée après insertion. Les numéros se suivent sans trou tant que les transactions aboutissent, une transaction annulée ne consomme pas de numéro. Deux opérations du même type se sérialisent brièvement sur le compteur, ce qui est sans effet à l'échelle d'une PME. Le compteur est toujours verrouillé avant les lignes de stock pour garder un ordre de verrouillage unique.

## ADR-018 : pas d'annulation d'une réception ni d'un inventaire validé

Statut : Acceptée

Date : 13/11/2026

Contexte : la règle générale veut qu'un document validé s'annule par des mouvements inverses. Pour une vente ou un transfert, l'inverse est simple. Pour une réception, la marchandise a pu être vendue ou transférée entre-temps, et il faudrait recalculer l'avancement de la commande. Pour un inventaire validé, l'inverse réintroduirait un écart que le comptage avait justement constaté. Par ailleurs, une commande partiellement reçue restait sans issue quand le fournisseur ne livrait jamais le reliquat.

Options : annulation complète avec type de mouvement dédié et colonnes d'annulation sur `goods_receipts` et `inventory_sessions` ; ou correction par ajustement manuel justifié.

Décision : une réception et un inventaire validé ne s'annulent pas. L'erreur se corrige par un `manual_adjustment` dont le motif cite la référence du document. Une commande partiellement reçue peut être soldée : statut `closed`, motif obligatoire dans `closure_reason`.

Conséquences : moins d'écrans et de cas à tester. En contrepartie, `quantity_received` d'une commande reste celle saisie, même après correction du stock ; l'ajustement et son motif font foi. Si le client veut une vraie annulation de réception, elle s'ajoutera par un type de mouvement `purchase_cancellation` sans toucher à l'existant.

## ADR-019 : audit des opérations de stock dans la transaction

Statut : Acceptée

Date : 13/11/2026

Contexte : avec un audit écrit par un listener après le commit, un listener en échec laisse une opération de stock validée sans ligne dans `audit_logs`. Le cahier des charges demande un journal qui enregistre chaque opération sur les stocks.

Décision : pour les opérations de stock, le service métier appelle `AuditService::record()` dans la transaction. Les notifications d'alerte restent émises après le commit. L'audit du catalogue, des connexions et des exports passe par l'événement `AuditableActionOccurred`.

Conséquences : l'opération et sa trace sont validées ensemble. Une erreur d'écriture de l'audit annule l'opération, ce qui est le comportement voulu pour un outil de contrôle.

## ADR-020 : emplacements créés par le seeder

Statut : Acceptée

Date : 13/11/2026

Contexte : le besoin porte sur un entrepôt central et un point de vente. Le modèle accepte un nombre quelconque d'emplacements, mais un écran de création imposerait de créer les lignes `stock_levels` de tous les produits et de régler l'affectation des utilisateurs.

Décision : les deux emplacements sont créés par le seeder de référence. L'administrateur peut modifier leur nom et leur adresse, pas en créer ni en supprimer depuis l'interface.

Conséquences : `StockService::lockLevels` crée à zéro une ligne de stock manquante, ce qui rend l'ajout d'un troisième emplacement possible plus tard par un simple seeder ou un écran dédié.
