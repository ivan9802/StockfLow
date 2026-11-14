# Guide de l'administrateur StockFlow

Ce guide décrit les tâches réservées au profil administrateur. Les opérations courantes (ventes, transferts, commandes, inventaires) sont décrites dans `user-guide.md` : l'administrateur peut toutes les effectuer.

L'installation sur le serveur est décrite dans `docs/07-deployment/deployment.md`, les sauvegardes dans `docs/07-deployment/backup-restore.md`.

## 1. Premier démarrage et compte initial

Au premier déploiement, l'application crée les deux emplacements (« Entrepôt central », code `ENT`, et « Point de vente Plateau », code `PDV`) et un premier compte administrateur.

L'adresse et le mot de passe de ce compte sont fixés ainsi :

| Variable de `.env.production` | Rôle | Valeur par défaut |
| :-- | :-- | :-- |
| `ADMIN_EMAIL` | Adresse de connexion | `admin@stockflow.example` |
| `ADMIN_NAME` | Nom affiché | `Administrateur` |
| `ADMIN_PASSWORD` | Mot de passe initial | Tiré au hasard |

Quand `ADMIN_PASSWORD` est absent, le mot de passe est généré et affiché une seule fois dans la sortie du script de déploiement, sur une ligne de la forme :

```text
Compte administrateur créé : admin@stockflow.example / xxxxxxxxxxxxxxxx (à changer à la première connexion).
```

Ce compte n'est créé que s'il n'existe encore aucun administrateur. Relancer le déploiement ne le recrée pas et ne change pas son mot de passe.

Première connexion :

1. Ouvrez l'adresse de l'application et connectez-vous avec ce compte.
2. L'écran « Changer mon mot de passe » s'impose. Saisissez le mot de passe provisoire, puis un nouveau mot de passe de 12 caractères au moins.
3. Cliquez sur « Enregistrer le mot de passe ».
4. Si vous aviez fixé `ADMIN_PASSWORD`, retirez cette ligne de `.env.production`.
5. Vérifiez les emplacements dans « Emplacements » et corrigez leur nom ou leur adresse si besoin (section 3).
6. Créez les comptes du personnel (section 2).

Créez un second compte administrateur nominatif et gardez-le en réserve. Il permet de reprendre la main si le premier administrateur perd son mot de passe : un administrateur ne peut pas réinitialiser son propre mot de passe depuis la liste des comptes.

## 2. Gérer les comptes

Ouvrez « Utilisateurs » dans le groupe « Administration ». La liste donne pour chaque compte le nom, l'adresse e-mail, le profil, l'emplacement, la dernière connexion et l'état. La mention « Mot de passe provisoire » signale un compte qui n'a pas encore choisi son mot de passe.

### Créer un compte

1. Cliquez sur « Nouveau compte ».
2. Saisissez le « Nom complet » et l'« Adresse e-mail ». L'adresse sert d'identifiant et doit être unique.
3. Choisissez le « Profil » : « Administrateur », « Gestionnaire de stock » ou « Vendeur ».
4. Choisissez l'« Emplacement d'affectation ». Il est obligatoire pour un vendeur et doit être un point de vente : le vendeur ne vend que depuis cet emplacement. Pour un autre profil, laissez « Aucun ».
5. Laissez « Mot de passe provisoire (facultatif) » vide pour qu'un mot de passe soit généré, ou saisissez-en un de 12 caractères au moins.
6. Laissez « Compte actif » coché.
7. Cliquez sur « Créer le compte ».

Le message de confirmation affiche le mot de passe provisoire généré, une seule fois. Notez-le et remettez-le en main propre. L'utilisateur devra le changer à sa première connexion.

Un vendeur sans point de vente est refusé avec le message « Un vendeur doit être affecté à un point de vente. ».

### Modifier un compte

1. Cliquez sur le nom de la personne dans la liste.
2. Modifiez le nom, l'adresse, le profil, l'emplacement ou l'état.
3. Cliquez sur « Enregistrer les modifications ».

Un changement de profil prend effet à la requête suivante de l'utilisateur. Vous ne pouvez pas retirer vos propres droits d'administrateur ni désactiver votre propre compte.

### Désactiver plutôt que supprimer

Un compte ne se supprime pas. L'historique des opérations garde ainsi son auteur.

Quand une personne quitte l'entreprise :

1. Ouvrez son compte.
2. Décochez « Compte actif ».
3. Cliquez sur « Enregistrer les modifications ».

Le compte ne peut plus se connecter, et une session déjà ouverte est fermée à la requête suivante. Le compte ne reçoit plus d'alertes de stock. Il reste visible dans la liste et dans l'historique.

### Réinitialiser un mot de passe oublié

L'application n'envoie pas d'e-mail. Un mot de passe oublié se réinitialise ici.

