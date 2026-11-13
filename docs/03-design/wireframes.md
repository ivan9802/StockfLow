# Wireframes StockFlow

Maquettes en basse fidélité des écrans de StockFlow. Elles fixent la structure, le contenu et la hiérarchie de chaque page. Les couleurs, la typographie et le détail des composants sont dans `docs/03-design/ui-guidelines.md`.

Les données sont fictives : entreprise « Ivoire Bureautique & Tech », un entrepôt central (`ENT`) à Yopougon et une boutique (`PDV`) au Plateau. Les statuts, champs et références suivent `docs/02-architecture/database-design.md`.

## Conventions de lecture

| Notation | Signification |
| :-- | :-- |
| `[[ Texte ]]` | Bouton principal de la page |
| `[ Texte ]` | Bouton secondaire |
| `<Texte>` | Lien |
| `[_______]` | Champ de saisie |
| `( Texte v )` | Liste déroulante |
| `{Texte}` | Badge de stock ou de statut |
| `(o)` et `( )` | Bouton radio coché, non coché |
| `[x]` et `[ ]` | Case cochée, non cochée |
| `^` ou `v` après un en-tête | Colonne triée, sens du tri |
| `*` après un libellé | Champ obligatoire |
| `...` | Menu d'actions de ligne |

Rôles : A pour administrateur (`admin`), G pour gestionnaire de stock (`stock_manager`), V pour vendeur (`sales_agent`).

Le premier écran authentifié (tableau de bord) montre le gabarit complet avec la barre latérale et la barre supérieure. Les écrans suivants ne montrent que la zone de contenu, sous la barre supérieure.

Les routes citées dans les notes sont indicatives. Elles seront arrêtées avec l'architecte. Toute route appelée en Fetch vérifie le rôle côté serveur.

## 1. Connexion

