# Journal des versions

Format inspiré de « Keep a Changelog ». Dates au format JJ/MM/AAAA.

## 1.0.0 (en cours de recette)

### Ajouté

- Authentification par e-mail avec limitation des tentatives, changement de mot de passe imposé après attribution par l'administrateur, trois profils aux droits vérifiés côté serveur sur chaque route.
- Catalogue : produits avec référence et code-barres EAN-13 générés, images contrôlées, catégories, fournisseurs, recherche instantanée et filtres.
- Stock par produit et par emplacement (entrepôt central et point de vente), historique complet des mouvements avec stock avant et après.
- Sorties internes et ajustements manuels avec motif obligatoire.
- Commandes fournisseurs, réceptions totales ou partielles, réception sans commande, solde d'une commande.
- Transferts entre emplacements en une étape, annulables, avec bon de transfert.
- Point de vente : disponibilité en direct, refus en bloc d'une vente en stock insuffisant, protection contre le double envoi, bon de sortie imprimable, annulation motivée.
- Inventaires : comptage sur tablette enregistré ligne par ligne, théorique relevé à la saisie, écarts justifiés, validation par l'administrateur, régularisation automatique.
- Alertes de réapprovisionnement au franchissement du seuil, centre d'alertes.
- Tableau de bord en direct, rapports CSV et PDF, journal d'audit consultable.
- Commande `stock:verify` qui rejoue l'historique pour vérifier chaque niveau de stock.
- Jeu de démonstration de trois mois d'activité.
- Environnement Docker de développement et de production, scripts de déploiement, de sauvegarde et de restauration.

### Corrigé pendant le développement

- Interblocage à la création d'un compteur de références lors de ventes simultanées (14/11/2026).
- Fichier non image accepté au téléversement s'il portait une extension d'image (14/11/2026).
- Erreur sur le tableau de bord due à une colonne ambiguë (14/11/2026).
- Écart d'inventaire compté deux fois quand une vente avait lieu avant le comptage : le théorique est désormais relevé à la saisie (révision de D-007, 13/11/2026).
