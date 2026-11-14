# Guide utilisateur StockFlow

Ce guide s'adresse aux vendeurs et aux gestionnaires de stock. Les tâches propres à l'administrateur (comptes, validation des inventaires, journal d'audit) sont décrites dans `admin-guide.md`.

StockFlow suit le stock de deux emplacements : « Entrepôt central » et « Point de vente Plateau ». Chaque entrée ou sortie de marchandise y laisse une trace : qui, quand, combien, stock avant et après, motif.

## Sommaire

1. Se connecter et changer son mot de passe
2. Se repérer dans l'application
3. Rechercher un produit
4. Vendre au point de vente (vendeur)
5. Consulter ses ventes
6. Consulter l'état des stocks
7. Sortie interne et ajustement (gestionnaire)
8. Transférer de la marchandise (gestionnaire)
9. Commande fournisseur de bout en bout (gestionnaire)
10. Réception sans commande (gestionnaire)
11. Inventaire physique (gestionnaire)
12. Alertes de stock (gestionnaire)
13. Exports et bons imprimables (gestionnaire)
14. Créer ou modifier un produit (gestionnaire)
15. Questions fréquentes

## 1. Se connecter et changer son mot de passe

### Première connexion

L'administrateur vous remet votre adresse e-mail et un mot de passe provisoire.

1. Ouvrez l'adresse de StockFlow dans votre navigateur.
2. Saisissez votre « Adresse e-mail » et votre « Mot de passe ». Le bouton « Afficher » montre le mot de passe saisi.
3. Cochez « Rester connecté sur cet appareil » seulement sur un poste qui vous est personnel.
4. Cliquez sur « Se connecter ».
5. L'écran « Changer mon mot de passe » s'ouvre avec le message « Choisissez votre propre mot de passe ». Tant que vous ne l'avez pas fait, aucun autre écran n'est accessible.
6. Saisissez le « Mot de passe actuel » (le provisoire), puis le « Nouveau mot de passe » : 12 caractères au moins. Une phrase de plusieurs mots fait un bon mot de passe.
7. Cliquez sur « Enregistrer le mot de passe ».

### Changer son mot de passe plus tard

1. Cliquez sur votre nom, en haut à droite.
2. Choisissez « Changer mon mot de passe ».
3. Remplissez les deux champs et cliquez sur « Enregistrer le mot de passe ».

### Se déconnecter

Cliquez sur votre nom, en haut à droite, puis sur « Se déconnecter ». Faites-le toujours sur un poste partagé.

### Mot de passe oublié

L'application n'envoie pas d'e-mail. Adressez-vous à votre administrateur : il vous attribue un nouveau mot de passe provisoire, que vous changerez à la connexion suivante.

Après 5 essais erronés en une minute, la connexion est bloquée quelques instants. Patientez avant de réessayer.

## 2. Se repérer dans l'application

Le menu de gauche dépend de votre profil. Sur tablette ou téléphone, il s'ouvre avec le bouton en haut à gauche de l'écran.

| Entrée du menu | Vendeur | Gestionnaire de stock |
| :-- | :-- | :-- |
| Tableau de bord | Oui (réduit) | Oui (complet) |
| Produits | Oui | Oui |
| Catégories, Fournisseurs | Non | Oui |
| État des stocks | Oui | Oui |
| Mouvements, Transferts, Inventaires | Non | Oui |
| Achats : Commandes, Réceptions | Non | Oui |
| Point de vente | Oui | Non |
| Historique des ventes | Oui (ses ventes) | Oui (toutes les ventes) |
| Pilotage : Alertes, Rapports | Non | Oui |

À la connexion, le vendeur arrive directement sur le « Point de vente ». Le gestionnaire arrive sur le « Tableau de bord ».

Le tableau de bord du vendeur montre « Mes ventes du jour », les « Produits en rupture », les « Produits sous le seuil » et « Mes dernières ventes ». Celui du gestionnaire montre la valeur du stock, les produits à réapprovisionner, les dernières opérations, la répartition par emplacement et les commandes en attente. Ses chiffres se mettent à jour tout seuls toutes les 30 secondes.

Le nom de votre emplacement d'affectation, s'il y en a un, est rappelé en bas du menu.

Les droits sont vérifiés par le serveur à chaque action. Un écran qui n'apparaît pas dans votre menu vous sera refusé même si vous en tapez l'adresse.

## 3. Rechercher un produit

### Depuis n'importe quel écran

1. Cliquez dans la zone de recherche en haut de l'écran, ou appuyez sur la touche « / ».
2. Tapez au moins 2 caractères : début du nom, référence (SKU) ou code-barres.
3. Choisissez un résultat avec la souris, ou avec les flèches puis Entrée. La fiche du produit s'ouvre.

La fiche montre la catégorie, le prix de vente, le seuil d'alerte, le stock par emplacement et le total, et le code-barres. Le gestionnaire y voit aussi le prix d'achat, le fournisseur principal et les derniers mouvements.

### Depuis la liste des produits

1. Ouvrez « Produits ».
2. Tapez dans « Recherche » (nom, référence ou code-barres). La liste se filtre au fil de la saisie.
3. Affinez si besoin avec « Catégorie », « État du stock » (« En stock », « Sous seuil », « Rupture ») ou « Fiche » (« Actives », « Inactives »).

## 4. Vendre au point de vente (vendeur)

Le vendeur vend uniquement depuis son point de vente d'affectation. Le gestionnaire de stock ne saisit pas de vente.

### Enregistrer une vente

1. Ouvrez « Point de vente ». L'emplacement est rappelé sous le titre.
2. Dans « Rechercher un produit à vendre », tapez le nom, la référence, ou lisez le code-barres avec la douchette.
3. Appuyez sur Entrée ou cliquez sur le produit. Il s'ajoute au panier avec la quantité 1. Avec une douchette, le produit lu s'ajoute directement.
4. Pour vendre plusieurs unités, modifiez la « Quantité » de la ligne, ou ajoutez le même produit une nouvelle fois.
5. Pour retirer une ligne, cliquez sur la croix rouge en bout de ligne. « Vider le panier » efface tout.
6. Saisissez, si le client le souhaite, le « Nom du client ». Ce champ est facultatif.
7. Vérifiez le « Total ».
8. Laissez cochée la case « Imprimer le bon de sortie après l'enregistrement » si le client veut son bon.
9. Cliquez sur « Enregistrer la vente ».

Si la case d'impression est cochée, le bon de sortie s'ouvre et la fenêtre d'impression du navigateur apparaît. Sinon, la fiche de la vente s'affiche avec le message « Vente BS-AAAA-NNNNNN enregistrée. ».

Le prix appliqué est celui de la fiche produit au moment de l'enregistrement. Il ne se modifie pas au point de vente.

### Stock insuffisant

La colonne « Disponible » du panier montre le stock du point de vente.

- Un produit en rupture ne s'ajoute pas : le message « [produit] est en rupture dans ce point de vente. » s'affiche.
- Si une quantité dépasse le disponible, la ligne passe en rouge avec « Seulement N en stock » et le bouton « Enregistrer la vente » reste grisé. Corrigez la quantité.
- Si un collègue a vendu le même article entre-temps, le serveur refuse toute la vente avec « Stock insuffisant : la vente n'a pas été enregistrée. » et indique pour chaque produit en cause le disponible et la quantité demandée. Le panier est mis à jour avec le stock réel : corrigez, puis enregistrez de nouveau.

Une vente n'est jamais enregistrée à moitié. Soit tous les articles sortent du stock, soit aucun.

### Imprimer ou réimprimer le bon de sortie

1. Ouvrez la vente depuis « Historique des ventes ».
2. Cliquez sur « Bon de sortie ».
3. Cliquez sur « Imprimer ». « Retour » ramène à l'écran précédent.

### Double clic et réseau lent

Vous pouvez cliquer deux fois sur « Enregistrer la vente » sans risque. Le bouton se grise et affiche « Enregistrement... » dès le premier clic. Si le même panier est malgré tout envoyé deux fois, le second envoi est refusé avec « Cette vente a déjà été envoyée. Consultez l'historique avant de la saisir à nouveau. ».

En cas de doute après une coupure, ouvrez « Historique des ventes » avant de ressaisir la vente.

### Annulation

Le vendeur ne peut pas annuler une vente. Signalez l'erreur à un gestionnaire ou à l'administrateur, avec le numéro du bon.

## 5. Consulter ses ventes

1. Ouvrez « Historique des ventes ». Pour le vendeur, l'écran s'intitule « Mes ventes » et ne montre que ses propres ventes.
2. Filtrez avec « Recherche » (numéro de bon ou client), « Statut » (« Enregistrées », « Annulées »), « Du » et « Au ».
3. Cliquez sur un numéro de bon pour ouvrir la vente.

Le gestionnaire voit toutes les ventes et peut filtrer par « Vendeur ». Il dispose du bouton « Export CSV ».

## 6. Consulter l'état des stocks

1. Ouvrez « État des stocks ».
2. Le tableau donne, pour chaque produit, la quantité par emplacement, le total, le seuil et l'état.
3. Filtrez avec « Recherche », « Catégorie » et « État » (« En stock », « Sous seuil », « Rupture »).

Le gestionnaire voit en plus la valeur du stock au prix d'achat et dispose des boutons « Export CSV » et « État PDF ».

Pour l'historique d'un produit, ouvrez sa fiche puis « Toute la fiche de stock » (gestionnaire uniquement).

## 7. Sortie interne et ajustement (gestionnaire)

Une sortie interne retire du stock pour un usage de l'entreprise : usage du bureau, casse, don, échantillon. Un ajustement corrige une erreur de saisie, à la hausse ou à la baisse. Le motif est obligatoire dans les deux cas.

1. Ouvrez « Mouvements », puis cliquez sur « Sortie ou ajustement ». Depuis la fiche de stock d'un produit, le même bouton présélectionne le produit.
2. Choisissez le « Type de mouvement » : « Sortie interne », « Ajustement à la baisse » ou « Ajustement à la hausse ».
3. Recherchez le produit dans « Rechercher le produit » et choisissez-le. Son nom s'affiche sous la zone de recherche.
4. Choisissez l'« Emplacement ».
5. Saisissez la « Quantité ».
6. Saisissez le « Motif ». Exemple : « Toner défectueux renvoyé au fournisseur ».
7. Cliquez sur « Enregistrer le mouvement ».

Une sortie ou un ajustement à la baisse qui dépasse le stock de l'emplacement est refusé.

Le mouvement enregistré ne se modifie pas et ne se supprime pas. Une erreur se corrige par un nouveau mouvement en sens inverse, avec un motif qui l'explique.

## 8. Transférer de la marchandise (gestionnaire)

Le transfert se fait en une seule étape : la marchandise quitte le départ et arrive à destination au même instant.

1. Ouvrez « Transferts », puis cliquez sur « Nouveau transfert ».
2. Vérifiez le « Départ » et l'« Arrivée ». Ils doivent être différents.
3. Saisissez une « Note » si besoin, par exemple « Réassort hebdomadaire ».
4. Dans « Produits à transférer », recherchez chaque produit et ajoutez-le. La colonne « Disponible » donne le stock du départ.
5. Saisissez la « Quantité » de chaque ligne.
6. Cliquez sur « Effectuer le transfert ».
7. Sur la fiche du transfert, cliquez sur « Bon de transfert », puis sur « Imprimer ». Le bon accompagne la marchandise.

Si une seule quantité dépasse le disponible, rien n'est transféré. Le vendeur voit les nouvelles quantités au point de vente dès l'enregistrement.

### Annuler un transfert

1. Ouvrez le transfert depuis « Transferts ».
2. Cliquez sur « Annuler le transfert ».
3. Saisissez le « Motif » (5 caractères au moins) et confirmez.

La marchandise revient à l'emplacement de départ. L'annulation est refusée si la destination a déjà vendu ou déplacé une partie des quantités.

## 9. Commande fournisseur de bout en bout (gestionnaire)

Une commande passe par les états « Brouillon », « Commandée », « Reçue en partie », puis « Reçue » ou « Soldée ». Elle peut aussi être « Annulée » tant que rien n'a été reçu.

### Préparer la commande (brouillon)

1. Ouvrez « Commandes », puis cliquez sur « Nouvelle commande ».
2. Choisissez le « Fournisseur ». Son délai moyen de livraison est indiqué entre parenthèses.
3. Choisissez « Livrer à » : l'emplacement qui recevra la marchandise.
4. Laissez « Livraison prévue le » vide pour que la date soit calculée à partir du délai du fournisseur, ou fixez-la.
5. Dans « Articles commandés », ajoutez chaque produit, saisissez la « Quantité » et vérifiez le « Prix d'achat unitaire », prérempli avec le prix d'achat de la fiche.
6. Cliquez sur « Enregistrer le brouillon ».

Le brouillon reste modifiable avec le bouton « Modifier ».

### Passer la commande

1. Ouvrez la commande.
2. Cliquez sur « Passer la commande » et confirmez. La commande n'est plus modifiable.

### Réceptionner une livraison

À chaque livraison, même partielle :

1. Ouvrez la commande, ou ouvrez « Réceptions » puis « Réceptionner une commande » pour voir les commandes en attente.
2. Cliquez sur « Réceptionner une livraison ».
3. Saisissez le « Numéro du bon de livraison » du fournisseur et vérifiez la « Date de réception ».
4. Pour chaque produit, la colonne « Attendu » donne ce qui reste à recevoir. Saisissez dans « Reçu aujourd'hui » ce qui est réellement arrivé. Laissez 0 pour un produit non livré.
5. Cliquez sur « Enregistrer la réception ».

Le stock de l'emplacement augmente aussitôt et un bon de réception BR-AAAA-NNNNNN est créé. Sa fiche propose le bouton « Bon de réception » pour l'imprimer.

Une livraison partielle laisse la commande à l'état « Reçue en partie » : vous réceptionnerez la suite de la même façon. Quand tout est reçu, la commande passe à « Reçue ».

Une quantité supérieure à l'attendu est refusée. Un excédent se saisit en réception sans commande (section 10).

### Solder une commande

Si le fournisseur ne livrera jamais le reliquat :

1. Ouvrez la commande, à l'état « Reçue en partie ».
2. Cliquez sur « Solder ».
3. Saisissez le motif et confirmez.

Le reliquat n'est plus attendu. Le stock déjà reçu n'est pas modifié.

### Annuler une commande

Une commande en « Brouillon » ou « Commandée » s'annule avec le bouton « Annuler » et un motif. Une commande dont une partie a été reçue ne s'annule pas : soldez-la.

## 10. Réception sans commande (gestionnaire)

Réservée aux achats de dépannage et aux excédents livrés au-delà d'une commande. Le circuit normal reste la commande fournisseur.

1. Ouvrez « Réceptions », puis cliquez sur « Réception sans commande ».
2. Choisissez le « Fournisseur » et l'« Emplacement de réception ».
3. Saisissez le « Numéro du bon de livraison », la « Date de réception » et des « Notes » si besoin.
4. Dans « Articles reçus », ajoutez chaque produit, sa « Quantité » et son « Prix d'achat unitaire ».
5. Cliquez sur « Enregistrer la réception ».

## 11. Inventaire physique (gestionnaire)

Un inventaire compare le stock compté en rayon au stock enregistré. Les écarts ne touchent le stock qu'après validation par l'administrateur.

Les ventes continuent pendant le comptage. Le stock enregistré (« Théorique ») de chaque produit est relevé au moment où vous saisissez sa quantité. Saisissez donc au fil du comptage, sur la tablette, plutôt qu'en fin de journée.

### Ouvrir l'inventaire

1. Ouvrez « Inventaires », puis cliquez sur « Ouvrir un inventaire ».
2. Choisissez l'« Emplacement à inventorier ».
3. Choisissez un « Périmètre » (une catégorie) pour un inventaire tournant, plus court, ou laissez « Tous les produits ».
4. Ajoutez des « Notes » si besoin.
5. Cliquez sur « Ouvrir l'inventaire ».

Un seul inventaire peut être ouvert à la fois sur un même emplacement.

### Imprimer la feuille de comptage

Sur la fiche de l'inventaire, cliquez sur « Feuille de comptage », puis sur « Imprimer ». La feuille sert aux compteurs qui travaillent sur papier.

### Compter sur tablette

1. Ouvrez l'inventaire sur la tablette.
2. Pour chaque produit, saisissez la quantité trouvée dans la colonne « Compté ». La ligne est enregistrée dès que vous quittez le champ.
3. Le « Théorique » et l'« Écart » n'apparaissent qu'après votre saisie : le comptage se fait à l'aveugle.
4. Utilisez le filtre « Afficher » pour ne voir que les « Lignes non comptées » ou les « Lignes en écart ».
5. Le bouton « Enregistrer le comptage » enregistre toutes les lignes de la page d'un coup.

Vous pouvez interrompre le comptage et le reprendre plus tard.

### Justifier les écarts

Pour chaque ligne en écart, surlignée en jaune, saisissez dans « Justification » la cause constatée, en 5 caractères au moins. Exemple : « deux unités retrouvées endommagées, mises au rebut ».

### Soumettre à validation

1. Vérifiez que « Lignes comptées » affiche toutes les lignes.
2. Cliquez sur « Soumettre à validation », puis sur « Soumettre ».

La soumission est refusée tant qu'une ligne n'est pas comptée (« Il reste N lignes à compter. ») ou qu'un écart n'est pas justifié. L'inventaire passe ensuite « En attente de validation ». L'administrateur le valide ou le renvoie en comptage avec un commentaire, affiché en haut de la fiche sous « Renvoyé en comptage par l'administrateur ».

### Annuler un inventaire

Tant qu'il n'est pas validé, un inventaire s'annule avec le bouton « Annuler » et un motif. Aucun écart n'est alors appliqué.

## 12. Alertes de stock (gestionnaire)

Une alerte est émise quand une sortie fait passer le stock total d'un produit au niveau de son seuil d'alerte ou en dessous. Si un « Seuil de réassort » est fixé pour un emplacement, une alerte est aussi émise quand le stock de cet emplacement le franchit. Les administrateurs et les gestionnaires actifs la reçoivent.

Une seule alerte est émise au franchissement. Tant que vous ne l'avez pas lue, une nouvelle sortie du même produit n'en crée pas d'autre.

1. Le nombre d'alertes non lues apparaît sur la cloche, en haut à droite, et à côté de « Alertes » dans le menu.
2. Ouvrez « Alertes ».
3. La partie « Produits à réapprovisionner » liste les produits dont le stock total est au plus leur seuil, ruptures en tête, avec le fournisseur et son délai moyen.
4. La partie « Notifications » liste les alertes reçues. Cliquez sur une alerte pour ouvrir la fiche du produit.
5. Selon le cas, transférez du stock vers le point de vente (section 8) ou cliquez sur « Commander » pour préparer une commande fournisseur (section 9).
6. Cliquez sur « Tout marquer comme lu » une fois les alertes traitées.

## 13. Exports et bons imprimables (gestionnaire)

### Rapports

Ouvrez « Rapports ».

| Rapport | Formats | Contenu |
| :-- | :-- | :-- |
| État des stocks | PDF, CSV | Quantité par emplacement, total, seuil, état et valeur au prix d'achat, à l'instant de l'export |
| Historique des mouvements | PDF, CSV | Mouvements sur une période (« Du », « Au ») et un « Emplacement » au choix |
| Ventes | CSV | Une ligne par article vendu, avec le prix du jour de la vente |

Le PDF des mouvements est limité à 2 000 mouvements. Au-delà, utilisez le CSV.

Les fichiers CSV s'ouvrent directement dans Excel (séparateur point-virgule, accents conservés). Chaque export est tracé dans le journal d'audit.

Les mêmes exports sont aussi proposés en haut des écrans « État des stocks », « Mouvements » et « Historique des ventes ». Sur « Mouvements », l'export reprend les filtres affichés.

### Bons

Les bons s'impriment depuis la fiche de leur document :

- « Bon de sortie », depuis une vente ;
- « Bon de réception », depuis une réception ;
- « Bon de transfert », depuis un transfert ;
- « Feuille de comptage », depuis un inventaire.

## 14. Créer ou modifier un produit (gestionnaire)

1. Ouvrez « Produits », puis cliquez sur « Nouveau produit ».
2. Saisissez le « Libellé » et choisissez la « Catégorie ». La catégorie fixe le préfixe de la référence, attribuée à l'enregistrement.
3. Choisissez le « Fournisseur principal » si vous le connaissez.
4. Saisissez le « Code-barres » du fabricant. Laissé vide, un code interne est généré.
5. Saisissez le « Prix d'achat » et le « Prix de vente », en F CFA, sans décimales.
6. Saisissez le « Seuil d'alerte », comparé au stock total tous emplacements confondus.
7. Saisissez si besoin un « Seuil de réassort » par emplacement.
8. Ajoutez une « Image » : JPEG, PNG ou WebP, 2 Mo au plus.
9. Cliquez sur « Créer le produit ».

Un produit est créé avec un stock nul. Le stock arrive par une réception.

Pour modifier un produit, ouvrez sa fiche et cliquez sur « Modifier ». Décochez « Produit actif » pour qu'il ne puisse plus être vendu ni commandé : son stock et son historique restent visibles. Le bouton « Supprimer » est refusé tant qu'il reste du stock.

## 15. Questions fréquentes

### Pourquoi ma vente est-elle refusée ?

Une des quantités dépasse le stock du point de vente au moment de l'enregistrement, souvent parce qu'un collègue vient de vendre le même article. Le message indique le produit, le disponible et la quantité demandée. Corrigez la ligne et enregistrez de nouveau. Rien n'a été enregistré entre-temps.

### Le bouton « Enregistrer la vente » reste grisé

Le panier est vide, une quantité vaut 0, ou une quantité dépasse le disponible (ligne en rouge). Corrigez la ligne en cause.

### J'ai cliqué deux fois sur « Enregistrer la vente ». La vente est-elle en double ?

Non. Le second envoi est refusé. Vérifiez dans « Historique des ventes ».

### Comment corriger une vente erronée ?

Un gestionnaire ou l'administrateur annule la vente avec un motif : les articles reviennent au stock. Le vendeur saisit ensuite la bonne vente.

### Comment corriger une réception erronée ?

Un bon de réception enregistré ne s'annule pas. Passez un ajustement manuel (section 7) : « Ajustement à la baisse » si vous avez saisi trop, « Ajustement à la hausse » si vous avez saisi trop peu. Citez la référence du bon de réception (BR-AAAA-NNNNNN) dans le motif.

### Pourquoi je ne vois pas le prix d'achat ?

Le prix d'achat et la valeur du stock sont réservés aux gestionnaires et à l'administrateur. Le vendeur ne voit que le prix de vente.

### Pourquoi je ne peux pas vendre depuis l'entrepôt ?

Une vente ne part que d'un point de vente. Le vendeur ne vend que depuis le point de vente auquel il est affecté. Pour vendre un article stocké à l'entrepôt, un gestionnaire le transfère d'abord au point de vente.

### Le stock affiché au point de vente ne correspond pas au rayon

Signalez-le à un gestionnaire. Il vérifie l'historique du produit et, si l'écart se confirme, ouvre un inventaire ou passe un ajustement justifié.

### Mon inventaire ne peut pas être soumis

Une ligne n'est pas comptée ou un écart n'est pas justifié. Utilisez le filtre « Afficher » avec « Lignes non comptées », puis « Lignes en écart ».

### Le message « Ce compte a été désactivé » s'affiche

L'administrateur a désactivé votre compte. Adressez-vous à lui.