1. Vérifiez l'identité de la personne qui le demande.
2. Dans « Utilisateurs », cliquez sur « Réinitialiser le mot de passe » sur la ligne de son compte.
3. Confirmez avec « Réinitialiser ».
4. Le message affiche le nouveau mot de passe provisoire, une seule fois. Il est formé de quatre groupes de quatre caractères séparés par des tirets, pour être dicté facilement.
5. Remettez-le à la personne. Elle devra le changer à sa connexion suivante.

La réinitialisation ferme toutes les sessions ouvertes du compte et laisse une trace dans le journal d'audit.

## 3. Emplacements

Ouvrez « Emplacements ». Le tableau donne pour chaque emplacement son code, son type, son adresse, le nombre d'unités et de références en stock.

Les deux emplacements sont fixes dans cette version : ils ne se créent ni ne se suppriment. Seuls leur nom et leur adresse se modifient.

1. Cliquez sur « Modifier » en bout de ligne.
2. Modifiez le « Nom » ou l'« Adresse » (commune, quartier, repère).
3. Cliquez sur « Enregistrer ».

## 4. Valider ou renvoyer un inventaire

La validation d'un inventaire est réservée à l'administrateur. Le gestionnaire compte et soumet ; vous contrôlez et appliquez.

Le nombre d'inventaires en attente apparaît à côté de « Inventaires » dans le menu.

1. Ouvrez « Inventaires » et filtrez sur le statut « En attente de validation ».
2. Ouvrez l'inventaire.
3. Lisez les indicateurs « Lignes comptées » et « Écarts ».
4. Choisissez « Lignes en écart » dans le filtre « Afficher ».
5. Pour chaque ligne, comparez « Compté », « Théorique » et « Écart », et lisez la « Justification ».
6. La mention « Stock modifié depuis le comptage » signale un produit vendu ou déplacé après sa saisie. Ce n'est pas une erreur : l'écart sera appliqué au stock actuel.

Ensuite, deux possibilités.

### Valider

1. Cliquez sur « Valider ».
2. Confirmez avec « Valider et régulariser ».

Chaque écart produit un mouvement « Régularisation d'inventaire », avec la justification en motif. L'inventaire passe à « Validé ». Cette opération ne s'annule pas.

Si un écart négatif dépasse le stock actuel du produit (vendu entre-temps), la validation est refusée en bloc et rien n'est appliqué. Renvoyez alors l'inventaire en comptage pour faire recompter la ligne.

### Renvoyer en comptage

1. Cliquez sur « Renvoyer en comptage ».
2. Indiquez dans le « Motif » ce qu'il faut vérifier, par exemple les lignes douteuses.
3. Confirmez.

L'inventaire revient à « Comptage en cours ». Votre commentaire s'affiche en haut de la fiche pour le gestionnaire.

### Corriger un inventaire validé

Un inventaire validé ne s'annule pas. Une erreur découverte après coup se corrige par un ajustement manuel justifié qui cite la référence de l'inventaire (voir « Sortie interne et ajustement » dans `user-guide.md`).

## 5. Annuler une vente ou un transfert

L'administrateur et le gestionnaire de stock peuvent annuler. Le motif est obligatoire (5 caractères au moins) et reste visible sur la fiche du document.

### Annuler une vente

1. Ouvrez « Historique des ventes » et cherchez le numéro du bon.
2. Ouvrez la vente.
3. Cliquez sur « Annuler la vente ».
4. Saisissez le « Motif » et cliquez sur « Annuler la vente » dans la fenêtre.

Les articles reviennent au stock du point de vente par des mouvements « Annulation de vente ». La vente reste dans l'historique avec le statut annulé, la date, l'auteur et le motif.

### Annuler un transfert

1. Ouvrez « Transferts », puis le transfert.
2. Cliquez sur « Annuler le transfert ».
3. Saisissez le « Motif » et confirmez.

La marchandise revient à l'emplacement de départ. L'annulation est refusée si la destination a déjà vendu ou déplacé une partie des quantités : il faudrait alors un nouveau transfert en sens inverse pour ce qui reste.

### Ce qui ne s'annule pas

Un bon de réception et un inventaire validé ne s'annulent pas. L'erreur se corrige par un ajustement manuel justifié. Un mouvement ne se modifie ni ne se supprime.

## 6. Lire le journal d'audit

Ouvrez « Journal d'audit ». Chaque ligne indique la date, l'utilisateur, l'action, une description, l'adresse IP et, dans « Valeurs », les champs modifiés (ancienne valeur barrée, nouvelle valeur ensuite).

Le journal enregistre notamment les connexions, les échecs de connexion, les déconnexions, les créations, modifications et suppressions, les annulations, les validations d'inventaire et les exports.

Filtres disponibles : « Recherche » (texte de la description), « Action », « Objet », « Utilisateur », « Du » et « Au ».