```text
+--------------------------------------------------------------------------------------------------+
|                                                                                                  |
|                           StockFlow                                                              |
|                           Ivoire Bureautique & Tech                                              |
|                                                                                                  |
|                  +--------------------------------------------------+                            |
|                  |  Connexion                                       |                            |
|                  |                                                  |                            |
|                  |  Adresse e-mail                                  |                            |
|                  |  [ fatou.diabate@ivoirebureautique.example     ] |                            |
|                  |                                                  |                            |
|                  |  Mot de passe                                    |                            |
|                  |  [ ************                      (Afficher) ] |                           |
|                  |                                                  |                            |
|                  |  [x] Rester connecté sur cet appareil            |                            |
|                  |                                                  |                            |
|                  |  [[            Se connecter                   ]] |                            |
|                  |                                                  |                            |
|                  |  Mot de passe oublié ? Adressez-vous à votre     |                            |
|                  |  administrateur, qui vous en attribuera un       |                            |
|                  |  nouveau.                                        |                            |
|                  +--------------------------------------------------+                            |
|                                                                                                  |
|     Variante après un refus, au-dessus des champs :                                              |
|                  +--------------------------------------------------+                            |
|                  | (!) Adresse e-mail ou mot de passe incorrect.    |                            |
|                  +--------------------------------------------------+                            |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : le focus est sur l'e-mail à l'ouverture. Entrée soumet. Le bouton « Afficher » bascule le champ entre masqué et lisible. Après un refus, l'e-mail saisi est conservé, le mot de passe est vidé et le focus va sur le bandeau.
- Attributs : `autocomplete="username"` et `autocomplete="current-password"`. Pas d'astérisque, les deux champs sont obligatoires.
- Après plusieurs refus consécutifs, le message devient « Trop de tentatives. Réessayez dans 1 minute. »
- Fetch : aucun, formulaire `POST` classique avec jeton CSRF.
- Rôles : écran public. Après connexion, A et G arrivent sur le tableau de bord, V sur le point de vente.
- Tablette : même carte, centrée, champs de 44 px de haut. À 360 px, la carte occupe la largeur moins 16 px de marge.

## 2. Tableau de bord (administrateur et gestionnaire)

```text
+--------------------------------------------------------------------------------------------------+
| StockFlow           | [ Rechercher un produit, un SKU, un code-barres  / ]    (Alertes 4)  AK v  |
|                     | ------------------------------------------------------------------------   |
|  Tableau de bord  < | Tableau de bord                               [[ Nouvelle réception ]]     |
|                     | Vendredi 13/11/2026, mis à jour à 14:35 <Actualiser>                       |
| CATALOGUE           |                                                                            |
|  Produits           | +----------------------+ +----------------------+ +----------------------+ |
|  Catégories         | | VALEUR DU STOCK      | | RÉFÉRENCES ACTIVES   | | MOUVEMENTS DU JOUR   | |
|  Fournisseurs       | | 148 620 000 F CFA    | | 812                  | | 64                   | |
|                     | | au prix d'achat      | | sur 838 au catalogue | | 41 sorties, 23 entr. | |
| STOCK               | +----------------------+ +----------------------+ +----------------------+ |
|  État des stocks    | +----------------------+ +----------------------+ +----------------------+ |
|  Mouvements         | | /!\ SOUS SEUIL       | | (x) RUPTURES         | | VENTES DU JOUR       | |
|  Transferts         | | 37                   | | 9                    | | 2 845 500 F CFA      | |
|  Inventaires      1 | | <Voir la liste>      | | <Voir la liste>      | | 23 ventes            | |
|                     | +----------------------+ +----------------------+ +----------------------+ |
| ACHATS              |                                                                            |
|  Commandes        3 | Produits à réapprovisionner                    <Voir les 46 produits>      |
|  Réceptions         | ------------------------------------------------------------------------   |
|                     | Produit                         Stock  Seuil  État          Fournisseur    |
| VENTES              | Écran Dell 24 pouces P2422H         0      4  {Rupture}     Lagune Distr.  |
|  Point de vente     | Routeur TP-Link Archer C6           0      5  {Rupture}     Sahel Réseaux  |
|  Historique         | Cartouche d'encre Canon PG-445      6     12  {Sous seuil}  Océan Consom.  |
|                     | Ordinateur Lenovo ThinkPad E14      3      3  {Sous seuil}  Ébrié Tech     |
| PILOTAGE            |                                                                            |
|  Alertes          4 | Répartition du stock par emplacement (valeur au prix d'achat)              |
|  Rapports           | ------------------------------------------------------------------------   |
|                     | Entrepôt central    [#################################.......]   82 %      |
| ADMINISTRATION      |                     121 480 000 F CFA                                      |
|  Utilisateurs       | Boutique du Plateau [#######.................................]   18 %      |
|  Journal d'audit    |                     27 140 000 F CFA                                       |
|                     |                                                                            |
|                     | Dernières opérations                         <Voir tous les mouvements>    |
|                     | ------------------------------------------------------------------------   |
|                     | Heure  Type            Bon             Produit                Lieu   Qté   |
|                     | 14:31  Vente           BS-2026-000131  Rame papier A4 80 g    PDV     -5   |
|                     | 14:12  Vente           BS-2026-000130  Souris Logitech M185   PDV     -1   |
|                     | 11:50  Sortie interne  BM-2026-000312  Clé USB SanDisk 32 Go  ENT     -2   |
| --------------------| 10:42  Vente           BS-2026-000128  Imprimante HP M404dn   PDV     -1   |
|  Entrepôt central   | 08:40  Ajust. manuel   BM-2026-000311  Câble HDMI 2 m         ENT     +3   |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : chaque carte KPI est un lien vers la liste filtrée correspondante (produits sous seuil, ruptures, mouvements du jour, ventes du jour). Une ligne de « Produits à réapprovisionner » ouvre la fiche produit. Une ligne de « Dernières opérations » ouvre le document source. L'action principale est « Nouvelle réception », l'opération la plus fréquente du gestionnaire.
- La liste « Produits à réapprovisionner » montre les ruptures puis les produits sous seuil, 5 lignes au plus. Le total (46) est la somme des deux cartes (37 et 9).
- Les barres de répartition sont des blocs HTML dont la largeur est fixée en pourcentage (D-015). Elles sont doublées d'un tableau lisible par lecteur d'écran. Un emplacement supplémentaire ajoute une ligne.
- Fetch : `GET /dashboard/kpis` toutes les 60 secondes et au clic sur « Actualiser », pour rafraîchir les six cartes et les dernières opérations sans recharger la page. Suspendu quand l'onglet est masqué. `GET /notifications/unread-count` pour la cloche.
- Rôles : A et G. La section « Administration » du menu n'est rendue que pour A.
- Tablette : barre latérale en rail de 64 px. Les cartes KPI passent par 3 puis par 2 en dessous de 992 px. Les blocs s'empilent sur une colonne. La colonne « Fournisseur » et la colonne « Bon » sont masquées. À 360 px, les cartes KPI sont sur une colonne et les tableaux deviennent des listes de cartes.

## 3. Tableau de bord, variante vendeur

```text
+--------------------------------------------------------------------------------------------------+
| Bonjour, Fatou Diabaté                                               [[ Nouvelle vente ]]        |
| Boutique du Plateau, vendredi 13/11/2026                                                         |
|                                                                                                  |
| +-----------------------------+ +-----------------------------+ +----------------------------+   |
| | MES VENTES DU JOUR          | | /!\ SOUS SEUIL EN BOUTIQUE  | | (x) RUPTURES EN BOUTIQUE   |   |
| | 1 124 500 F CFA             | | 12                          | | 5                          |   |
| | 9 ventes                    | | <Voir la liste>             | | <Voir la liste>            |   |
| +-----------------------------+ +-----------------------------+ +----------------------------+   |
|                                                                                                  |
| Mes dernières ventes                                                 <Voir mon historique>       |
| ----------------------------------------------------------------------------------------------   |
| Heure   Bon de sortie    Client                        Articles     Montant (F CFA)   Statut     |
| 14:31   BS-2026-000131   -                                    5              17 500   {Validée}  |
| 14:12   BS-2026-000130   Mme Adjoua Brou                      1               7 500   {Validée}  |
| 10:42   BS-2026-000128   Cabinet Konan & Associés             8             326 500   {Validée}  |
| 09:05   BS-2026-000126   M. Ibrahim Touré                     2             124 000   {Validée}  |
|                                                                                                  |
| En rupture à la boutique, disponibles à l'entrepôt                                               |
| ----------------------------------------------------------------------------------------------   |
| Produit                                   Boutique    Entrepôt                                   |
| Onduleur APC Back-UPS 650 VA                     0          11                                   |
| Switch D-Link 8 ports Gigabit                    0          24                                   |
| Clavier USB Logitech K120 AZERTY                 0          35                                   |
+--------------------------------------------------------------------------------------------------+
```

- Contenu réduit : pas de valeur du stock, pas de prix d'achat, pas de mouvements des autres emplacements. Les compteurs portent sur l'emplacement d'affectation du vendeur (D-009).
- Interactions : « Nouvelle vente » ouvre le point de vente. Une ligne de vente ouvre le bon de sortie. Le dernier bloc aide à répondre à un client : le produit manque en boutique, il peut venir de l'entrepôt.
- Fetch : `GET /dashboard/kpis`, qui renvoie le jeu réduit selon le rôle.
- Rôles : V.
- Tablette : trois cartes sur une ligne jusqu'à 768 px, empilées en dessous. La colonne « Client » est masquée sous 768 px.

## 4. Liste des produits

```text
+--------------------------------------------------------------------------------------------------+
| Catalogue > Produits                                                                             |
| Produits                                               [ Exporter (CSV) ]  [[ Nouveau produit ]] |
|                                                                                                  |
| [ Rechercher par libellé, SKU ou code-barres    ]  ( Toutes les catégories v )                   |
| ( Tous les fournisseurs v )  ( État du stock : tous v )  ( Actifs v )          <Réinitialiser>   |
|                                                                                                  |
| Filtres actifs : {Catégorie : Consommables d'impression x}        812 produits, 1 à 25 affichés  |
| ----------------------------------------------------------------------------------------------   |
| Libellé ^                      SKU        Catégorie       Prix vente  Stock  Seuil  État         |
|                                                              (F CFA)  total                      |
| ----------------------------------------------------------------------------------------------   |
| Cartouche d'encre Canon PG-445 CON-000125 Consommables        14 500      6     12  {Sous seuil} |
| Cartouche toner HP 59A noir    CON-000118 Consommables        62 000     17     10  {En stock}   |
| Clé USB SanDisk 32 Go          ACC-000058 Périphériques        5 000     88     30  {En stock}   |
| Écran Dell 24 pouces P2422H    ACC-000023 Périphériques      118 000      0      4  {Rupture}    |
| Imprimante HP LaserJet M404dn  IMP-000042 Imprimantes        185 000      8      5  {En stock}   |
| Ordinateur Lenovo ThinkPad E14 ORD-000007 Ordinateurs        465 000      3      3  {Sous seuil} |
| Rame papier A4 80 g (500 f.)   PAP-000090 Papeterie            3 500    420    150  {En stock}   |
| Routeur TP-Link Archer C6      RES-000031 Réseau              28 500      0      5  {Rupture}    |
| Souris sans fil Logitech M185  ACC-000063 Périphériques        7 500     46     20  {En stock}   |
| ----------------------------------------------------------------------------------------------   |
| 1 à 25 sur 812      Lignes par page ( 25 v )              < Précédent  1  2  3 ... 33  Suivant > |
|                                                                                                  |
| Menu d'actions en fin de ligne (non dessiné) : Modifier, Voir la fiche de stock, Désactiver      |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : la recherche filtre la liste pendant la frappe. Les listes déroulantes filtrent dès le choix. Un clic sur un en-tête trie. Un clic sur une ligne ouvre la fiche produit. « État du stock » propose : tous, en stock, sous seuil, rupture.
- Fetch : `GET /products?q=&category=&supplier=&stock=&active=&sort=&dir=&page=` avec l'en-tête `Accept: application/json`, 300 ms après la dernière frappe. La réponse remplace le corps du tableau et la pagination, l'URL est mise à jour par `history.replaceState`. Sans JavaScript, le même formulaire `GET` recharge la page.
- Le stock affiché est le total des emplacements, comparé à `alert_threshold` (D-008).
- Rôles : A, G, V. Pour V : pas de bouton « Nouveau produit », pas d'export, pas de menu « ... », pas de filtre fournisseur, et le filtre d'activité est figé sur « Actifs ».
- Tablette : colonnes SKU et Catégorie masquées, le SKU passe sous le libellé. Filtres repliés derrière « Filtres (1) ». À 360 px, une carte par produit : libellé, SKU, badge, stock et prix.

## 5. Fiche produit

```text
+--------------------------------------------------------------------------------------------------+
| Catalogue > Produits > IMP-000042                                                                |
| Imprimante HP LaserJet Pro M404dn   {En stock}        [ Imprimer l'étiquette ]  [[ Modifier ]]   |
| IMP-000042   Imprimantes et scanners   {Actif}                                                   |
|                                                                                                  |
| +--------------+  Prix de vente      185 000 F CFA      +--------------------------------------+ |
| |              |  Prix d'achat       152 000 F CFA      | CODE-BARRES (EAN-13)                 | |
| |   image du   |  Marge unitaire      33 000 F CFA      |  || ||| | || ||| || | ||| | || |||   | |
| |   produit    |  Seuil d'alerte     5 (stock total)    |  || ||| | || ||| || | ||| | || |||   | |
| |              |  Fournisseur        <Lagune Distrib.>  |  2 000000 000428                     | |
| +--------------+  Délai moyen        7 jours            +--------------------------------------+ |
|                                                                                                  |
| Description : imprimante laser monochrome A4, recto verso automatique, réseau Ethernet,          |
| 38 pages par minute.                                                                             |
|                                                                                                  |
| Stock par emplacement                                                       [ Transférer ]       |
| ----------------------------------------------------------------------------------------------   |
| Emplacement               Quantité   Seuil de réassort   État          Valeur (F CFA)            |
| Entrepôt central (ENT)           6                   -   {En stock}           912 000            |
| Boutique du Plateau (PDV)        2                   2   {Sous seuil}         304 000            |
| ----------------------------------------------------------------------------------------------   |
| Total                            8                   5   {En stock}         1 216 000            |
|                                                                                                  |
| Fiche de stock            ( Tous les emplacements v )  Du [01/11/2026] au [13/11/2026]  [ CSV ]  |
| ----------------------------------------------------------------------------------------------   |
| Date              Type         Bon              Lieu   Entrée  Sortie   Avant  Après  Par        |
| 13/11/2026 10:42  Vente        BS-2026-000128   PDV                 1       3      2  F. Diabaté |
| 12/11/2026 16:05  Transfert    TR-2026-000019   PDV         2               1      3  M. Bamba   |
| 12/11/2026 16:05  Transfert    TR-2026-000019   ENT                 2       8      6  M. Bamba   |
| 10/11/2026 09:20  Réception    BR-2026-000057   ENT         6               2      8  M. Bamba   |
| ----------------------------------------------------------------------------------------------   |
| 1 à 4 sur 4                                                                                      |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : le lien du fournisseur ouvre sa fiche. Une référence de bon ouvre le document source. « Transférer » ouvre le formulaire de transfert avec le produit déjà en ligne. « Imprimer l'étiquette » ouvre une page imprimable avec le code-barres, le libellé et le prix de vente. Le seuil de réassort d'un emplacement se modifie depuis le formulaire produit.
- Le code-barres est un SVG produit par le serveur, avec un texte alternatif qui donne le numéro. Les 13 chiffres sont répétés en police de code sous les barres.
- Les colonnes « Avant » et « Après » sont celles de l'emplacement du mouvement (`stock_before`, `stock_after`). Un transfert produit deux lignes de même référence.
- Fetch : `GET /products/{id}/movements?warehouse=&from=&to=&page=` pour filtrer la fiche de stock sans recharger la page.
- Rôles : A, G, V. Pour V : ni prix d'achat, ni marge, ni valeur, ni boutons « Modifier » et « Transférer », ni fiche de stock. V voit le stock par emplacement avec les badges.
- Tablette : l'image, les prix et le code-barres s'empilent. La fiche de stock masque « Avant » et « Par », puis passe en cartes sous 768 px.

## 6. Formulaire produit (création et modification)

```text
+--------------------------------------------------------------------------------------------------+
| Catalogue > Produits > Nouveau produit                                                           |
| Nouveau produit                                                                                  |
| Les champs marqués d'un astérisque sont obligatoires.                                            |
|                                                                                                  |
| IDENTIFICATION                                                                                   |
| Libellé *                                                                                        |
| [ Imprimante HP LaserJet Pro M404dn                                   ]                          |
| Catégorie *                               Fournisseur principal                                  |
| ( Imprimantes et scanners v )             ( Lagune Distribution Informatique v )                 |
| SKU                                       Code-barres                                            |
| [ IMP-000042      ]                       [ 2000000000428   ]                                    |
| Laissez vide : la référence est générée   Laissez vide pour générer un code interne, ou          |
| à partir de la catégorie.                 saisissez le code du fabricant (13 chiffres).          |
| Description                                                                                      |
| [                                                                     ]                          |
| [                                                                     ]                          |
|                                                                                                  |
| PRIX                                                                                             |
| Prix d'achat *                            Prix de vente *                                        |
| [      152 000 ] F CFA                    [      185 000 ] F CFA                                 |
|                                           Marge : 33 000 F CFA (21,7 % du prix d'achat)          |
|                                                                                                  |
| SEUILS                                                                                           |
| Seuil d'alerte (stock total) *            Seuil de réassort, Boutique du Plateau                 |
| [     5 ]                                 [     2 ]                                              |
| Une alerte est émise quand le stock       Facultatif. Signale qu'il faut réassortir              |
| total atteint ce nombre.                  la boutique depuis l'entrepôt.                         |
|                                                                                                  |
| IMAGE ET ÉTAT                                                                                    |
| Image                                     [x] Produit actif                                      |
| [ Choisir un fichier ]  aucun fichier     Un produit inactif n'est plus vendable ni              |
| JPEG, PNG ou WebP, 2 Mo au plus.          commandable.                                           |
|                                                                                                  |
| [[ Enregistrer le produit ]]   <Annuler>                                                         |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : en création, les champs SKU et code-barres sont vides et leur aide explique la génération. En modification, le SKU est en lecture seule. La marge se recalcule à la saisie des prix. Un avertissement non bloquant s'affiche si le prix de vente est inférieur au prix d'achat. Le choix d'une image affiche un aperçu.
- Aucun champ de quantité : le stock initial entre par une réception ou un ajustement, jamais par la fiche produit.
- Champs de prix : `inputmode="numeric"`, mise en forme des milliers à la sortie du champ. Seuils : `inputmode="numeric"`.
- Erreurs : sous chaque champ, avec un bandeau récapitulatif en tête. Exemple : « La référence IMP-000042 existe déjà. Laissez le champ vide pour qu'elle soit générée. »
- Fetch : aucun appel obligatoire. Envoi en `POST` avec `enctype="multipart/form-data"`.
- Rôles : A, G.
- Tablette : une seule colonne sous 768 px. La barre « Enregistrer le produit » reste collée en bas de l'écran.

## 7. Catégories

```text
+--------------------------------------------------------------------------------------------------+
| Catalogue > Catégories                                                                           |
| Catégories                                                        [[ Nouvelle catégorie ]]       |
|                                                                                                  |
| Nom ^                          Description                                  Produits             |
| ----------------------------------------------------------------------------------------------   |
| Consommables d'impression      Toners, cartouches d'encre, rubans                 186  [/] [x]   |
| Imprimantes et scanners        Laser, jet d'encre, multifonctions                  74  [/] [x]   |
| Ordinateurs                    Portables, unités centrales, tout-en-un             92  [/] [x]   |
| Papeterie et fournitures       Papier, classement, petit matériel                 148  [/] [x]   |
| Périphériques et accessoires   Écrans, claviers, souris, stockage, onduleurs      231  [/] [x]   |
| Réseau et connectique          Routeurs, switchs, câbles                          107  [/] [x]   |
| ----------------------------------------------------------------------------------------------   |
| 6 catégories                                                                                     |
|                                                                                                  |
| Fenêtre « Nouvelle catégorie » :                                                                 |
|         +------------------------------------------------------------+                           |
|         | Nouvelle catégorie                                     (x) |                           |
|         | Nom *                                                      |                           |
|         | [                                             ]            |                           |
|         | Description                                                |                           |
|         | [                                             ]            |                           |
|         |                    <Fermer>  [[ Créer la catégorie ]]      |                           |
|         +------------------------------------------------------------+                           |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : création et modification dans une fenêtre modale, la liste étant courte. `[/]` modifie, `[x]` supprime. Le nombre de produits est un lien vers la liste des produits filtrée.
- La suppression d'une catégorie qui contient des produits est refusée. Le bouton reste actif et explique : « Cette catégorie contient 186 produits. Déplacez-les vers une autre catégorie avant de la supprimer. » Une catégorie vide se supprime après confirmation.
- Fetch : `POST /categories` et `PUT /categories/{id}` depuis la fenêtre, avec affichage des erreurs de validation dans la fenêtre sans la fermer. Sans JavaScript, les mêmes formulaires s'ouvrent en pleine page.
- Rôles : A, G.
- Tablette : la colonne « Description » est masquée sous 768 px. Les boutons d'action font 44 px.

## 8. Fournisseurs

```text
+--------------------------------------------------------------------------------------------------+
| Catalogue > Fournisseurs                                                                         |
| Fournisseurs                                                    [[ Nouveau fournisseur ]]        |
|                                                                                                  |
| [ Rechercher par raison sociale ou interlocuteur ]   ( Actifs v )            <Réinitialiser>     |
| ----------------------------------------------------------------------------------------------   |
| Raison sociale ^                   Interlocuteur        Téléphone             Délai   Produits   |
| ----------------------------------------------------------------------------------------------   |
| Comptoir Bureautique du Plateau    Mme Akissi Kouamé    +225 27 20 31 45 08    3 j.        96    |
| Ébrié Tech Import                  M. Olivier Gnagne    +225 07 08 45 12 36   14 j.       121    |
| Lagune Distribution Informatique   M. Seydou Koné       +225 05 46 77 20 19    7 j.       204    |
| Océan Consommables                 Mme Mariam Ouattara  +225 01 02 63 88 40    5 j.       186    |
| Papeterie Akwaba                   M. Jean-Marc Ehui    +225 07 59 10 34 72    2 j.       148    |
| Sahel Réseaux & Câblage            M. Lassina Traoré    +225 05 05 91 26 53   10 j.        83    |
| ----------------------------------------------------------------------------------------------   |
| 6 fournisseurs                                                                                   |
|                                                                                                  |
| Fiche d'un fournisseur (page de détail) :                                                        |
| Lagune Distribution Informatique   {Actif}            [ Nouvelle commande ]  [[ Modifier ]]      |
| Interlocuteur    M. Seydou Koné                                                                  |
| E-mail           commandes@lagune-distribution.example                                           |
| Téléphone        +225 05 46 77 20 19                                                             |
| Adresse          Treichville, zone portuaire, rue des Carrossiers, face à la pharmacie           |
| Délai moyen      7 jours                                                                         |
| Onglets :  [ Produits (204) ]  [ Commandes (37) ]  [ Réceptions (41) ]                           |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : une ligne ouvre la fiche. La fiche liste les produits dont il est le fournisseur principal, ses commandes et ses réceptions. Le formulaire reprend les champs du modèle : raison sociale, interlocuteur, e-mail, téléphone, adresse, délai moyen en jours, actif.
- Un fournisseur ne se supprime pas depuis la liste : il se désactive. Un fournisseur inactif n'est plus proposé dans les commandes.
- Le téléphone s'affiche au format `+225 XX XX XX XX XX` et forme un lien `tel:`.
- Fetch : filtrage de la liste comme pour les produits.
- Rôles : A, G.
- Tablette : la colonne « Interlocuteur » est masquée, le téléphone reste visible pour appeler d'un geste.

## 9. État des stocks par emplacement

```text
+--------------------------------------------------------------------------------------------------+
| Stock > État des stocks                                                                          |
| État des stocks                                             [ Exporter en CSV ]  [ PDF ]         |
|                                                                                                  |
| Emplacement :  [ Tous ]  [ Entrepôt central ]  [ Boutique du Plateau ]                           |
| [ Rechercher un produit           ]  ( Toutes les catégories v )  ( État : tous v )              |
|                                                                                                  |
| 812 références   Valeur : 148 620 000 F CFA   {Sous seuil} 37   {Rupture} 9                      |
| ----------------------------------------------------------------------------------------------   |
| Produit ^                          SKU          Entrepôt  Boutique   Total  Seuil  État          |
|                                                                                         Valeur   |
|                                                                                        (F CFA)   |
| ----------------------------------------------------------------------------------------------   |
| Cartouche d'encre Canon PG-445     CON-000125          4         2       6     12  {Sous seuil}  |
|                                                                                         66 000   |
| Cartouche toner HP 59A noir        CON-000118         14         3      17     10  {En stock}    |
|                                                                                        816 000   |
| Écran Dell 24 pouces P2422H        ACC-000023          0         0       0      4  {Rupture}     |
|                                                                                              0   |
| Imprimante HP LaserJet Pro M404dn  IMP-000042          6         2       8      5  {En stock}    |
|                                                                                      1 216 000   |
| Onduleur APC Back-UPS 650 VA       ACC-000012         11         0      11      6  {En stock}    |
|                                                                                        473 000   |
| Rame papier A4 80 g (500 f.)       PAP-000090        340        80     420    150  {En stock}    |
|                                                                                      1 113 000   |
| ----------------------------------------------------------------------------------------------   |
| 1 à 25 sur 812                                            < Précédent  1  2  3 ... 33  Suivant > |
+--------------------------------------------------------------------------------------------------+
```

- Lecture : dans la maquette, la valeur est écrite sous chaque ligne faute de largeur. À l'écran, c'est une colonne à droite de « État ».
- Interactions : les onglets d'emplacement changent le périmètre. Sur « Tous », le tableau montre une colonne par emplacement et le total comparé au seuil du produit. Sur un emplacement, il montre la quantité de cet emplacement, son seuil de réassort et la valeur locale. Une quantité à 0 dans un emplacement alors que le total est positif est signalée par une icône avec le texte « Rupture à la boutique ».
- Les exports reprennent les filtres actifs. Le PDF rappelle les filtres et la date d'édition.
- Fetch : `GET /stock?warehouse=&q=&category=&status=&sort=&page=` pour le filtrage. La ligne de synthèse est recalculée par le serveur avec les mêmes filtres.
- Rôles : A, G, V. Pour V : pas de valeur, pas d'export.
- Tablette : SKU sous le libellé, colonne « Valeur » masquée. À 360 px, une carte par produit avec les quantités par emplacement sur deux lignes.

## 10. Historique des mouvements

```text
+--------------------------------------------------------------------------------------------------+
| Stock > Mouvements                                                                               |
| Mouvements de stock                    [ Exporter en CSV ]  [ PDF ]  [[ Nouveau mouvement ]]     |
|                                                                                                  |
| Du [01/11/2026] au [13/11/2026]   <Aujourd'hui> <7 jours> <Ce mois> <Mois dernier>               |
| ( Tous les types v )  ( Tous les emplacements v )  ( Tous les utilisateurs v )                   |
| [ Produit, SKU ou numéro de bon   ]                                          <Réinitialiser>     |
|                                                                                                  |
| Filtres actifs : {Du 01/11/2026 au 13/11/2026 x}                 1 284 mouvements, 1 à 25        |
| ----------------------------------------------------------------------------------------------   |
| Date v            Type                 Bon              Produit                 Lieu             |
|                                                       Entrée  Sortie  Avant  Après  Par          |
| ----------------------------------------------------------------------------------------------   |
| 13/11/2026 14:31  Vente                BS-2026-000131   Rame papier A4 80 g     PDV              |
|                                                                    5     85     80  F. Diabaté   |
| 13/11/2026 11:50  Sortie interne       BM-2026-000312   Clé USB SanDisk 32 Go   ENT              |
|                   Motif : dotation du service comptabilité         2     70     68  M. Bamba     |
| 13/11/2026 08:40  Ajustement manuel    BM-2026-000311   Câble HDMI 2 m          ENT              |
|                   Motif : 3 câbles retrouvés en réserve    3             21     24  S. N'Guessan |
| 12/11/2026 17:48  Écart d'inventaire   INV-2026-000006  Souris Logitech M185    PDV              |
|                   Motif : 1 souris retrouvée en vitrine    1             11     12  A. Kouassi   |
| 12/11/2026 16:05  Transfert            TR-2026-000019   Imprimante HP M404dn    ENT              |
|                                                                    2      8      6  M. Bamba     |
| 12/11/2026 15:22  Annulation de vente  BS-2026-000119   Routeur TP-Link C6      PDV              |
|                   Motif : erreur de produit au comptoir    1              0      1  M. Bamba     |
| ----------------------------------------------------------------------------------------------   |
| 1 à 25 sur 1 284    Lignes par page ( 25 v )             < Précédent  1  2  3 ... 52  Suivant >  |
+--------------------------------------------------------------------------------------------------+
```

- Lecture : dans la maquette, chaque mouvement tient sur deux lignes faute de largeur. À l'écran, c'est une seule ligne de tableau, et le motif s'affiche sur une seconde ligne en texte secondaire quand il existe.
- Interactions : la référence du bon ouvre le document source, le libellé ouvre la fiche produit. « Nouveau mouvement » ouvre le formulaire de sortie interne ou d'ajustement. Aucune action de ligne : un mouvement ne se modifie pas et ne se supprime pas.
- Entrée et sortie sont deux colonnes distinctes, pour que le sens se lise sans couleur ni signe.
- Exports : CSV en flux pour toute la période filtrée, PDF en paysage. Au-delà de 5 000 lignes, le PDF est refusé avec le message « Réduisez la période ou utilisez l'export CSV. » Le seuil est à confirmer.
- Fetch : `GET /movements?from=&to=&type=&warehouse=&user=&q=&page=` pour le filtrage.
- Rôles : A, G.
- Tablette : colonnes « Avant » et « Par » masquées, filtres repliés. À 360 px, une carte par mouvement : date et type en tête, produit, quantité signée, bon.

## 11. Sortie interne ou ajustement

```text
+--------------------------------------------------------------------------------------------------+
| Stock > Mouvements > Nouveau mouvement                                                           |
| Nouveau mouvement de stock                                                                       |
| Les champs marqués d'un astérisque sont obligatoires.                                            |
|                                                                                                  |
| Nature du mouvement *                                                                            |
| (o) Sortie interne           usage propre, casse, don, échantillon                               |
| ( ) Ajustement manuel        correction ponctuelle d'une quantité, en plus ou en moins           |
|                                                                                                  |
| Emplacement *                                                                                    |
| ( Entrepôt central v )                                                                           |
|                                                                                                  |
| Produit *                                                                                        |
| [ Rechercher un produit par libellé, SKU ou code-barres               ]                          |
|   Clé USB SanDisk 32 Go   ACC-000058   70 disponibles à l'Entrepôt central          <Changer>    |
|                                                                                                  |
| Quantité à sortir *                                                                              |
| [ - ] [    2 ] [ + ]   sur 70 disponibles         Stock après l'opération : 68                   |
|                                                                                                  |
| Motif *                         Motifs courants : <Usage interne> <Casse> <Don> <Échantillon>    |
| [ Dotation du service comptabilité                                    ]                          |
| [                                                                     ]   34 / 500               |
|                                                                                                  |
| [[ Enregistrer la sortie ]]   <Annuler>                                                          |
|                                                                                                  |
| Variante « Ajustement manuel » :                                                                 |
| Sens *   (o) Retirer du stock   ( ) Ajouter au stock                                             |
| Quantité *  [    1 ]            Stock après l'opération : 69                                     |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : le choix de la nature adapte les libellés et le bouton (« Enregistrer la sortie », « Enregistrer l'ajustement »). Le choix du produit affiche le disponible de l'emplacement. « Stock après l'opération » se recalcule à chaque frappe. Un motif courant préremplit la zone de texte, qui reste modifiable.
- Contrôle avant envoi : une quantité supérieure au disponible désactive le bouton et affiche « Indiquez une quantité entre 1 et 70. » Le serveur revérifie sous verrou. En cas de refus, un bandeau donne la quantité réellement disponible.
- Après enregistrement : retour à l'historique des mouvements, toast « Mouvement BM-2026-000313 enregistré. »
- Fetch : `GET /products/search?q=&warehouse=` pour la recherche, `GET /stock-levels?product=&warehouse=` pour rafraîchir le disponible au choix de l'emplacement.
- Rôles : A, G.
- Tablette : pavé numérique sur le champ de quantité, boutons moins et plus de 44 px.

## 12. Commandes fournisseurs, liste

```text
+--------------------------------------------------------------------------------------------------+
| Achats > Commandes fournisseurs                                                                  |
| Commandes fournisseurs                                           [[ Nouvelle commande ]]         |
|                                                                                                  |
| [ Référence ou fournisseur        ]  ( Tous les statuts v )  ( Tous les fournisseurs v )         |
| ----------------------------------------------------------------------------------------------   |
| Référence        Fournisseur                    Commandée   Attendue v   Reçu     Montant        |
|                                                  le          le                    (F CFA)       |
| ----------------------------------------------------------------------------------------------   |
| CF-2026-000041   Lagune Distribution Inform.    03/11/2026  10/11/2026   46/110   3 752 000      |
|                  {Reçue en partie}  En retard de 3 jours                                         |
| CF-2026-000043   Océan Consommables             09/11/2026  14/11/2026    0/240   1 968 000      |
|                  {Commandée}                                                                     |
| CF-2026-000044   Sahel Réseaux & Câblage        12/11/2026  22/11/2026     0/75   1 242 500      |
|                  {Commandée}                                                                     |
| CF-2026-000045   Papeterie Akwaba               -           -             0/600   1 590 000      |
|                  {Brouillon}                                                                     |
| CF-2026-000040   Ébrié Tech Import              20/10/2026  03/11/2026    24/24   9 480 000      |
|                  {Reçue}                                                                         |
| CF-2026-000038   Comptoir Bureautique Plateau   15/10/2026  18/10/2026     0/30     435 000      |
|                  {Annulée}                                                                       |
| ----------------------------------------------------------------------------------------------   |
| 1 à 6 sur 37                                                   < Précédent  1  2  Suivant >      |
+--------------------------------------------------------------------------------------------------+
```

- Lecture : le badge de statut est une colonne à l'écran. Il est écrit sous la ligne dans la maquette faute de largeur.
- Interactions : une ligne ouvre le détail. Le tri par défaut place en tête les commandes en attente, par date attendue croissante. « Reçu » donne les unités reçues sur les unités commandées. Une commande dont la date attendue est passée porte la mention « En retard de N jours » en texte d'alerte.
- Le compteur du menu latéral (3) est le nombre de commandes aux statuts `ordered` et `partially_received`.
- Fetch : filtrage de la liste.
- Rôles : A, G.
- Tablette : la colonne « Commandée le » est masquée. À 360 px, une carte par commande.

## 13. Commande fournisseur, détail avec avancement

```text
+--------------------------------------------------------------------------------------------------+
| Achats > Commandes fournisseurs > CF-2026-000041                                                 |
| Commande CF-2026-000041   {Reçue en partie}          [ Imprimer ]  [[ Réceptionner ]]            |
| Lagune Distribution Informatique, créée par Mamadou Bamba                                        |
|                                                                                                  |
| Commandée le    03/11/2026                Livraison à     Entrepôt central                       |
| Attendue le     10/11/2026 (en retard de 3 jours)                                                |
| Notes           Livraison en deux fois annoncée par le fournisseur.                              |
|                                                                                                  |
| Avancement de la réception                                                                       |
| [################..........................]  46 unités reçues sur 110, soit 42 %                |
|                                                                                                  |
| Lignes de la commande                                                                            |
| ----------------------------------------------------------------------------------------------   |
| Produit                             SKU          Commandé   Reçu   Reste   Coût unit.    Montant |
|                                                                              (F CFA)    (F CFA)  |
| ----------------------------------------------------------------------------------------------   |
| Imprimante HP LaserJet Pro M404dn   IMP-000042         10      6       4      152 000  1 520 000 |
|   [############........]  60 %                                                                   |
| Cartouche toner HP 59A noir         CON-000118         40     40       0       48 000  1 920 000 |
|   [####################]  100 %  Soldée                                                          |
| Souris sans fil Logitech M185       ACC-000063         60      0      60        5 200    312 000 |
|   [....................]  0 %                                                                    |
| ----------------------------------------------------------------------------------------------   |
| Total                                                 110     46      64               3 752 000 |
|                                                                                                  |
| Réceptions rattachées                                                                            |
| ----------------------------------------------------------------------------------------------   |
| Bon de réception   Reçu le            Bon de livraison   Unités   Réceptionnaire                 |
| BR-2026-000057     10/11/2026 09:20   BL-88214               6    Mamadou Bamba                  |
| BR-2026-000058     11/11/2026 14:05   BL-88310              40    Serge N'Guessan                |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : « Réceptionner » ouvre le formulaire de réception prérempli avec les restes à recevoir. Le bouton disparaît aux statuts `draft`, `received` et `cancelled`. Une réception de la liste ouvre son bon.
- Actions selon le statut : `draft` propose « Modifier », « Passer la commande » (action principale) et « Supprimer le brouillon ». `ordered` propose « Réceptionner » et « Annuler la commande ». `partially_received` ne propose que « Réceptionner » : une commande partiellement reçue ne s'annule pas.
- L'avancement est une barre HTML avec une valeur texte à côté (`role="progressbar"` avec `aria-valuenow`). Le pourcentage est calculé sur les unités.
- Fetch : aucun.
- Rôles : A, G.
- Tablette : les barres par ligne sont remplacées par le texte « 6 sur 10 ». Colonnes SKU et coût unitaire masquées.

## 14. Réception d'une livraison partielle

```text
+--------------------------------------------------------------------------------------------------+
| Achats > Commandes fournisseurs > CF-2026-000041 > Réception                                     |
| Réceptionner la commande CF-2026-000041                                                          |
| Lagune Distribution Informatique, livraison à l'Entrepôt central                                 |
|                                                                                                  |
| Numéro du bon de livraison du fournisseur        Date et heure de réception *                    |
| [ BL-88402              ]                        [ 13/11/2026 ] [ 15:10 ]                        |
|                                                                                                  |
| Quantités reçues                                 [ Tout recevoir ]  [ Tout remettre à 0 ]        |
| ----------------------------------------------------------------------------------------------   |
| Produit                              Commandé   Déjà reçu   Reste   Reçu aujourd'hui             |
| ----------------------------------------------------------------------------------------------   |
| Imprimante HP LaserJet Pro M404dn          10           6       4   [ - ] [    4 ] [ + ]         |
| IMP-000042                                                           Ligne soldée                |
| Souris sans fil Logitech M185              60           0      60   [ - ] [   36 ] [ + ]         |
| ACC-000063                                                           Il restera 24 à recevoir    |
| ----------------------------------------------------------------------------------------------   |
| Cartouche toner HP 59A noir : ligne soldée, 40 reçues sur 40.            40 unités à entrer      |
|                                                                                                  |
| Notes                                                                                            |
| [ Carton de 24 souris manquant, annoncé pour la semaine prochaine.    ]                          |
|                                                                                                  |
| (i) Livraison partielle : la commande restera au statut « Reçue en partie », il manquera         |
|     24 unités.                                                                                   |
|                                                                                                  |
| [[ Enregistrer la réception ]]   <Annuler>                                                       |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : chaque quantité part de 0. « Tout recevoir » remplit chaque champ avec le reste. Sous chaque champ, un texte dit ce que la saisie produit : ligne soldée, ou reste à recevoir. Le bandeau d'information du bas se met à jour pendant la saisie : il annonce le statut que prendra la commande.
- Contrôles : une quantité supérieure au reste est refusée sous le champ (« 4 au plus : c'est le reste à recevoir sur cette commande. »). Une réception dont toutes les lignes sont à 0 est refusée.
- Après enregistrement : le stock de l'entrepôt est augmenté, l'utilisateur arrive sur le bon de réception `BR-2026-000059`, avec un toast et le lien « Imprimer le bon ».
- Réception directe sans commande (D-016, proposée) : même écran, ouvert depuis « Nouvelle réception », avec le choix du fournisseur et de l'emplacement en tête, l'ajout des lignes par `<x-product-search>` et la saisie du coût unitaire.
- Fetch : aucun pour une réception sur commande. `GET /products/search?q=` pour une réception directe.
- Rôles : A, G.
- Tablette : écran prévu pour être rempli debout, devant les cartons. Une ligne par produit sur deux rangées, champ de quantité de 44 px avec pavé numérique, barre d'action collée en bas. Un code-barres lu au lecteur place le focus sur la ligne du produit correspondant.

## 15. Nouveau transfert

```text
+--------------------------------------------------------------------------------------------------+
| Stock > Transferts > Nouveau transfert                                                           |
| Nouveau transfert                                                                                |
| Les champs marqués d'un astérisque sont obligatoires.                                            |
|                                                                                                  |
| Depuis *                           Vers *                                                        |
| ( Entrepôt central v )     -->     ( Boutique du Plateau v )                                     |
|                                                                                                  |
| Ajouter un produit                                                                               |
| [ Rechercher un produit par libellé, SKU ou code-barres               ]                          |
|                                                                                                  |
| Produits à transférer                                                                            |
| ----------------------------------------------------------------------------------------------   |
| Produit                             Disponible    Présent à la     Quantité à transférer         |
|                                     à la source   destination                                    |
| ----------------------------------------------------------------------------------------------   |
| Onduleur APC Back-UPS 650 VA                11               0     [ - ] [    4 ] [ + ]   (x)    |
| ACC-000012                                                         Après : 7 et 4                |
| Switch D-Link 8 ports Gigabit               24               0     [ - ] [   10 ] [ + ]   (x)    |
| RES-000044                                                         Après : 14 et 10              |
| Clavier USB Logitech K120 AZERTY            35               0     [ - ] [   40 ] [ + ]   (x)    |
| ACC-000071                                  (!) 35 disponibles : réduisez la quantité.           |
| ----------------------------------------------------------------------------------------------   |
| 3 produits, 54 unités                                                                            |
|                                                                                                  |
| Notes                                                                                            |
| [ Réassort de la boutique après ruptures signalées le 13/11.          ]                          |
|                                                                                                  |
| [[ Effectuer le transfert ]]   <Annuler>         (inactif tant qu'une ligne est en erreur)       |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : la source et la destination ne peuvent pas être identiques : choisir un emplacement le retire de l'autre liste. Avec deux emplacements, le choix de l'un fixe l'autre. Changer la source vide les lignes après confirmation. `(x)` retire une ligne. « Après » montre les quantités qui résulteront à la source et à la destination.
- Le transfert s'exécute en une fois (D-006, proposée) : pas d'état d'expédition ni d'accusé de réception. La fenêtre de confirmation le dit : « 54 unités quitteront l'Entrepôt central et entreront à la Boutique du Plateau immédiatement. »
- Refus serveur : si le disponible a changé entre-temps, rien n'est transféré, un bandeau liste les lignes fautives avec leur disponible réel et la saisie est conservée.
- Après enregistrement : détail du transfert `TR-2026-000020`, statut « Effectué », avec « Imprimer le bon de transfert ».
- La liste des transferts (non maquettée) suit le gabarit des listes : référence, date, source, destination, unités, auteur, statut.
- Fetch : `GET /products/search?q=&warehouse=` avec l'emplacement source, qui renvoie le disponible à la source et à la destination.
- Rôles : A, G.
- Tablette : une carte par ligne, quantité avec boutons de 44 px.

## 16. Point de vente

```text
+--------------------------------------------------------------------------------------------------+
| Point de vente   Boutique du Plateau   Vendeuse : Fatou Diabaté         <Mes ventes du jour : 9> |
|                                                                                                  |
| +---------------------------------------------------+ +--------------------------------------+   |
| | [ Rechercher ou scanner un produit            / ] | | PANIER   3 produits, 8 articles      |   |
| | 4 produits trouvés pour « logitech »              | |--------------------------------------|   |
| |---------------------------------------------------| | Cartouche toner HP 59A noir          |   |
| | Souris sans fil Logitech M185                     | | CON-000118   62 000                  |   |
| | ACC-000063   7 500 F CFA   {En stock} 12 en bout. | | [-] [  2 ] [+]   sur 3     124 000   |   |
| |                                    [ Ajouter ]    | |                          <Retirer>   |   |
| |---------------------------------------------------| |--------------------------------------|   |
| | Clavier USB Logitech K120 AZERTY                  | | Souris sans fil Logitech M185        |   |
| | ACC-000071   6 500 F CFA   {Rupture} en boutique  | | ACC-000063   7 500                   |   |
| | 35 à l'Entrepôt central            (non vendable) | | [-] [  1 ] [+]   sur 12      7 500   |   |
| |---------------------------------------------------| |                          <Retirer>   |   |
| | Webcam Logitech C270 HD                           | |--------------------------------------|   |
| | ACC-000077   19 500 F CFA  {Sous seuil} 2 en bout.| | Rame papier A4 80 g (500 f.)         |   |
| |                                    [ Ajouter ]    | | PAP-000090   3 500                   |   |
| |---------------------------------------------------| | [-] [  5 ] [+]   sur 80     17 500   |   |
| | Casque micro Logitech H111                        | |                          <Retirer>   |   |
| | ACC-000081   9 000 F CFA   {En stock} 7 en bout.  | |--------------------------------------|   |
| |                                    [ Ajouter ]    | | Client (facultatif)                  |   |
| +---------------------------------------------------+ | [ Mme Adjoua Brou                  ] |   |
|                                                       |                                      |   |
|                                                       | TOTAL               149 000 F CFA    |   |
|                                                       |                                      |   |
|                                                       | [[   Enregistrer la vente         ]] |   |
|                                                       | <Vider le panier>                    |   |
|                                                       +--------------------------------------+   |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : le focus est dans la recherche à l'ouverture et y revient après chaque ajout. Entrée ajoute le premier résultat. Un code-barres lu au lecteur ajoute directement le produit. Ajouter un produit déjà au panier augmente sa quantité de 1. « sur 3 » rappelle le disponible à la boutique, et le bouton plus se désactive quand il est atteint. Un produit en rupture à la boutique reste visible avec le stock de l'entrepôt, sans bouton d'ajout.
- Le prix unitaire est le prix de vente de la fiche. Il n'est pas modifiable : le modèle ne prévoit ni remise ni mode de paiement.
- Le panier vit dans le navigateur (`sessionStorage`), il survit à un rechargement de page et rien n'est écrit en base avant l'enregistrement.
- Enregistrement : le bouton passe à « Enregistrement… » et se désactive. Le serveur crée la vente, ses lignes et ses mouvements dans une transaction. En cas de succès : panier vidé, toast « Vente BS-2026-000132 enregistrée. » avec le lien « Imprimer le bon de sortie », focus rendu à la recherche.
- Disponibilité en direct : les quantités disponibles des lignes du panier sont relues à chaque ajout, à chaque changement de quantité, et toutes les 30 secondes. Si un disponible passe sous la quantité du panier, la ligne est signalée avant même l'envoi.
- Fetch : `GET /pos/products?q=` (recherche limitée à l'emplacement du vendeur), `GET /pos/availability?products[]=` (relecture des disponibles), `POST /sales` (enregistrement, réponse JSON avec la référence ou le détail du refus).
- Rôles : V sur son emplacement d'affectation. A et G y accèdent aussi, avec un sélecteur d'emplacement de type `store` en tête de page (point à trancher).
- Tablette : sous 992 px, la recherche et le panier s'empilent. Le panier se replie en barre collée en bas (« 8 articles, 149 000 F CFA, Voir le panier ») qui ouvre un panneau. Boutons de quantité de 44 px.

## 17. Point de vente, stock insuffisant

```text
+--------------------------------------------------------------------------------------------------+
| +---------------------------------------------------+ +--------------------------------------+   |
| | (!) STOCK INSUFFISANT                             | | PANIER   3 produits, 11 articles     |   |
| |     La vente n'a pas été enregistrée.             | |--------------------------------------|   |
| |                                                   | | (!) Cartouche toner HP 59A noir      |   |
| |     Cartouche toner HP 59A noir : 5 demandés,     | | CON-000118   62 000                  |   |
| |     3 disponibles à la Boutique du Plateau        | | [-] [  5 ] [+]   sur 3     310 000   |   |
| |     (14 à l'Entrepôt central).                    | | 3 disponibles.   [ Ramener à 3 ]     |   |
| |                                                   | |                          <Retirer>   |   |
| |     Réduisez la quantité ou demandez un           | |--------------------------------------|   |
| |     transfert depuis l'entrepôt.                  | | Souris sans fil Logitech M185        |   |
| +---------------------------------------------------+ | [-] [  1 ] [+]   sur 12      7 500   |   |
| | [ Rechercher ou scanner un produit            / ] | |--------------------------------------|   |
| |                                                   | | Rame papier A4 80 g (500 f.)         |   |
| |                                                   | | [-] [  5 ] [+]   sur 80     17 500   |   |
| |                                                   | |--------------------------------------|   |
| |                                                   | | TOTAL               335 000 F CFA    |   |
| |                                                   | | [[   Enregistrer la vente         ]] |   |
| +---------------------------------------------------+ +--------------------------------------+   |
+--------------------------------------------------------------------------------------------------+
```

- Cas représenté : un autre vendeur a vendu des cartouches entre la mise au panier et l'enregistrement, ou la quantité a été saisie au clavier au-delà du disponible. Le serveur refuse la vente en bloc : aucune ligne n'est enregistrée.
- Le bandeau porte `role="alert"` et reçoit le focus. Il nomme le produit, la quantité demandée, le disponible à l'emplacement de vente et le stock de l'entrepôt. Il reste affiché tant que la ligne n'est pas corrigée.
- La ligne fautive est marquée par une icône et un texte, son champ de quantité porte `aria-invalid="true"`. « Ramener à 3 » corrige la quantité et recalcule le total (211 500 F CFA). Les autres lignes du panier sont intactes.
- Si plusieurs lignes dépassent le disponible, le bandeau les liste toutes. Le service vérifie toutes les lignes avant de refuser : la réponse du serveur liste donc tous les manques avec leur quantité disponible.
- Fetch : la réponse `422` de `POST /sales` porte, pour chaque ligne refusée, l'identifiant du produit, la quantité demandée et la quantité disponible.
- Rôles : comme le point de vente.
- Tablette : le bandeau s'affiche au-dessus du panier ouvert.

## 18. Bon de sortie imprimable

```text
+--------------------------------------------------------------------------------------------------+
| [ < Retour à la vente ]                              [ Télécharger le PDF ]  [[ Imprimer ]]      |
|         (barre d'actions masquée à l'impression)                                                 |
|                                                                                                  |
|     +------------------------------------------------------------------------------------+       |
|     | IVOIRE BUREAUTIQUE & TECH                                        BON DE SORTIE     |       |
|     | Boutique du Plateau                                              BS-2026-000128    |       |
|     | Rue du Commerce, Plateau, Abidjan                                13/11/2026 10:42  |       |
|     | +225 27 20 21 43 65                                                                |       |
|     |------------------------------------------------------------------------------------|       |
|     | Emplacement : Boutique du Plateau (PDV)     Client : Cabinet Konan & Associés      |       |
|     | Vendeuse : Fatou Diabaté                                                           |       |
|     |------------------------------------------------------------------------------------|       |
|     | N°  Référence    Désignation                          Qté   Prix unit.     Montant |       |
|     |                                                               (F CFA)      (F CFA) |       |
|     |------------------------------------------------------------------------------------|       |
|     | 1   IMP-000042   Imprimante HP LaserJet Pro M404dn      1     185 000      185 000 |       |
|     | 2   CON-000118   Cartouche toner HP 59A noir            2      62 000      124 000 |       |
|     | 3   PAP-000090   Rame papier A4 80 g (500 f.)           5       3 500       17 500 |       |
|     |------------------------------------------------------------------------------------|       |
|     |                                      Nombre d'articles : 8                         |       |
|     |                                      TOTAL                         326 500 F CFA   |       |
|     |                                                                                    |       |
|     |   Le vendeur                              Le client, reçu conforme                 |       |
|     |   +--------------------------+            +--------------------------+             |       |
|     |   |                          |            |                          |             |       |
|     |   | Nom :                    |            | Nom :                    |             |       |
|     |   | Date :                   |            | Date :                   |             |       |
|     |   +--------------------------+            +--------------------------+             |       |
|     |                                                                                    |       |
|     | BS-2026-000128   Page 1 sur 1   Édité le 13/11/2026 10:43 par Fatou Diabaté        |       |
|     +------------------------------------------------------------------------------------+       |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : « Imprimer » appelle l'impression du navigateur. « Télécharger le PDF » renvoie le même document produit par Dompdf. La page s'ouvre depuis le toast de fin de vente et depuis le détail d'une vente.
- Document en noir sur blanc, A4 portrait, sans couleur de fond. Une vente annulée porte sous l'en-tête un cadre « ANNULÉ le 13/11/2026 par Mamadou Bamba : erreur de produit au comptoir ».
- Les bons de réception et de transfert suivent le même gabarit : le bon de réception remplace le client par le fournisseur et le numéro de bon de livraison, le bon de transfert affiche source et destination et n'a aucune colonne de prix.
- Le bon ne porte ni taxe, ni remise, ni mode de paiement, absents du modèle. Ce n'est pas une facture.
- Fetch : aucun.
- Rôles : V pour ses ventes, A et G pour toutes.
- Tablette : la feuille est réduite à la largeur de l'écran, les boutons restent en haut.

## 19. Historique des ventes

```text
+--------------------------------------------------------------------------------------------------+
| Ventes > Historique                                                                              |
| Historique des ventes                                 [ Exporter en CSV ]  [[ Nouvelle vente ]]  |
|                                                                                                  |
| Du [13/11/2026] au [13/11/2026]   <Aujourd'hui> <7 jours> <Ce mois>                              |
| [ Référence ou client             ]  ( Tous les vendeurs v )  ( Tous les statuts v )             |
|                                                                                                  |
| 23 ventes   Total validé : 2 845 500 F CFA   1 vente annulée (124 000 F CFA)                     |
| ----------------------------------------------------------------------------------------------   |
| Bon de sortie    Date v            Vendeur          Client                     Art.    Montant   |
|                                                                                        (F CFA)   |
| ----------------------------------------------------------------------------------------------   |
| BS-2026-000131   13/11/2026 14:31  Fatou Diabaté    -                             5     17 500   |
|                  {Validée}                                                                       |
| BS-2026-000130   13/11/2026 14:12  Fatou Diabaté    Mme Adjoua Brou               1      7 500   |
|                  {Validée}                                                                       |
| BS-2026-000129   13/11/2026 11:15  Koffi Yao        Lycée Sainte-Marie            2    124 000   |
|                  {Annulée}  par Mamadou Bamba : doublon de la vente BS-2026-000126               |
| BS-2026-000128   13/11/2026 10:42  Fatou Diabaté    Cabinet Konan & Associés      8    326 500   |
|                  {Validée}                                                                       |
| BS-2026-000127   13/11/2026 09:48  Koffi Yao        -                             3     42 000   |
|                  {Validée}                                                                       |
| ----------------------------------------------------------------------------------------------   |
| 1 à 23 sur 23                                                                                    |
|                                                                                                  |
| Détail d'une vente (page) : en-tête du bon, lignes, total, auteur, puis                          |
| [ Imprimer le bon de sortie ]   [ Annuler la vente ]  (A et G uniquement, vente validée)         |
|                                                                                                  |
| Fenêtre « Annuler la vente BS-2026-000128 » :                                                    |
|         +----------------------------------------------------------------+                       |
|         | Annuler la vente BS-2026-000128                            (x) |                       |
|         | Les 8 articles seront remis en stock à la Boutique du Plateau. |                       |
|         | La vente restera visible dans l'historique, marquée annulée.   |                       |
|         | Motif de l'annulation *                                        |                       |
|         | [                                                   ] 0 / 500  |                       |
|         |                   <Ne pas annuler>   [[ Annuler la vente ]]    |                       |
|         +----------------------------------------------------------------+                       |
+--------------------------------------------------------------------------------------------------+
```

- Lecture : le statut est une colonne à l'écran. Il est écrit sous la ligne dans la maquette faute de largeur.
- Interactions : une ligne ouvre le détail. Une vente annulée reste dans la liste, avec son auteur d'annulation et le motif. Le total de synthèse ne compte que les ventes validées.
- Annulation : réservée à A et G (D-009, proposée). Motif obligatoire. Le bouton de confirmation est rouge brique. L'annulation crée les mouvements inverses, elle ne supprime rien.
- Fetch : filtrage de la liste. `POST /sales/{id}/cancel` depuis la fenêtre.
- Rôles : V voit ses propres ventes, sans filtre vendeur et sans export. A et G voient toutes les ventes.
- Tablette : colonnes « Vendeur » et « Art. » masquées. À 360 px, une carte par vente.

## 20. Inventaires, liste des sessions

```text
+--------------------------------------------------------------------------------------------------+
| Stock > Inventaires                                                                              |
| Inventaires                                                     [[ Nouvel inventaire ]]          |
|                                                                                                  |
| ( Tous les emplacements v )  ( Tous les statuts v )                                              |
| ----------------------------------------------------------------------------------------------   |
| Référence         Emplacement          Périmètre                  Ouvert le    Comptage          |
| ----------------------------------------------------------------------------------------------   |
| INV-2026-000007   Entrepôt central     Consommables d'impression  13/11/2026   112 / 186 lignes  |
|                   {Comptage en cours}  ouvert par Serge N'Guessan             [ Reprendre ]      |
| INV-2026-000006   Boutique du Plateau  Périphériques et access.   12/11/2026   231 / 231 lignes  |
|                   {À valider}  soumis le 12/11/2026 17:20, 4 écarts           [ Examiner ]       |
| INV-2026-000005   Entrepôt central     Inventaire complet         30/09/2026   838 / 838 lignes  |
|                   {Validé}  par Aya Kouassi le 01/10/2026, 19 écarts                             |
| INV-2026-000004   Boutique du Plateau  Papeterie et fournitures   22/09/2026    40 / 148 lignes  |
|                   {Annulé}                                                                       |
| ----------------------------------------------------------------------------------------------   |
|                                                                                                  |
| Fenêtre « Nouvel inventaire » :                                                                  |
|         +----------------------------------------------------------------+                       |
|         | Nouvel inventaire                                          (x) |                       |
|         | Emplacement *       ( Boutique du Plateau v )                  |                       |
|         | Périmètre *         (o) Une catégorie  ( Ordinateurs v )       |                       |
|         |                     ( ) Inventaire complet de l'emplacement    |                       |
|         | Notes               [                                   ]      |                       |
|         | (i) Le théorique est relevé au comptage de chaque ligne.       |                       |
|         |     Ventes et mouvements restent possibles pendant le comptage.|                       |
|         |                     <Fermer>  [[ Ouvrir l'inventaire ]]        |                       |
|         +----------------------------------------------------------------+                       |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : « Reprendre » ouvre l'écran de comptage, « Examiner » la revue des écarts. « Examiner » est visible de G en lecture et de A avec les actions de validation. Une session validée ou annulée s'ouvre en lecture seule.
- Une seule session ouverte par emplacement : si l'emplacement choisi a déjà une session `in_progress` ou `pending_validation`, la fenêtre l'indique et propose de l'ouvrir : « Un inventaire est déjà ouvert sur la Boutique du Plateau (INV-2026-000006). »
- Le compteur du menu latéral indique, pour A, les sessions à valider.
- Fetch : `POST /inventories` depuis la fenêtre.
- Rôles : A, G. La création et le comptage reviennent à G, A peut les faire aussi.
- Tablette : la colonne « Périmètre » passe sous l'emplacement. Boutons « Reprendre » et « Examiner » de 44 px.

## 21. Inventaire, écran de comptage (tablette)

Écran dessiné pour une tablette tenue en mode portrait (768 px), d'où la largeur réduite.

```text
+----------------------------------------------------------------------------+
| [=]  INV-2026-000007   {Comptage en cours}                 (Alertes 4)  SN |
| ------------------------------------------------------------------------   |
| Entrepôt central, Consommables d'impression                                |
| [##########################................]  112 lignes sur 186           |
|                                                                            |
| [ Rechercher ou scanner un produit                                   ]     |
| [ Tous 186 ]  [ À compter 74 ]  [ Comptés 112 ]  [ Avec écart 5 ]          |
|                                                                            |
| +----------------------------------------------------------------------+   |
| | Cartouche toner HP 59A noir                             Enregistré   |   |
| | CON-000118                                                           |   |
| | Quantité comptée     [  -  ]  [      12 ]  [  +  ]                   |   |
| | Écart : -2                                                           |   |
| | Justification *                                                      |   |
| | [ 2 cartouches abîmées par l'humidité, mises au rebut.             ] |   |
| +----------------------------------------------------------------------+   |
| +----------------------------------------------------------------------+   |
| | Cartouche d'encre HP 305 noir                           Enregistré   |   |
| | CON-000127                                                           |   |
| | Quantité comptée     [  -  ]  [      31 ]  [  +  ]                   |   |
| | Écart : +1                                                           |   |
| | Justification *                                                      |   |
| | [ Indiquez la raison de cet écart.                                 ] |   |
| +----------------------------------------------------------------------+   |
| +----------------------------------------------------------------------+   |
| | Toner Brother TN-2420                                   À compter    |   |
| | CON-000131                                                           |   |
| | Quantité comptée     [  -  ]  [         ]  [  +  ]                   |   |
| +----------------------------------------------------------------------+   |
| +----------------------------------------------------------------------+   |
| | Ruban Epson LQ-350                           Enregistrement en cours |   |
| | CON-000140                                                           |   |
| | Quantité comptée     [  -  ]  [       5 ]  [  +  ]                   |   |
| +----------------------------------------------------------------------+   |
|                                                                            |
| ------------------------------------------------------------------------   |
| 74 lignes restent à compter          [[ Terminer le comptage ]] (inactif)  |
+----------------------------------------------------------------------------+
```

- Principe : le stock théorique n'est pas affiché pendant le comptage, pour que le compteur ne recopie pas le chiffre attendu. L'écart apparaît après l'enregistrement de la ligne, pour permettre de recompter tout de suite et de saisir la justification sur place. Ce choix est à faire confirmer par le client.
- Interactions : la quantité s'enregistre à la sortie du champ ou à l'appui sur moins ou plus. Chaque carte affiche son état : à compter, enregistrement en cours, enregistré, échec avec « Réessayer ». Un champ vide signifie « non compté », la valeur 0 signifie « compté, zéro ». La justification devient obligatoire dès que l'écart est différent de zéro.
- Recherche : un code-barres lu au lecteur ou une recherche fait défiler jusqu'à la carte du produit et place le focus dans son champ. Les onglets filtrent les cartes.
- « Terminer le comptage » s'active quand toutes les lignes sont comptées et que chaque écart est justifié. Sinon, le pied de page dit ce qui manque (« 74 lignes restent à compter », « 1 écart sans justification »). La confirmation fait passer la session au statut `pending_validation` et verrouille la saisie.
- Les mouvements restent autorisés pendant l'inventaire (D-007, proposée). Un bandeau d'information le rappelle en tête : « Les ventes et mouvements continuent pendant le comptage. Les écarts seront appliqués au stock du moment de la validation. »
- Fetch : `PATCH /inventories/{id}/items/{item}` à chaque ligne, avec la quantité comptée et la justification. La réponse renvoie l'écart calculé. `GET /inventories/{id}/items?filter=&q=&page=` pour charger les cartes par lots de 50. En cas de perte de réseau, la saisie reste dans le champ, la carte passe en « Échec » et un bandeau propose « Réessayer les 3 lignes en attente ».
- Rôles : G et A.
- Ordinateur : mêmes données dans un tableau, une ligne par produit, avec tabulation directe d'un champ de quantité au suivant.
- Tablette : champ de quantité de 56 px de haut, texte de 20 px, pavé numérique. Boutons moins et plus de 56 px. Pied de page collé en bas.

## 22. Inventaire, revue des écarts et validation

```text
+--------------------------------------------------------------------------------------------------+
| Stock > Inventaires > INV-2026-000007                                                            |
| Inventaire INV-2026-000007   {À valider}       [ Écarts (PDF) ]  [[ Valider l'inventaire ]]      |
| Entrepôt central, Consommables d'impression                                                      |
| Ouvert le 13/11/2026 08:10 par Serge N'Guessan, soumis le 13/11/2026 16:40                       |
|                                                                                                  |
| +------------------+ +------------------+ +------------------+ +---------------------------+     |
| | LIGNES COMPTÉES  | | SANS ÉCART       | | AVEC ÉCART       | | VALEUR NETTE DES ÉCARTS   |     |
| | 186              | | 181              | | 5                | | -89 500 F CFA             |     |
| +------------------+ +------------------+ +------------------+ +---------------------------+     |
|                                                                                                  |
| [ Avec écart 5 ]  [ Toutes les lignes 186 ]                                                      |
| ----------------------------------------------------------------------------------------------   |
| Produit                      Théorique  Compté  Écart   Stock    Stock après   Valeur écart      |
|                                                          actuel   validation        (F CFA)      |
| ----------------------------------------------------------------------------------------------   |
| Cartouche toner HP 59A noir         14      12     -2       14            12        -96 000      |
|   Justification : 2 cartouches abîmées par l'humidité, mises au rebut.                           |
| Cartouche d'encre HP 305 noir       30      31     +1       28            29        +11 000      |
|   Justification : 1 cartouche rangée par erreur avec les rubans.                                 |
|   (i) 2 unités sorties depuis l'ouverture de l'inventaire.                                       |
| Ruban Epson LQ-350                   6       5     -1        6             5         -4 500      |
|   Justification : emballage vide retrouvé en rayon.                                              |
| Toner Brother TN-2420                8       9     +1        8             9        +22 000      |
|   Justification : réception du 05/11 comptée une unité en moins.                                 |
| Encre Epson 103 noir                25      24     -1       25            24        -22 000      |
|   Justification : flacon percé, jeté.                                                            |
| ----------------------------------------------------------------------------------------------   |
| Total                                               -2                              -89 500      |
|                                                                                                  |
| <Renvoyer au comptage>     <Annuler l'inventaire>                                                |
|                                                                                                  |
| Fenêtre « Valider l'inventaire INV-2026-000007 » :                                               |
|         +------------------------------------------------------------------+                     |
|         | Valider l'inventaire INV-2026-000007                         (x) |                     |
|         | 5 mouvements d'ajustement seront créés à l'Entrepôt central,     |                     |
|         | pour une valeur nette de -89 500 F CFA. Cette validation est     |                     |
|         | définitive.                                                      |                     |
|         | Notes de validation                                              |                     |
|         | [                                                     ]          |                     |
|         |                    <Fermer>   [[ Valider l'inventaire ]]         |                     |
|         +------------------------------------------------------------------+                     |
+--------------------------------------------------------------------------------------------------+
```

- Lecture : « Stock actuel » est la quantité de l'emplacement au moment de la revue. « Stock après validation » est le stock actuel augmenté de l'écart (D-007, proposée). Quand des mouvements ont eu lieu depuis l'ouverture, une note le dit sous la ligne.
- Garde : si l'application d'un écart négatif devait rendre le stock négatif (produit vendu entre-temps), la ligne est signalée en erreur et la validation est bloquée, avec la consigne « Renvoyez cette ligne au comptage. »
- Interactions : l'onglet « Avec écart » est ouvert par défaut. « Valider l'inventaire » demande confirmation, crée un mouvement `inventory_adjustment` par ligne en écart avec la justification pour motif, et passe la session au statut `validated`. « Renvoyer au comptage » remet la session en `in_progress`. « Annuler l'inventaire » demande un motif et n'écrit aucun mouvement.
- La valeur d'un écart est l'écart multiplié par le prix d'achat courant du produit.
- Fetch : aucun, actions en `POST` classiques.
- Rôles : validation, renvoi et annulation pour A seulement (D-009, proposée). G voit l'écran en lecture seule, sans les trois actions, avec la mention « En attente de validation par un administrateur ».
- Tablette : tableau en mode défilement horizontal, première colonne figée. Les colonnes « Théorique » et « Compté » restent visibles, « Stock actuel » passe dans le détail de la ligne.

## 23. Centre d'alertes

```text
+--------------------------------------------------------------------------------------------------+
| Pilotage > Alertes                                                                               |
| Alertes de stock                                                  [ Tout marquer comme lu ]      |
|                                                                                                  |
| [ À réapprovisionner 46 ]  [ Notifications 4 non lues ]                                          |
|                                                                                                  |
| Onglet « À réapprovisionner » : situation actuelle                                               |
| ( Tous les états v )  ( Toutes les catégories v )  ( Tous les fournisseurs v )                   |
| ----------------------------------------------------------------------------------------------   |
| Produit                          Entrepôt  Boutique  Total  Seuil  État          Fournisseur     |
|                                                                                   (délai)        |
| ----------------------------------------------------------------------------------------------   |
| Écran Dell 24 pouces P2422H             0         0      0      4  {Rupture}     Lagune (7 j.)   |
|                                  Commande CF-2026-000046 en brouillon                            |
| Routeur TP-Link Archer C6               0         0      0      5  {Rupture}     Sahel (10 j.)   |
|                                  Commande CF-2026-000044 attendue le 22/11/2026                  |
| Cartouche d'encre Canon PG-445          4         2      6     12  {Sous seuil}  Océan (5 j.)    |
|                                  Aucune commande en cours             [ Commander ]              |
| Ordinateur Lenovo ThinkPad E14          2         1      3      3  {Sous seuil}  Ébrié (14 j.)   |
|                                  Aucune commande en cours             [ Commander ]              |
| ----------------------------------------------------------------------------------------------   |
| 1 à 25 sur 46                                                                                    |
|                                                                                                  |
| Onglet « Notifications » : événements de franchissement de seuil                                 |
| ----------------------------------------------------------------------------------------------   |
| (o) 13/11/2026 14:12  Souris sans fil Logitech M185 est passée sous son seuil de réassort        |
|                       à la Boutique du Plateau : 2 restantes, seuil 3.     [ Transférer ]        |
| (o) 13/11/2026 10:42  Imprimante HP LaserJet Pro M404dn est sous son seuil de réassort           |
|                       à la Boutique du Plateau : 2 restantes, seuil 2.     [ Transférer ]        |
| ( ) 12/11/2026 17:05  Routeur TP-Link Archer C6 est en rupture : 0 en stock, seuil 5.            |
| ----------------------------------------------------------------------------------------------   |
| (o) non lue   ( ) lue                                                                            |
+--------------------------------------------------------------------------------------------------+
```

- Deux vues : la situation actuelle (tous les produits dont le stock total est inférieur ou égal au seuil, ruptures en tête) et le fil des notifications émises au franchissement d'un seuil (D-013). Les deux nombres diffèrent par nature : 46 produits concernés, 4 notifications non lues.
- Interactions : « Commander » ouvre une nouvelle commande avec le fournisseur principal et le produit en ligne. « Transférer » ouvre un transfert de l'entrepôt vers la boutique avec le produit en ligne. Ouvrir une notification la marque comme lue. Une ligne ouvre la fiche produit.
- Chaque ligne à réapprovisionner indique s'il existe une commande en cours pour le produit, ce qui évite de commander deux fois.
- La distinction entre lue et non lue est portée par un point plein, une graisse de texte plus forte et un texte pour lecteur d'écran.
- Fetch : `POST /notifications/{id}/read`, `POST /notifications/read-all`. Le compteur de la cloche se met à jour en retour.
- Rôles : A, G.
- Tablette : les colonnes par emplacement sont regroupées en « 4 + 2 » sous le total. Le fournisseur passe sous le libellé.

## 24. Rapports et exports

```text
+--------------------------------------------------------------------------------------------------+
| Pilotage > Rapports                                                                              |
| Rapports et exports                                                                              |
|                                                                                                  |
| +--------------------------------------------+ +---------------------------------------------+   |
| | ÉTAT DES STOCKS                            | | HISTORIQUE DES MOUVEMENTS                   |   |
| | Quantités et valeur par produit, à la date | | Tous les mouvements d'une période, avec     |   |
| | et à l'heure de l'édition.                 | | stock avant et après.                       |   |
| |                                            | |                                             |   |
| | Emplacement   ( Tous v )                   | | Période *   Du [01/11/2026] au [13/11/2026] |   |
| | Catégorie     ( Toutes v )                 | | Type          ( Tous v )                    |   |
| | État          ( Tous v )                   | | Emplacement   ( Tous v )                    |   |
| |                                            | | Utilisateur   ( Tous v )                    |   |
| | [ Exporter en CSV ]  [ Exporter en PDF ]   | | [ Exporter en CSV ]  [ Exporter en PDF ]    |   |
| +--------------------------------------------+ +---------------------------------------------+   |
| +--------------------------------------------+ +---------------------------------------------+   |
| | PRODUITS À RÉAPPROVISIONNER                | | VENTES DE LA PÉRIODE                        |   |
| | Produits sous seuil ou en rupture, avec    | | Ventes validées et annulées, par bon de     |   |
| | fournisseur et délai de livraison.         | | sortie.                                     |   |
| |                                            | |                                             |   |
| | Fournisseur   ( Tous v )                   | | Période *   Du [01/11/2026] au [13/11/2026] |   |
| |                                            | | Vendeur       ( Tous v )                    |   |
| | [ Exporter en CSV ]  [ Exporter en PDF ]   | | [ Exporter en CSV ]  [ Exporter en PDF ]    |   |
| +--------------------------------------------+ +---------------------------------------------+   |
|                                                                                                  |
| Derniers exports                                                                                 |
| ----------------------------------------------------------------------------------------------   |
| Date               Rapport                      Format   Filtres                    Par          |
| 13/11/2026 12:02   Historique des mouvements    CSV      du 01/10 au 31/10/2026     M. Bamba     |
| 03/11/2026 09:15   État des stocks              PDF      Tous les emplacements      A. Kouassi   |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : chaque carte est un petit formulaire `GET` qui renvoie le fichier. Les mêmes exports sont accessibles depuis les listes correspondantes, avec les filtres en cours. Cette page les rassemble pour les contrôles comptables.
- Les quatre cartes ont la même taille et la même structure. Ce sont des formulaires, pas des tuiles décoratives.
- « Derniers exports » est tiré du journal d'audit (action `exported`). Il ne conserve pas les fichiers : il dit qui a exporté quoi.
- L'état des stocks est daté de l'instant de l'édition. Un état à une date passée n'est pas proposé dans cette version.
- Pendant la génération d'un PDF, le bouton passe à « Préparation… ». Le CSV est envoyé en flux.
- Fetch : aucun, téléchargement direct.
- Rôles : A, G.
- Tablette : cartes sur une colonne sous 992 px.

## 25. Utilisateurs

```text
+--------------------------------------------------------------------------------------------------+
| Administration > Utilisateurs                                                                    |
| Utilisateurs                                                    [[ Nouvel utilisateur ]]         |
|                                                                                                  |
| [ Nom ou adresse e-mail           ]  ( Tous les rôles v )  ( Actifs v )                          |
| ----------------------------------------------------------------------------------------------   |
| Nom ^               Adresse e-mail                    Rôle                   Emplacement         |
|                     Dernière connexion                                                État       |
| ----------------------------------------------------------------------------------------------   |
| Aya Kouassi         aya.kouassi@ivoirebureautique.example      Administrateur         -          |
|                     13/11/2026 07:52                                                  {Actif}    |
| Fatou Diabaté       fatou.diabate@ivoirebureautique.example    Vendeur                PDV        |
|                     13/11/2026 08:01                                                  {Actif}    |
| Koffi Yao           koffi.yao@ivoirebureautique.example        Vendeur                PDV        |
|                     13/11/2026 08:14                                                  {Actif}    |
| Mamadou Bamba       mamadou.bamba@ivoirebureautique.example    Gestionnaire de stock  ENT        |
|                     13/11/2026 07:40                                                  {Actif}    |
| Marie-Laure Aka     marielaure.aka@ivoirebureautique.example   Vendeur                PDV        |
|                     28/08/2026 17:30                                                  {Inactif}  |
| Serge N'Guessan     serge.nguessan@ivoirebureautique.example   Gestionnaire de stock  ENT        |
|                     13/11/2026 07:58                                                  {Actif}    |
| ----------------------------------------------------------------------------------------------   |
| 6 utilisateurs                                                                                   |
|                                                                                                  |
| Formulaire « Nouvel utilisateur » (page) :                                                       |
| Nom complet *            [                                  ]                                    |
| Adresse e-mail *         [                                  ]                                    |
| Rôle *                   ( ) Administrateur  ( ) Gestionnaire de stock  (o) Vendeur              |
| Emplacement *            ( Boutique du Plateau v )    obligatoire pour un vendeur                |
| Mot de passe initial *   [                        (Afficher) ]   [ Générer ]                     |
|                          12 caractères au moins. Communiquez-le à la personne.                   |
| [x] Compte actif                                                                                 |
| [[ Créer l'utilisateur ]]   <Annuler>                                                            |
+--------------------------------------------------------------------------------------------------+
```

- Lecture : dernière connexion et état sont des colonnes à l'écran.
- Interactions : une ligne ouvre le formulaire de modification. Le champ « Emplacement » devient obligatoire quand le rôle « Vendeur » est choisi, et il ne propose alors que les emplacements de type `store`. Un seul champ de mot de passe avec bouton d'affichage (`autocomplete="new-password"`). « Générer » propose un mot de passe aléatoire affiché en clair.
- Désactivation : un compte ne se supprime pas depuis l'interface, il se désactive après confirmation (« Marie-Laure Aka ne pourra plus se connecter. Son historique est conservé. »). Un administrateur ne peut ni désactiver son propre compte, ni retirer son propre rôle : les contrôles sont absents de sa propre fiche, avec une phrase qui l'explique.
- La réinitialisation d'un mot de passe se fait ici : l'administrateur en attribue un nouveau. Règle : 12 caractères au moins, sans obligation de composition (D-010), et changement forcé à la connexion suivante.
- Fetch : filtrage de la liste.
- Rôles : A.
- Tablette : l'adresse e-mail passe sous le nom, la dernière connexion est masquée.

## 26. Journal d'audit

```text
+--------------------------------------------------------------------------------------------------+
| Administration > Journal d'audit                                                                 |
| Journal d'audit                                                        [ Exporter en CSV ]       |
|                                                                                                  |
| Du [13/11/2026] au [13/11/2026]   <Aujourd'hui> <7 jours> <Ce mois>                              |
| ( Tous les utilisateurs v )  ( Toutes les actions v )  ( Tous les objets v )                     |
| [ Rechercher dans la description  ]                                                              |
|                                                                                                  |
| 214 événements, 1 à 25                                                                           |
| ----------------------------------------------------------------------------------------------   |
| Date v            Utilisateur       Action              Description                              |
| ----------------------------------------------------------------------------------------------   |
| 13/11/2026 15:22  Mamadou Bamba     Annulation          Annulation de la vente BS-2026-000129[v] |
| 13/11/2026 14:35  Aya Kouassi       Modification        Modification du produit IMP-000042   [^] |
|    +--------------------------------------------------------------------------------------+      |
|    | Champ               Ancienne valeur          Nouvelle valeur                         |      |
|    | Prix de vente       179 000 F CFA            185 000 F CFA                           |      |
|    | Seuil d'alerte      4                        5                                       |      |
|    | Adresse IP 196.201.74.18   Navigateur Chrome 142 sur Windows   <Ouvrir le produit>   |      |
|    +--------------------------------------------------------------------------------------+      |
| 13/11/2026 12:02  Mamadou Bamba     Export              Export CSV de l'historique des mouv. [v] |
| 13/11/2026 08:40  Serge N'Guessan   Création            Mouvement BM-2026-000311             [v] |
| 13/11/2026 08:03  -                 Connexion refusée   Tentative pour koffi.yao@ivoireb...  [v] |
| 13/11/2026 08:01  Fatou Diabaté     Connexion           Connexion depuis 196.201.74.18       [v] |
| ----------------------------------------------------------------------------------------------   |
| 1 à 25 sur 214      Lignes par page ( 25 v )              < Précédent  1  2  3 ... 9  Suivant >  |
+--------------------------------------------------------------------------------------------------+
```

- Interactions : `[v]` déplie le détail d'un événement sous sa ligne (bouton avec `aria-expanded`). Le détail compare anciennes et nouvelles valeurs champ par champ, avec les libellés français des champs et les valeurs mises en forme. Il donne l'adresse IP, le navigateur et un lien vers l'objet concerné.
- Lecture seule : aucune action de modification ou de suppression. L'export CSV reprend les filtres.
- Une connexion refusée n'a pas d'utilisateur : la colonne affiche un tiret et la description cite l'adresse e-mail tentée.
- Le mot de passe et le jeton de session n'apparaissent jamais dans les valeurs.
- Fetch : filtrage de la liste. Le détail est déjà dans la page, replié.
- Rôles : A.
- Tablette : la colonne « Action » passe sous l'utilisateur. Le détail déplié passe en liste « champ, ancienne valeur, nouvelle valeur » empilée.

## 27. Page 404

```text
+--------------------------------------------------------------------------------------------------+
|                                                                                                  |
|                                                                                                  |
|                          404                                                                     |
|                          Page introuvable                                                        |
|                                                                                                  |
|                          L'adresse demandée n'existe pas, ou le document a été déplacé.          |
|                          Vérifiez l'adresse, ou reprenez depuis l'un de ces écrans.              |
|                                                                                                  |
|                          [[ Retour au tableau de bord ]]   <Rechercher un produit>               |
|                                                                                                  |
|                                                                                                  |
+--------------------------------------------------------------------------------------------------+
```

- La page garde la barre latérale et la barre supérieure quand l'utilisateur est connecté : il peut repartir par le menu ou la recherche. Hors session, elle utilise le gabarit de la connexion avec un lien « Se connecter ».
- Pour un vendeur, le bouton devient « Retour au point de vente ».
- Pas d'illustration, pas d'humour. Le code 404 est en texte secondaire au-dessus du titre, qui est le `h1`.
- Rôles : tous.

## 28. Page 403

```text
+--------------------------------------------------------------------------------------------------+
|                                                                                                  |
|                                                                                                  |
|                          403                                                                     |
|                          Accès refusé                                                            |
|                                                                                                  |
|                          Votre profil (Vendeur) ne donne pas accès à cette page.                 |
|                          Si vous pensez qu'il s'agit d'une erreur, adressez-vous à               |
|                          votre administrateur.                                                   |
|                                                                                                  |
|                          [[ Retour au point de vente ]]   <Page précédente>                      |
|                                                                                                  |
|                                                                                                  |
+--------------------------------------------------------------------------------------------------+
```

- Le message nomme le rôle de l'utilisateur, sans décrire la page refusée ni les droits qui manquent.
- Le bouton principal ramène à la page d'accueil du rôle : tableau de bord pour A et G, point de vente pour V.
- Un appel Fetch refusé ne redirige pas vers cette page : il affiche un bandeau « Vous n'avez pas le droit d'effectuer cette action. » dans l'écran en cours.
- Même gabarit pour la page 419 (« Votre session a expiré. Reconnectez-vous pour continuer. ») et la page 500.
- Rôles : tous.

## Plan de navigation

Arborescence du menu latéral. Une entrée absente pour un rôle n'est pas rendue dans le menu, et sa route renvoie 403.

```text
StockFlow
|
+-- Tableau de bord ................. /dashboard
|
+-- CATALOGUE
|   +-- Produits .................... liste, fiche, formulaire
|   +-- Catégories
|   +-- Fournisseurs ................ liste, fiche, formulaire
|
+-- STOCK
|   +-- État des stocks
|   +-- Mouvements .................. historique, nouveau mouvement
|   +-- Transferts .................. liste, nouveau, détail, bon
|   +-- Inventaires ................. sessions, comptage, revue      (compteur : à valider)
|
+-- ACHATS
|   +-- Commandes ................... liste, détail, formulaire      (compteur : en attente)
|   +-- Réceptions .................. liste, nouvelle, bon
|
+-- VENTES
|   +-- Point de vente
|   +-- Historique .................. liste, détail, bon de sortie
|
+-- PILOTAGE
|   +-- Alertes ..................... à réapprovisionner, notifications   (compteur : non lues)
|   +-- Rapports
|
+-- ADMINISTRATION
    +-- Utilisateurs
    +-- Emplacements ................ écran non maquetté, voir plus bas
    +-- Journal d'audit

Barre supérieure : recherche de produit, cloche d'alertes, menu utilisateur
Menu utilisateur : Changer mon mot de passe, Se déconnecter
```

Visibilité par rôle. « Oui » : accès complet à la rubrique. « Lecture » : consultation sans création ni modification. « Non » : entrée absente.

| Entrée | Administrateur | Gestionnaire de stock | Vendeur |
| :-- | :-- | :-- | :-- |
| Tableau de bord | Oui | Oui | Variante réduite |
| Produits | Oui | Oui | Lecture, sans prix d'achat |
| Catégories | Oui | Oui | Non |
| Fournisseurs | Oui | Oui | Non |
| État des stocks | Oui | Oui | Lecture, sans valeur ni export |
| Mouvements | Oui | Oui | Non |
| Transferts | Oui | Oui | Non |
| Inventaires | Oui, avec validation | Oui, sans validation | Non |
| Commandes | Oui | Oui | Non |
| Réceptions | Oui | Oui | Non |
| Point de vente | Oui, choix de l'emplacement | Oui, choix de l'emplacement | Oui, emplacement d'affectation |
| Historique des ventes | Oui, avec annulation | Oui, avec annulation | Ses ventes, sans annulation |
| Alertes | Oui | Oui | Non |
| Rapports | Oui | Oui | Non |
| Utilisateurs | Oui | Non | Non |
| Emplacements | Oui | Non | Non |
| Journal d'audit | Oui | Non | Non |
| Recherche globale de produit | Oui | Oui | Oui |
| Cloche d'alertes | Oui | Oui | Non |
| Changer mon mot de passe | Oui | Oui | Oui |

Pour le vendeur, le menu se réduit à cinq entrées : Tableau de bord, Produits, État des stocks, Point de vente, Historique. Les surtitres de groupe sont alors supprimés.

Page d'accueil après connexion : tableau de bord pour l'administrateur et le gestionnaire, point de vente pour le vendeur.

Écrans non maquettés dans ce document, qui suivent les gabarits décrits : liste et détail des transferts, liste des réceptions et bon de réception, formulaire de commande fournisseur, formulaire de fournisseur, changement de mot de passe, gestion des emplacements. La gestion des emplacements n'est pas demandée par le cahier des charges : avec deux emplacements créés par les seeders, elle peut attendre une version ultérieure.
