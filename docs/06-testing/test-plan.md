# Plan et bilan des tests

Tous les tests tournent sur MySQL 8.4, jamais sur SQLite : les verrous, les contraintes `CHECK` et le comportement transactionnel sont ceux de la production (D-014). La base de test est le service `db-test` de `compose.yaml`, dont les données vivent en mémoire vive.

## Suites

| Suite | Dossier | Rôle | Lancement |
| :-- | :-- | :-- | :-- |
| Unit | `tests/Unit` | Calculs sans base de données : périodes du tableau de bord (`PeriodTest`, 26 tests) | `php artisan test --testsuite=Unit` |
| Feature | `tests/Feature` | Règles métier, écrans, droits, exports | `php artisan test --testsuite=Feature` |
| Concurrency | `tests/Concurrency` | Ventes simultanées par processus PHP séparés | `php artisan test --testsuite=Concurrency` |

`php artisan test` sans option lance Unit puis Feature (`defaultTestSuite="Unit,Feature"` dans `phpunit.xml`), jamais Concurrency.

La suite Unit reste volontairement courte : les règles qui comptent (stock, verrous, transactions, droits) ne se vérifient qu'avec la base et relèvent de Feature. Elle couvre les calculs isolés qui n'ont pas besoin de MySQL, à ce jour les périodes du tableau de bord (`App\Support\Period`). Les autres calculs isolés (clé EAN-13, formats) sont couverts au travers des tests fonctionnels.

## Contenu par fichier

| Fichier | Tests | Ce qui est vérifié |
| :-- | --: | :-- |
| `Feature/Stock/StockServiceTest.php` | 16 | Une ligne de stock par emplacement, stock avant et après, refus d'une sortie supérieure au stock sans aucune écriture, motif obligatoire, numéro de bon des mouvements manuels, historique non modifiable, contrainte `CHECK` de la base, liste complète des manques, transaction obligatoire pour verrouiller |
| `Feature/Stock/SalesServiceTest.php` | 7 | Vente qui décrémente et trace, refus en bloc si une ligne manque, cumul d'un produit saisi deux fois, stock de l'entrepôt non vendable en boutique, prix figé au jour de la vente, annulation motivée qui restitue le stock |
| `Feature/Stock/TransferAndPurchaseServiceTest.php` | 7 | Transfert à somme constante, refus si la source manque, annulation refusée si la marchandise est déjà vendue, commande reçue en deux livraisons, sur-réception refusée, solde d'une commande partielle, brouillon modifiable, réception directe |
| `Feature/Stock/InventoryServiceTest.php` | 8 | Cycle complet avec régularisation, vente avant comptage non retranchée deux fois (D-007), vente après comptage préservée, écart non justifié bloquant, une session ouverte par emplacement, inventaire par catégorie, renvoi en comptage, validation refusée si le stock deviendrait négatif, session validée non annulable |
| `Feature/Stock/StockIntegrityCommandTest.php` | 2 | `stock:verify` valide un historique produit par les services et détecte une modification directe |
| `Feature/Auth/AuthenticationTest.php` | 15 | Connexion, refus et trace des échecs, message identique pour un compte inconnu, compte désactivé, blocage après cinq échecs, déconnexion d'un compte désactivé en cours de session, mot de passe imposé à changer, règle des 12 caractères, en-têtes de sécurité et CSP sans `unsafe-inline` |
| `Feature/Catalog/ProductTest.php` | 22 | Référence et EAN-13 générés, unicité, validation serveur, droits du vendeur, prix d'achat masqué au vendeur, image contrôlée sur son contenu et servie aux seuls connectés, historique conservé à la suppression, recherche par nom, référence et code-barres en moins de 500 ms sur 800 produits, caractères spéciaux traités comme du texte, échappement XSS |
| `Feature/Security/PermissionMatrixTest.php` | 4 (dont 32 cas) | Chaque route sensible appelée par chacun des trois profils : 403 attendu hors matrice ; vendeur limité à ses ventes et à son point de vente ; compte désactivé coupé des points JSON |
| `Feature/Modules/OperationsHttpTest.php` | 14 | Parcours complets par HTTP : vente, refus détaillé, double envoi bloqué, annulation, mouvements manuels, transfert et bon, commande en deux livraisons, inventaire avec comptage JSON et validation, alerte unique au franchissement du seuil, KPI égaux aux agrégats recalculés, exports CSV (BOM, formules neutralisées) et PDF, gestion des comptes, journal d'audit, affichage de tous les écrans |
| `Concurrency/ConcurrentSalesTest.php` | 3 | Deux ventes de 6 sur un stock de 10 : une seule passe ; huit ventes de 2 sur 10 : exactement cinq, mouvements enchaînés sans lecture périmée ; paniers croisés sans interblocage |

## Couverture des critères de réussite du cahier des charges

| Critère | Preuve |
| :-- | :-- |
| Zéro stock négatif, même lors de soumissions simultanées | `ConcurrentSalesTest` (processus réels), `StockServiceTest::test_la_base_refuse_un_stock_negatif_meme_si_le_service_est_contourne` |
| Chaque variation de stock correspond à un mouvement | `stock:verify` sur la base de démonstration : 1 470 mouvements rejoués, aucun écart ; `StockServiceTest::test_la_somme_des_mouvements_egale_le_niveau_de_stock` |
| Jeu de données réaliste | `DemoSeeder` : 61 produits, 7 catégories, 6 fournisseurs, 6 comptes, plus de 1 400 mouvements sur trois mois |
| Recherche produit sous 500 ms | `ProductTest::test_la_recherche_repond_en_moins_de_500_ms_sur_800_produits` |

## Dernières exécutions

| Date | Suite | Résultat | Durée |
| :-- | :-- | :-- | :-- |
| 14/11/2026 | Unit et Feature | 124 tests réussis, 571 vérifications | 20 min 28 s |
| 14/11/2026 | `StockIntegrityCommandTest` (ajouté ensuite) | 2 tests réussis | 30 s |
| 14/11/2026 | Concurrency, 5 tours par test | 3 tests réussis, 175 vérifications | 2 min 38 s |
| 14/11/2026 | `stock:verify` sur la démonstration, après pilotage HTTP | 1 470 mouvements, aucun écart | |

Les durées sont celles d'un poste Windows où le code est lu à travers le montage Docker ; sur Linux, elles sont nettement plus courtes.

## Anomalies trouvées par les tests

| Réf. | Constat | Correction |
| :-- | :-- | :-- |
| A-006 | Interblocage MySQL à la création d'un compteur de références avec plusieurs vendeurs simultanés | Création et incrément en une instruction atomique |
| A-007 | Fichier texte renommé en `.png` accepté comme image | Contrôle du contenu par `getimagesize` |
| A-008 | Tableau de bord en erreur 500 : colonne `is_active` ambiguë dans la répartition par emplacement | Colonnes qualifiées par leur table |

## Ce qui reste à faire

- Un test de bout en bout dans un vrai navigateur (Playwright) sur le parcours de vente : les interactions JavaScript (panier, comptage d'inventaire, filtres instantanés) ne sont aujourd'hui vérifiées qu'au travers de leurs points JSON.
- Une vérification manuelle sur tablette réelle de l'écran de comptage.
- Un passage à 50 tours des tests de concurrence (`CONCURRENCY_ROUNDS=50`), prévu au backlog et non encore exécuté.