Exemples de recherches utiles :

- tentatives d'intrusion : « Action » = « Échec de connexion » ;
- annulations du mois : « Action » = « Annulation », avec « Du » et « Au » ;
- activité d'une personne : « Utilisateur », sur une période ;
- sorties de données : « Action » = « Export ».

Le journal est en ajout seul. Personne, pas même l'administrateur, ne peut modifier ou supprimer une ligne depuis l'application.

Pour l'historique détaillé des quantités (stock avant, stock après, motif), utilisez « Mouvements » et la fiche de stock de chaque produit.

## 7. Contrôler la cohérence du stock

La commande `stock:verify` rejoue tout l'historique des mouvements et vérifie deux choses :

1. pour chaque produit et chaque emplacement, la somme des mouvements est égale au stock enregistré ;
2. chaque mouvement part du stock laissé par le mouvement précédent.

Elle ne modifie rien. Sur le serveur de production, depuis le dossier de l'application :

```bash
docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan stock:verify
```

En développement :

```bash
docker compose exec app php artisan stock:verify
```

Résultat attendu :

```text
Stock cohérent : N mouvements rejoués sur M lignes de stock, aucun écart.
```

En cas d'écart, la commande affiche un tableau des lignes en cause (identifiants du produit et de l'emplacement) et se termine avec le code 1. L'option `--limit=50` affiche jusqu'à 50 écarts au lieu de 20.

Un écart ne doit jamais apparaître : toutes les variations passent par le moteur de stock. Si cela arrive, ne corrigez rien à la main dans la base. Notez la sortie complète, prévenez le responsable technique et vérifiez la dernière sauvegarde.

Le script de déploiement et le script de restauration lancent cette commande automatiquement.

## 8. Droits par profil

Les droits sont vérifiés par le serveur à chaque requête, y compris sur les appels internes de l'interface. Masquer un bouton ne suffirait pas : c'est le serveur qui refuse.

| Fonction | Administrateur | Gestionnaire de stock | Vendeur |
| :-- | :-- | :-- | :-- |
| Consulter le catalogue et l'état des stocks | Oui | Oui | Oui |
| Voir le prix d'achat et la valeur du stock | Oui | Oui | Non |
| Gérer produits et catégories | Oui | Oui | Non |
| Gérer les fournisseurs | Oui | Oui | Non |
| Commandes fournisseurs et réceptions | Oui | Oui | Non |
| Consulter les mouvements | Oui | Oui | Non |
| Sortie interne, ajustement manuel | Oui | Oui | Non |
| Transferts (créer, annuler) | Oui | Oui | Non |
| Ouvrir, compter, soumettre, annuler un inventaire | Oui | Oui | Non |
| Valider un inventaire ou le renvoyer en comptage | Oui | Non | Non |
| Saisir une vente | Oui, depuis un point de vente | Non | Oui, depuis son point de vente d'affectation |
| Consulter les ventes | Toutes | Toutes | Les siennes |
| Annuler une vente | Oui | Oui | Non |
| Alertes de stock | Oui | Oui | Non |
| Rapports et exports | Oui | Oui | Non |
| Tableau de bord | Complet | Complet | Réduit |
| Gérer les comptes | Oui | Non | Non |
| Modifier les emplacements | Oui | Non | Non |
| Journal d'audit | Oui | Non | Non |

Les trois profils sont fixes. On attribue un profil à un compte, on n'en crée pas de nouveau.

## 9. Bonnes pratiques

### Séparation des tâches

- Ne validez pas un inventaire que vous avez compté vous-même. L'application ne l'interdit pas à l'administrateur : c'est une règle d'organisation. Faites compter par un gestionnaire.
- Réservez le profil administrateur à une ou deux personnes. Un administrateur qui vend au quotidien devrait avoir un second compte, vendeur.
- Un compte par personne. Ne partagez jamais un compte entre plusieurs vendeurs : la traçabilité repose sur le nom de l'auteur de chaque opération.

### Revue mensuelle

Une fois par mois :

1. Dans le journal d'audit, filtrez sur « Échec de connexion » et cherchez des rafales sur un même compte.
2. Filtrez sur « Annulation » : chaque vente ou transfert annulé doit avoir un motif clair.
3. Dans « Mouvements », filtrez le « Type » sur « Ajustement manuel » puis « Sortie interne » et relisez les motifs.
4. Dans « Utilisateurs », désactivez les comptes des personnes parties et vérifiez les comptes jamais connectés.
5. Lancez `stock:verify`.
6. Vérifiez avec le responsable technique que les sauvegardes de la nuit existent et que le test de restauration du mois a été fait (voir `backup-restore.md`).

### Mots de passe

- Remettez les mots de passe provisoires en main propre, jamais par message écrit conservé.
- Une personne qui pense que son mot de passe est connu d'un tiers demande une réinitialisation.
