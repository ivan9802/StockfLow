# Animations

Toutes les animations sont en CSS (`resources/scss/_motion.scss`) ou en JavaScript natif, sauf celles des graphiques, assurées par Chart.js. Aucune bibliothèque d'animation n'a été ajoutée.

## Principes

- Durées de 120 à 650 ms, courbe de décélération (`--sf-ease`).
- Rien ne retarde une saisie : les animations portent sur l'apparition et la transition, jamais sur un champ actif.
- Pas d'animation en boucle, sauf les indicateurs de chargement.
- Seules `transform` et `opacity` sont animées pour les apparitions, ce qui reste fluide sur un téléphone d'entrée de gamme.
- La politique de sécurité du contenu interdit les attributs `style` : les décalages en cascade passent par `nth-child`, pas par une variable posée sur l'élément.

## Catalogue

| Où | Effet | Durée | Déclencheur |
| :-- | :-- | :-- | :-- |
| Toutes les pages | En-tête qui monte de 8 px en apparaissant, contenu en fondu | 320 et 360 ms | Ouverture de la page |
| Tableau de bord | Cartes d'indicateurs en cascade, 45 ms d'écart | 400 ms | Ouverture |
| Tableau de bord | Rangées de graphiques et d'activité en fondu montant | 420 ms | Ouverture |
| Tableau de bord | Valeur qui défile de l'ancien au nouveau chiffre, puis surlignage bref | 650 et 900 ms | Changement de période |
| Graphiques | Tracé progressif à la création, transition entre deux jeux de données | 600 ms | Ouverture, changement de période |
| Tableau de bord | Voile léger sur les indicateurs, indicateur tournant sur « Appliquer » | Durée de la requête | Requête en cours |
| Cartes cliquables | Élévation de 2 px et ombre au survol | 160 ms | Survol |
| Boutons | Changement de couleur, enfoncement de 1 px au clic, indicateur tournant à l'envoi | 140 ms | Survol, clic, envoi |
| Navigation latérale | Filet de la page active qui se déploie | 180 ms | Page active |
| Menus déroulants, résultats de recherche | Apparition en fondu montant | 160 ms | Ouverture |
| Modales | Glissement de 12 px et léger agrandissement | 220 ms | Ouverture |
| Toasts | Glissement depuis la droite | 260 ms | Confirmation d'une opération |
| Bandeaux | Fondu montant | 260 ms | Affichage d'un message |
| Listes filtrées | Lignes en fondu, 18 ms d'écart | 260 ms | Résultat d'un filtre instantané |
| Chargement | Reflet sur les squelettes `.skeleton` | 1,2 s en boucle | Contenu attendu |

## Moins d'animations

Quand le système demande moins d'animations (`prefers-reduced-motion: reduce`) :

- les apparitions, survols et transitions sont ramenés à 1 ms : le contenu s'affiche directement ;
- les valeurs du tableau de bord prennent leur nouvelle valeur sans défiler ;
- les graphiques sont dessinés sans animation (`animation: false` dans Chart.js) ;
- les indicateurs de chargement restent visibles, immobiles.

Vérifié dans Chrome avec l'émulation de cette préférence : graphiques complets, aucune erreur (voir `tests-ui-ux.md`).
