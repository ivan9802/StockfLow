# Design system v2

La version 2 prolonge la charte de `docs/03-design/ui-guidelines.md` : même palette, mêmes polices, mêmes composants Blade. Elle change les rayons, introduit des ombres légères, ajoute des états de chargement et un mouvement maîtrisé. Les surcharges sont dans `resources/scss/_design-system.scss`, chargé après Bootstrap.

## Couleurs

Palette inchangée : neutres chauds, accent bleu pétrole `#0B5C6E`, états succès `#256B3C`, alerte `#7A5200` (graphique `#9A6700`), danger `#A32A1F`, information `#3D5368`. Les contrastes AA vérifiés dans la charte restent valables.

Ajouts pour les graphiques et les évolutions :

| Jeton | Valeur | Emploi |
| :-- | :-- | :-- |
| `--sf-chart-1` | `#0B5C6E` | Série principale (entrées, chiffre d'affaires, entrepôt) |
| `--sf-chart-2` | `#C2672E` | Série secondaire (sorties, point de vente) |
| `--sf-chart-3` | `#3D5368` | Troisième série éventuelle |
| `--sf-good` / `--sf-bad` | `#256B3C` / `#A32A1F` | Évolution favorable ou défavorable |

Le niveau de stock reprend les couleurs d'état : vert pour « En stock », ocre pour « Sous le seuil », brique pour « En rupture ».

## Rayons et ombres

| Jeton | v1 | v2 | Emploi |
| :-- | :-- | :-- | :-- |
| `$border-radius` | 4 px | 6 px | Champs, boutons |
| `$border-radius-lg` | 6 px | 10 px | Cartes, tableaux, bandeaux, menus |
| `$border-radius-xl` | | 14 px | Fenêtres modales |
| Badges | 2 px | pilule | Badges de statut et d'évolution |
| `--sf-shadow-1` | aucune | `0 1px 2px` à 6 % | Toute surface posée sur le fond |
| `--sf-shadow-2` | | deux couches à 6 % | Carte cliquable survolée |
| `--sf-shadow-3` | | `0 8px 28px` à 14 % | Ce qui flotte : menus, modales, toasts |

Les ombres restent discrètes : elles séparent la surface du fond, sans effet de carte flottante.

## Composants modifiés

| Composant | Changement |
| :-- | :-- |
| Boutons | Transition des couleurs, léger enfoncement au clic, état `is-loading` (indicateur tournant à gauche du libellé) posé automatiquement sur tout bouton d'envoi d'un formulaire `data-once` |
| Champs | Bordure plus foncée au survol, transition du focus |
| Tableaux | En-têtes figés au défilement, transition du survol de ligne |
| Navigation latérale | Entrées arrondies, repère de page active animé (filet qui se déploie) |
| Menus déroulants | Rayon large, ombre de niveau 3, entrées arrondies, apparition douce |
| Modales | Sans bordure, rayon 14 px, entrée par glissement léger |
| Bandeaux et toasts | Filet coloré de 4 px à gauche, apparition animée |
| États vides | Icône dans une pastille ronde aux couleurs de l'accent |
| Chargement | Classe `.skeleton` (reflet animé) et voile sur tout bloc `aria-busy="true"` |
| Connexion | Deux colonnes sur ordinateur : présentation de l'outil à gauche, formulaire à droite ; une colonne sur téléphone |

## Nouveaux composants

| Composant | Fichier | Rôle |
| :-- | :-- | :-- |
| `<x-dash-kpi>` | `components/dash-kpi.blade.php` | Carte d'indicateur : libellé, icône, valeur, évolution ou aide |
| `dashboard._delta` | `dashboard/_delta.blade.php` | Évolution : flèche, pourcentage, valeur de référence, phrase pour lecteur d'écran |
| `<x-chart-card>` | `components/chart-card.blade.php` | Bloc de graphique avec état vide et repli texte |

## Règle sur les styles en ligne

La politique de sécurité du contenu interdit les attributs `style`. Toute largeur ou tout décalage passe par une classe (`.form-wide`, `.input-cost`, décalages d'animation par `nth-child`). Chart.js modifie le style des `canvas` par JavaScript (CSSOM), ce que la politique autorise.
