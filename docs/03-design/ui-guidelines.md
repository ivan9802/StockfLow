# Charte d'interface StockFlow

Ce document fixe les règles visuelles et d'interaction de StockFlow. Il sert de référence aux vues Blade, à la feuille de style Sass et aux composants. Les écrans sont décrits dans `docs/03-design/wireframes.md`.

Contraintes reprises du projet : Blade, Bootstrap 5 personnalisé par variables Sass et compilé avec Vite, JavaScript natif, Bootstrap Icons, aucune bibliothèque de graphiques (D-015), aucune ressource chargée depuis un CDN tiers.

## 1. Principes de conception

Trois situations de travail dictent les choix : le vendeur au comptoir avec un client devant lui, le gestionnaire debout dans l'entrepôt avec une tablette, l'administrateur ou le comptable qui contrôle des chiffres.

1. Le chiffre passe avant le décor. Quantités et montants sont alignés à droite, en chiffres tabulaires, dans une graisse plus forte que le libellé qui les accompagne. Aucun élément décoratif n'occupe la place d'une donnée.
2. Une action principale par écran. Chaque page n'a qu'un bouton plein de couleur d'accent, placé dans l'en-tête de page. Les autres actions sont en bouton contour ou en lien.
3. L'état du stock se lit sans la couleur. Un badge de stock porte toujours une icône et un libellé (« En stock », « Sous seuil », « Rupture »). Une personne daltonienne ou une impression en noir et blanc donnent la même information.
4. Un refus s'explique avec le chiffre. Quand une vente dépasse le disponible, le message donne la quantité disponible, l'emplacement concerné et ce que l'utilisateur peut faire. L'interface prévient avant l'envoi quand elle le peut, et le serveur tranche toujours.
5. Le doigt et le clavier valent la souris. Sur tablette, toute zone activable mesure au moins 44 px de côté. Au comptoir, une vente se saisit sans quitter le clavier : recherche, flèches, Entrée, quantité, validation.
6. Rien ne disparaît sans trace. L'interface ne propose pas de suppression sur un document validé : elle propose une annulation avec motif. Les écrans d'historique ne comportent aucun bouton de modification.

## 2. Identité

### Nom et logotype

Le produit s'appelle StockFlow, en un mot, avec un S et un F majuscules. Le nom de l'entreprise cliente (« Ivoire Bureautique & Tech » dans la démonstration) apparaît sur les documents imprimés et sur l'écran de connexion, jamais à la place du nom du produit dans la barre latérale.

Le logotype est textuel, sans pictogramme : « StockFlow » en Bricolage Grotesque, graisse 700, interlettrage de -0,01 em. « Stock » prend la couleur du texte, « Flow » la couleur d'accent. Sur le fond sombre de la barre latérale, « Stock » passe en `#E4E8E6` et « Flow » en `#7FC6D6`. Dans la barre latérale repliée, le logotype se réduit aux deux lettres « SF » dans la même police.

Taille minimale : 18 px de hauteur de capitale à l'écran. Pas de dégradé, pas d'ombre, pas d'animation sur le logotype.

### Ton rédactionnel

- Vouvoiement partout : « Saisissez la quantité reçue », jamais « Saisis ».
- Phrases courtes, à l'infinitif pour les boutons (« Enregistrer la vente », « Valider l'inventaire »), à l'impératif pour les consignes.
- Un bouton nomme l'action et son objet. Pas de « OK », pas de « Valider » seul, pas de « Cliquez ici ».
- Un message d'erreur dit ce qui s'est passé, puis ce qu'il faut faire. Il ne s'excuse pas et ne culpabilise pas.
- Le vocabulaire est celui du métier : bon de sortie, bon de réception, seuil d'alerte, emplacement, écart. Aucun terme technique (identifiant, requête, exception) n'atteint l'écran.
- Les nombres s'écrivent en chiffres, y compris dans les phrases : « 3 produits sous seuil ».
- Pas de point d'exclamation, pas d'emoji, pas de majuscules pour insister.

Exemples de messages.

| Situation | À écrire | À ne pas écrire |
| :-- | :-- | :-- |
| Stock insuffisant | « Stock insuffisant pour Cartouche toner HP 59A noir : 3 disponibles à la Boutique du Plateau, 5 demandés. Réduisez la quantité ou demandez un transfert depuis l'entrepôt. » | « Erreur : opération impossible » |
| Champ requis vide | « Indiquez le motif de la sortie. » | « Ce champ est invalide » |
| SKU déjà pris | « La référence IMP-000042 existe déjà. Laissez le champ vide pour qu'elle soit générée. » | « Violation de contrainte d'unicité » |
| Vente enregistrée | « Vente BS-2026-000128 enregistrée. » | « Succès ! » |
| Connexion refusée | « Adresse e-mail ou mot de passe incorrect. » | « Mot de passe incorrect pour ce compte » |
| Session expirée | « Votre session a expiré. Reconnectez-vous pour continuer. » | « Erreur 419 » |

Le message de connexion refusée ne précise pas lequel des deux champs est faux, pour ne pas révéler l'existence d'un compte.

## 3. Palette

Identité retenue : des neutres chauds légèrement grisés (papier, encre) et une seule couleur d'accent, un bleu pétrole foncé. Les couleurs d'état sont le vert, l'ocre, le rouge brique et l'ardoise. Aucune ne se confond avec l'accent. Pas de dégradé, pas de transparence décorative.

### Couleurs de base

| Rôle | Jeton CSS | Hex | Emploi |
| :-- | :-- | :-- | :-- |
| Fond de page | `--sf-bg` | `#F3F1EC` | Arrière-plan de l'application |
| Surface | `--sf-surface` | `#FBFAF7` | Cartes, tableaux, champs, fenêtres modales. C'est le blanc cassé de l'interface, le `#FFFFFF` n'est pas utilisé à l'écran |
| Surface creuse | `--sf-surface-sunken` | `#EAE7E0` | En-têtes de tableau, survol de ligne, piste des barres, badges neutres |
| Bordure | `--sf-border` | `#D6D1C7` | Séparateurs et contours décoratifs de cartes et de lignes |
| Bordure de contrôle | `--sf-border-strong` | `#857F73` | Contour des champs, cases à cocher, boutons contour neutres |
| Texte principal | `--sf-text` | `#1E2422` | Texte courant, titres, valeurs |
| Texte secondaire | `--sf-text-muted` | `#545B57` | Libellés secondaires, aides, texte indicatif des champs, légendes |
| Accent | `--sf-accent` | `#0B5C6E` | Bouton principal, liens, élément actif, focus, barres de graphique |
| Accent survol | `--sf-accent-hover` | `#084A59` | Survol et état enfoncé du bouton principal |
| Accent clair | `--sf-accent-soft` | `#DCEBEE` | Ligne sélectionnée, fond d'un onglet actif |
| Barre latérale | `--sf-sidebar-bg` | `#1C2B30` | Fond de la navigation |
| Barre latérale, élément actif | `--sf-sidebar-active` | `#2A4048` | Fond de l'entrée courante et du survol |
| Barre latérale, texte | `--sf-sidebar-text` | `#E4E8E6` | Libellés de navigation |
| Barre latérale, texte secondaire | `--sf-sidebar-muted` | `#A9B5B5` | Titres de groupe, icônes inactives |
| Barre latérale, repère | `--sf-sidebar-mark` | `#7FC6D6` | Filet de l'entrée active, focus sur fond sombre |

### Couleurs d'état

| État | Jeton | Texte et icône | Fond du badge ou du bandeau | Emploi |
| :-- | :-- | :-- | :-- | :-- |
| Succès | `--sf-success` | `#256B3C` | `#E1EEE3` | Stock normal, document validé ou reçu, confirmation |
| Alerte | `--sf-warning` | `#7A5200` | `#F8EBC8` | Stock sous seuil, réception partielle, attente de validation |
| Alerte, élément graphique | `--sf-warning-mark` | `#9A6700` | | Icône seule, filet de bandeau, barre de graphique en alerte |
| Danger | `--sf-danger` | `#A32A1F` | `#F7E2DE` | Rupture, annulation, erreur bloquante, action destructive |
| Information | `--sf-info` | `#3D5368` | `#E5EBF0` | Brouillon, comptage en cours, message neutre |
| Neutre | `--sf-neutral` | `#545B57` | `#EAE7E0` | Élément inactif ou archivé |

L'ocre de l'alerte et le rouge brique du danger restent distincts en vision des couleurs altérée parce qu'ils diffèrent aussi en luminosité, et parce que chaque badge porte une icône et un libellé propres.

### Contrastes vérifiés

Ratios calculés avec la formule de luminance relative de WCAG 2.1. Seuils : 4,5:1 pour le texte courant, 3:1 pour les composants d'interface et les éléments graphiques.

| Premier plan | Arrière-plan | Ratio | Seuil | Emploi |
| :-- | :-- | --: | --: | :-- |
| Texte `#1E2422` | Fond `#F3F1EC` | 13,98:1 | 4,5 | Texte sur la page |
| Texte `#1E2422` | Surface `#FBFAF7` | 15,12:1 | 4,5 | Texte dans les cartes et tableaux |
| Texte `#1E2422` | Surface creuse `#EAE7E0` | 12,78:1 | 4,5 | En-têtes de tableau, ligne survolée |
| Texte `#1E2422` | Accent clair `#DCEBEE` | 12,90:1 | 4,5 | Ligne sélectionnée |
| Texte secondaire `#545B57` | Fond `#F3F1EC` | 6,18:1 | 4,5 | Aides, fil d'Ariane |
| Texte secondaire `#545B57` | Surface `#FBFAF7` | 6,68:1 | 4,5 | Aides, texte indicatif des champs |
| Texte secondaire `#545B57` | Surface creuse `#EAE7E0` | 5,65:1 | 4,5 | Badge neutre, en-tête de colonne |
| Accent `#0B5C6E` | Fond `#F3F1EC` | 6,72:1 | 4,5 | Liens, contour de focus |
| Accent `#0B5C6E` | Surface `#FBFAF7` | 7,26:1 | 4,5 | Liens, bouton contour |
| Accent `#0B5C6E` | Surface creuse `#EAE7E0` | 6,14:1 | 3 | Barre de graphique sur sa piste |
| Accent `#0B5C6E` | Accent clair `#DCEBEE` | 6,19:1 | 4,5 | Lien dans une ligne sélectionnée |
| Surface `#FBFAF7` | Accent `#0B5C6E` | 7,26:1 | 4,5 | Libellé du bouton principal |
| Surface `#FBFAF7` | Accent survol `#084A59` | 9,43:1 | 4,5 | Bouton principal survolé |
| Surface `#FBFAF7` | Danger `#A32A1F` | 6,92:1 | 4,5 | Libellé du bouton d'annulation |
| Texte barre latérale `#E4E8E6` | Barre latérale `#1C2B30` | 11,81:1 | 4,5 | Entrées de navigation |
| Texte barre latérale `#E4E8E6` | Élément actif `#2A4048` | 8,82:1 | 4,5 | Entrée courante |
| Texte secondaire `#A9B5B5` | Barre latérale `#1C2B30` | 6,93:1 | 4,5 | Titres de groupe |
| Texte secondaire `#A9B5B5` | Élément actif `#2A4048` | 5,17:1 | 4,5 | Icône d'une entrée survolée |
| Repère `#7FC6D6` | Barre latérale `#1C2B30` | 7,62:1 | 3 | Filet actif, focus |
| Repère `#7FC6D6` | Élément actif `#2A4048` | 5,69:1 | 3 | Focus sur l'entrée courante |
| Succès `#256B3C` | Fond succès `#E1EEE3` | 5,40:1 | 4,5 | Badge « En stock », « Reçue » |
| Succès `#256B3C` | Surface `#FBFAF7` | 6,20:1 | 4,5 | Écart positif dans un tableau |
| Succès `#256B3C` | Surface creuse `#EAE7E0` | 5,24:1 | 4,5 | Écart positif sur une ligne survolée |
| Alerte `#7A5200` | Fond alerte `#F8EBC8` | 5,84:1 | 4,5 | Badge « Sous seuil » |
| Alerte `#7A5200` | Surface `#FBFAF7` | 6,63:1 | 4,5 | Texte d'alerte dans un tableau |
| Alerte `#7A5200` | Surface creuse `#EAE7E0` | 5,60:1 | 4,5 | Même texte sur une ligne survolée |
| Alerte graphique `#9A6700` | Surface `#FBFAF7` | 4,66:1 | 3 | Icône seule, filet de bandeau |
| Alerte graphique `#9A6700` | Fond alerte `#F8EBC8` | 4,10:1 | 3 | Icône dans un bandeau d'alerte |
| Alerte graphique `#9A6700` | Surface creuse `#EAE7E0` | 3,94:1 | 3 | Barre de graphique sur sa piste |
| Danger `#A32A1F` | Fond danger `#F7E2DE` | 5,81:1 | 4,5 | Badge « Rupture », bandeau d'erreur |
| Danger `#A32A1F` | Surface `#FBFAF7` | 6,92:1 | 4,5 | Message d'erreur sous un champ |
| Danger `#A32A1F` | Fond `#F3F1EC` | 6,40:1 | 4,5 | Message d'erreur sur la page |
| Danger `#A32A1F` | Surface creuse `#EAE7E0` | 5,85:1 | 4,5 | Écart négatif sur une ligne survolée |
| Information `#3D5368` | Fond information `#E5EBF0` | 6,63:1 | 4,5 | Badge « Brouillon » |
| Information `#3D5368` | Surface `#FBFAF7` | 7,63:1 | 4,5 | Texte d'information |
| Bordure de contrôle `#857F73` | Surface `#FBFAF7` | 3,81:1 | 3 | Contour des champs |
| Bordure de contrôle `#857F73` | Fond `#F3F1EC` | 3,52:1 | 3 | Contour d'un champ posé sur la page |
| Bordure de contrôle `#857F73` | Surface creuse `#EAE7E0` | 3,22:1 | 3 | Contour d'un champ dans une barre de filtres |
| Texte `#1E2422` | Fond alerte `#F8EBC8` | 13,31:1 | 4,5 | Corps d'un bandeau d'alerte |
| Texte `#1E2422` | Fond danger `#F7E2DE` | 12,69:1 | 4,5 | Corps d'un bandeau d'erreur |
| Texte `#1E2422` | Fond succès `#E1EEE3` | 13,18:1 | 4,5 | Corps d'un bandeau de succès |
| Texte `#1E2422` | Fond information `#E5EBF0` | 13,13:1 | 4,5 | Corps d'un bandeau d'information |

Couples à ne pas utiliser :

- la bordure décorative `#D6D1C7` comme seul contour d'un champ ou d'un bouton (1,46:1 sur la surface) ;
- l'accent `#0B5C6E` sur la barre latérale sombre (1,93:1) : sur ce fond, le repère est `#7FC6D6` ;
- un texte de couleur d'état sur le fond d'un autre état.

Le texte d'un contrôle désactivé échappe au seuil de contraste. Il prend `#545B57` à 65 % d'opacité, et le contrôle porte `disabled` ou `aria-disabled="true"`.

### Correspondance avec Bootstrap

Les variables Sass sont redéfinies dans `resources/scss/_variables.scss`, avant l'import de Bootstrap. Les jetons `--sf-*` sont déclarés sur `:root` dans `resources/scss/_tokens.scss`.

| Variable Sass | Valeur | Variable CSS générée |
| :-- | :-- | :-- |
| `$primary` | `#0B5C6E` | `--bs-primary` |
| `$secondary` | `#545B57` | `--bs-secondary` |
| `$success` | `#256B3C` | `--bs-success` |
| `$warning` | `#9A6700` | `--bs-warning` |
| `$danger` | `#A32A1F` | `--bs-danger` |
| `$info` | `#3D5368` | `--bs-info` |
| `$light` | `#EAE7E0` | `--bs-light` |
| `$dark` | `#1C2B30` | `--bs-dark` |
| `$body-bg` | `#F3F1EC` | `--bs-body-bg` |
| `$body-color` | `#1E2422` | `--bs-body-color` |
| `$body-secondary-color` | `#545B57` | `--bs-secondary-color` |
| `$body-tertiary-bg` | `#EAE7E0` | `--bs-tertiary-bg` |
| `$border-color` | `#D6D1C7` | `--bs-border-color` |
| `$link-color` | `#0B5C6E` | `--bs-link-color` |
| `$link-hover-color` | `#084A59` | `--bs-link-hover-color` |
| `$card-bg`, `$modal-content-bg`, `$dropdown-bg`, `$input-bg`, `$table-bg` | `#FBFAF7` | |
| `$input-border-color` | `#857F73` | |
| `$input-placeholder-color` | `#545B57` | |
| `$table-hover-bg`, `$table-striped-bg` | `#EAE7E0` | |
| `$success-bg-subtle`, `$warning-bg-subtle`, `$danger-bg-subtle`, `$info-bg-subtle` | `#E1EEE3`, `#F8EBC8`, `#F7E2DE`, `#E5EBF0` | `--bs-success-bg-subtle`, etc. |
| `$success-text-emphasis`, `$warning-text-emphasis`, `$danger-text-emphasis`, `$info-text-emphasis` | `#256B3C`, `#7A5200`, `#A32A1F`, `#3D5368` | `--bs-success-text-emphasis`, etc. |
| `$min-contrast-ratio` | `4.5` | |
| `$enable-shadows`, `$enable-gradients` | `false` | |
| `$enable-rounded`, `$enable-reduced-motion` | `true` | |

Le bouton `.btn-warning` n'est pas employé : une alerte n'est jamais une action. Le mode sombre de Bootstrap n'est pas activé dans cette version.

## 4. Typographie

### Familles

| Emploi | Famille | Licence | Fichier |
| :-- | :-- | :-- | :-- |
| Texte, tableaux, formulaires, chiffres | Public Sans, variable (graisses 400 à 700) | SIL OFL 1.1 | `resources/fonts/public-sans-variable.woff2` |
| Titres de page et de section, logotype | Bricolage Grotesque, variable (axe de graisse seul, 600 et 700 utilisées) | SIL OFL 1.1 | `resources/fonts/bricolage-grotesque-variable.woff2` |
| Références et codes (SKU, code-barres, numéros de bon) | Pile à chasse fixe du système | | Aucun fichier |

Les deux fichiers woff2 sont copiés dans le dépôt avec leur fichier de licence et servis par l'application. Ils sont réduits aux jeux latin et latin étendu. Les italiques ne sont pas chargées : l'interface n'en emploie pas.

```scss
$font-family-sans-serif: "Public Sans", system-ui, -apple-system, "Segoe UI", Roboto,
  "Helvetica Neue", "Noto Sans", Arial, sans-serif;
$headings-font-family: "Bricolage Grotesque", "Public Sans", system-ui, -apple-system,
  "Segoe UI", Roboto, Arial, sans-serif;
$font-family-monospace: ui-monospace, "Cascadia Mono", "Segoe UI Mono", "Roboto Mono",
  Menlo, Consolas, monospace;
```

Déclarations `@font-face` avec `font-display: swap`. Seule Public Sans est préchargée par `<link rel="preload" as="font" type="font/woff2" crossorigin>`.

### Échelle

Taille racine du navigateur conservée à 16 px. Le corps de l'interface est à 15 px pour gagner en densité sans descendre sous le confort de lecture.

| Style | Famille | Taille et interligne | Graisse | Emploi |
| :-- | :-- | :-- | :-- | :-- |
| Titre de page (`h1`) | Bricolage Grotesque | 24 px / 32 px | 600 | Un seul par page |
| Titre de section (`h2`) | Bricolage Grotesque | 18 px / 26 px | 600 | Blocs d'une page, titre de fenêtre modale |
| Titre de carte (`h3`) | Public Sans | 15 px / 22 px | 600 | Cartes, groupes de champs |
| Valeur de KPI | Public Sans | 28 px / 32 px | 600 | Cartes du tableau de bord |
| Total de panier | Public Sans | 24 px / 32 px | 700 | Point de vente |
| Corps | Public Sans | 15 px / 22 px | 400 | Texte courant, champs |
| Tableau | Public Sans | 14 px / 20 px | 400, valeurs clés en 600 | Cellules |
| Libellé de champ | Public Sans | 14 px / 20 px | 600 | Au-dessus des champs |
| Aide et erreur | Public Sans | 13 px / 18 px | 400 | Sous les champs, légendes |
| Surtitre | Public Sans | 12 px / 16 px, capitales, interlettrage 0,04 em | 600 | Étiquette de KPI, en-tête de colonne, groupe de navigation |
| Code | Pile à chasse fixe | 13 px / 20 px | 400 | SKU, code-barres, référence de bon |

Aucun texte sous 12 px. Sur tablette et en dessous, les champs de saisie passent à 16 px pour éviter le zoom automatique du navigateur à la prise de focus.

Les longueurs de ligne de texte continu sont plafonnées à 70 caractères (`max-width: 70ch`).

### Chiffres tabulaires

Toute colonne de quantités ou de montants, toute valeur de KPI et tout champ de quantité portent la classe `.tabular` :

```css
.tabular {
  font-variant-numeric: tabular-nums lining-nums;
  font-feature-settings: "tnum" 1, "lnum" 1;
}
```

La classe est appliquée d'office aux cellules `td.num` et `th.num` de `<x-data-table>`. Les chiffres restent dans Public Sans, y compris dans les titres, pour garantir des chasses égales.

## 5. Espacements, grille, rayons, bordures, élévation

### Espacements

Pas de base de 4 px. L'échelle de Bootstrap est conservée et complétée de deux pas.

| Clé `$spacers` | Valeur | Emploi type |
| :-- | :-- | :-- |
| `1` | 4 px | Entre une icône et son libellé |
| `2` | 8 px | Entre un champ et son aide, entre deux badges |
| `3` | 16 px | Marge intérieure d'une carte, entre deux champs |
| `4` | 24 px | Entre deux blocs d'une page, gouttière de grille sur ordinateur |
| `5` | 48 px | Autour d'un état vide |
| `6` | 32 px | Entre l'en-tête de page et le contenu (ajout) |
| `7` | 12 px | Marge horizontale d'une cellule de tableau (ajout) |

Hauteurs de contrôle : 36 px sur ordinateur, 44 px dès que la largeur passe sous 1200 px ou que le pointeur est grossier (`@media (pointer: coarse)`). Hauteur de ligne de tableau : 40 px sur ordinateur, 48 px sur tablette.

### Grille

Grille Bootstrap à 12 colonnes. Gouttière de 24 px à partir de 992 px, 16 px en dessous. La zone de contenu a une marge intérieure de 24 px (16 px sous 768 px) et une largeur maximale de 1440 px. Un formulaire simple est plafonné à 720 px de large, aligné à gauche. Les mises en page sont des colonnes régulières : pas de mosaïque de cartes de tailles inégales.

### Rayons et bordures

| Élément | Rayon |
| :-- | :-- |
| Badges, cases à cocher | 2 px |
| Champs, boutons, cartes, bandeaux | 4 px (`$border-radius`) |
| Fenêtres modales, toasts, menus | 6 px (`$border-radius-lg`) |

Pas de forme en pilule, y compris pour les badges. Les bordures font 1 px. Une carte se détache du fond par sa bordure `#D6D1C7` et sa surface plus claire. Le filet de 3 px est réservé au bord gauche des bandeaux et à l'entrée active de la navigation.

### Élévation

Les cartes, tableaux et barres n'ont aucune ombre. Une seule ombre existe, pour ce qui flotte au-dessus de la page (menu déroulant, résultats de recherche, toast, fenêtre modale, panneau offcanvas) :

```css
--sf-shadow-overlay: 0 2px 8px rgba(30, 36, 34, 0.12);
```

Le voile derrière une fenêtre modale est `rgba(28, 43, 48, 0.5)`, sans flou.

### Mouvement

Les transitions se limitent aux changements d'état qui aident à comprendre : ouverture d'un panneau, apparition d'un toast, changement de couleur au survol. Durée de 120 à 160 ms, courbe `ease-out`. Aucune animation d'entrée de page, aucun compteur animé, aucun effet de parallaxe.

## 6. Structure de page

Gabarit unique `layouts/app.blade.php` pour toutes les pages authentifiées, `layouts/guest.blade.php` pour la connexion et les pages d'erreur hors session, `layouts/print.blade.php` pour les documents imprimables.

### Barre latérale

- Fond `#1C2B30`, largeur de 248 px, fixe sur toute la hauteur, défilement interne si le menu dépasse.
- En tête : le logotype. En pied : le nom de l'emplacement d'affectation de l'utilisateur quand il en a un.
- Entrées regroupées sous des surtitres (Catalogue, Stock, Achats, Ventes, Pilotage, Administration). Une entrée : icône Bootstrap Icons de 18 px, libellé, compteur facultatif à droite.
- Entrée courante : fond `#2A4048`, filet gauche de 3 px en `#7FC6D6`, `aria-current="page"`.
- Une entrée à laquelle le rôle n'a pas accès n'est pas rendue. Elle n'est pas grisée.
- Balise `<nav aria-label="Navigation principale">`. L'arborescence et la visibilité par rôle figurent à la fin de `wireframes.md`.

### Barre supérieure

Hauteur de 56 px, fond de surface, bordure basse de 1 px. De gauche à droite :

1. le bouton de menu (visible sous 1200 px), qui déplie la barre latérale ou ouvre le panneau offcanvas ;
2. la recherche globale de produit (`<x-product-search variant="global">`), large de 480 px au plus, avec le raccourci clavier `/` pour y placer le curseur ;
3. la cloche d'alertes de stock, avec le nombre d'alertes non lues ;
4. le menu utilisateur : nom, rôle, emplacement d'affectation, « Changer mon mot de passe », « Se déconnecter ».

La déconnexion est un formulaire `POST` avec jeton CSRF, présenté comme une entrée de menu.

### Fil d'Ariane

Placé au-dessus du titre, en 13 px, dans `<nav aria-label="Fil d'Ariane">`. Il commence par la rubrique, pas par « Accueil ». Il est absent des pages de premier niveau (tableau de bord, point de vente). Sous 768 px, il se réduit à un lien de retour vers le niveau parent.

### En-tête de page

Composant `<x-page-header>`. Il contient le titre (`h1`), une ligne de contexte facultative (référence, badge de statut, date), et à droite les actions. Une seule action principale en bouton plein. Les actions secondaires sont en bouton contour, deux au plus, le reste dans un menu « Plus d'actions ».

### Zone de contenu

Balise `<main id="contenu" tabindex="-1">`. Ordre constant : bandeau d'erreur ou d'information éventuel, barre de filtres, contenu, pagination. Les messages flash de session s'affichent en toast pour un succès, en bandeau pour une erreur.

### Comportement par point de rupture

| Largeur | Barre latérale | Barre supérieure | Contenu |
| :-- | :-- | :-- | :-- |
| 1200 px et plus (ordinateur) | Dépliée, 248 px | Recherche toujours visible | Grille à 12 colonnes, cartes KPI sur une ligne de 6 ou deux lignes de 3 |
| 768 à 1199 px (tablette) | Repliée en rail de 64 px, icônes seules avec infobulle et `aria-label`. Le bouton de menu la déplie par-dessus le contenu | Recherche visible, nom d'utilisateur réduit aux initiales | Contrôles de 44 px, cartes KPI par 3 ou par 2, colonnes de priorité 3 masquées dans les tableaux |
| Moins de 768 px | Masquée, ouverte en panneau offcanvas de 280 px par le bouton de menu | Bouton de menu, « SF », icône de recherche qui ouvre le champ en pleine largeur, cloche, initiales | Une colonne, tableaux en cartes, action principale en pleine largeur sous le titre |

À 360 px, aucune page ne défile horizontalement. Un tableau qui ne peut pas passer en cartes (grille de comptage, revue des écarts) est placé dans un conteneur à défilement horizontal propre, avec sa première colonne figée.

L'état replié ou déplié de la barre latérale est mémorisé dans `localStorage`, par appareil.

## 7. Composants

Les composants Blade sont anonymes, rangés dans `resources/views/components`. Les noms de propriétés sont indicatifs et seront arrêtés à l'implémentation.

### En-tête de page, `<x-page-header>`

- Usage : haut de chaque page.
- Propriétés : `title`, `subtitle`, emplacements nommés `breadcrumb`, `meta` (badges, référence), `actions`.
- Variantes : simple (titre seul), document (référence en police de code, badge de statut, date et auteur).
- Règle : un seul bouton `.btn-primary` dans `actions`.

### Carte KPI, `<x-kpi-card>`

- Usage : indicateurs du tableau de bord.
- Contenu : surtitre, valeur en chiffres tabulaires, unité (« F CFA », « références »), ligne de contexte facultative (« 23 ventes »), lien vers la liste filtrée correspondante.
- Variantes : `neutral` (défaut), `warning` (sous seuil), `danger` (ruptures). La variante ajoute un filet gauche de 3 px et une icône, la valeur reste en couleur de texte.
- États : chargement (squelette de la valeur), valeur nulle (« 0 » affiché, jamais de carte vide), erreur de calcul (tiret court et lien « Réessayer »).
- La carte entière est un lien quand `href` est fourni. La zone cliquable couvre toute la carte.
- Pas de flèche de tendance ni de pourcentage d'évolution dans cette version : le modèle ne conserve pas de valeur de référence.

### Tableau de données, `<x-data-table>`

- Usage : toutes les listes. Règles détaillées en section 9.
- Propriétés : `caption` (obligatoire, visible ou réservée aux lecteurs d'écran), `sortable`, `rowLink`, `responsive` (`cards` ou `scroll`).
- États : chargement, vide (rend `<x-empty-state>` dans le corps), erreur de chargement.

### Barre de filtres, `<x-filter-bar>`

- Usage : au-dessus d'un tableau. C'est un formulaire `GET` : les filtres sont dans l'URL, donc partageables et conservés au retour arrière.
- Contenu : un champ de recherche à gauche, quatre filtres au plus, « Réinitialiser » en lien.
- Les filtres actifs sont rappelés sous la barre sous forme d'étiquettes supprimables, suivies du nombre de résultats.
- Sous 992 px : la recherche reste visible, les filtres se replient derrière un bouton « Filtres (2) » qui ouvre un panneau.
- Avec JavaScript, la recherche et les listes déroulantes soumettent en Fetch après 300 ms sans frappe et mettent l'URL à jour. Sans JavaScript, un bouton « Filtrer » soumet le formulaire.

### Badge de stock, `<x-stock-badge>`

Il traduit une quantité et un seuil en un état. Il porte toujours une icône et un libellé.

| État | Condition | Libellé | Icône | Couleurs (texte sur fond) |
| :-- | :-- | :-- | :-- | :-- |
| Normal | `quantity > seuil` | En stock | `bi-check-circle` | `#256B3C` sur `#E1EEE3` |
| Sous seuil | `0 < quantity <= seuil` | Sous seuil | `bi-exclamation-triangle` | `#7A5200` sur `#F8EBC8` |
| Rupture | `quantity = 0` | Rupture | `bi-x-octagon` | `#A32A1F` sur `#F7E2DE` |

- Propriétés : `quantity`, `threshold`, `showQuantity` (affiche « Sous seuil · 3 »), `size` (`sm` dans les tableaux, `md` sur la fiche produit).
- Le seuil passé au composant est `products.alert_threshold` quand la quantité est le stock total, et `stock_levels.reorder_point` quand elle est celle d'un emplacement. Sans seuil d'emplacement, le badge d'emplacement ne distingue que « En stock » et « Rupture ».
- Un produit inactif reçoit en plus le badge neutre « Inactif ».

### Badge de statut, `<x-status-badge>`

Propriétés : `type` (document concerné) et `status` (valeur de l'enum). Le libellé vient d'un fichier de traduction `lang/fr/statuses.php`. Chaque badge a une icône.

| Document | Valeur | Libellé | Couleur | Icône |
| :-- | :-- | :-- | :-- | :-- |
| Commande fournisseur | `draft` | Brouillon | Information | `bi-pencil` |
| Commande fournisseur | `ordered` | Commandée | Accent (`#0B5C6E` sur `#DCEBEE`) | `bi-send` |
| Commande fournisseur | `partially_received` | Reçue en partie | Alerte | `bi-hourglass-split` |
| Commande fournisseur | `received` | Reçue | Succès | `bi-check-circle` |
| Commande fournisseur | `cancelled` | Annulée | Danger | `bi-x-circle` |
| Vente | `completed` | Validée | Succès | `bi-check-circle` |
| Vente | `cancelled` | Annulée | Danger | `bi-x-circle` |
| Transfert | `completed` | Effectué | Succès | `bi-check-circle` |
| Transfert | `cancelled` | Annulé | Danger | `bi-x-circle` |
| Inventaire | `in_progress` | Comptage en cours | Information | `bi-clipboard-data` |
| Inventaire | `pending_validation` | À valider | Alerte | `bi-hourglass-split` |
| Inventaire | `validated` | Validé | Succès | `bi-check-circle` |
| Inventaire | `cancelled` | Annulé | Danger | `bi-x-circle` |
| Produit, fournisseur, utilisateur, emplacement | `is_active = true` | Actif | Succès | `bi-check-circle` |
| Produit, fournisseur, utilisateur, emplacement | `is_active = false` | Inactif | Neutre | `bi-slash-circle` |

Le bon de réception n'a pas de colonne de statut dans le modèle : il n'a pas de badge.

Le même composant affiche le type d'un mouvement de stock, avec une flèche de sens à la place de l'icône (`bi-arrow-down-left` pour une entrée, `bi-arrow-up-right` pour une sortie) et un fond neutre.

| `stock_movements.type` | Libellé |
| :-- | :-- |
| `purchase` | Réception |
| `sale` | Vente |
| `sale_cancellation` | Annulation de vente |
| `internal_use` | Sortie interne |
| `transfer` | Transfert |
| `manual_adjustment` | Ajustement manuel |
| `inventory_adjustment` | Écart d'inventaire |

Libellés des rôles : `admin` « Administrateur », `stock_manager` « Gestionnaire de stock », `sales_agent` « Vendeur ».

Libellés des actions du journal d'audit : `created` « Création », `updated` « Modification », `deleted` « Suppression », `restored` « Restauration », `login` « Connexion », `logout` « Déconnexion », `login_failed` « Connexion refusée », `validated` « Validation », `cancelled` « Annulation », `exported` « Export ».

### Champ de saisie, `<x-form.input>`

- Propriétés : `name`, `label`, `type`, `value`, `required`, `help`, `autocomplete`, `inputmode`, `suffix`, `prefix`.
- Variantes : texte, e-mail, téléphone, mot de passe (avec bouton d'affichage), quantité, montant (suffixe « F CFA »), date, zone de texte (`<x-form.textarea>` suit les mêmes règles), fichier image.
- États : repos (bordure `#857F73`), focus (contour d'accent), erreur (bordure `#A32A1F`, icône et message sous le champ), désactivé, lecture seule (fond `#EAE7E0`, sans bordure forte).
- Le composant relie lui-même `label`, aide et erreur par `for`, `id` et `aria-describedby`. Il lit l'erreur dans le sac `$errors` et réaffiche `old()`.

### Liste déroulante, `<x-form.select>`

- Propriétés : `name`, `label`, `options`, `selected`, `placeholder`, `required`, `help`.
- Élément `<select>` natif, sans surcouche JavaScript : il ouvre le sélecteur du système sur tablette.
- Au-delà d'une trentaine d'options (choix d'un produit), on emploie `<x-product-search>` et non une liste.
- Mêmes états que le champ de saisie.

### Recherche de produit, `<x-product-search>`

- Usage : trouver un produit par nom, SKU ou code-barres, puis l'ouvrir ou l'ajouter à un document.
- Variantes : `global` (barre supérieure, ouvre la fiche produit), `picker` (formulaires de sortie, de commande, de réception, de transfert : ajoute une ligne), `pos` (point de vente : grand champ, ajoute au panier, affiche le disponible de l'emplacement du vendeur).
- Comportement : requête Fetch 250 ms après la dernière frappe, à partir de 2 caractères, annulation de la requête précédente par `AbortController`. Huit résultats au plus, puis « Voir tous les résultats ».
- Chaque résultat affiche le libellé, le SKU en police de code, le prix de vente, la quantité disponible et le badge de stock. En variante `pos`, un produit en rupture à l'emplacement reste visible mais n'est pas ajoutable, avec la mention du stock de l'entrepôt.
- Un code-barres saisi au lecteur (13 chiffres suivis d'Entrée) sélectionne directement le produit correspondant.
- Clavier : flèches haut et bas pour parcourir, Entrée pour choisir, Échap pour fermer. Modèle ARIA `combobox` avec `listbox`, `aria-expanded`, `aria-activedescendant`.
- États : repos, saisie trop courte (« Saisissez au moins 2 caractères »), recherche en cours, résultats, aucun résultat (« Aucun produit ne correspond à « toner 59 » »), erreur réseau (« Recherche indisponible. Vérifiez la connexion puis réessayez. »).

### Fenêtre de confirmation, `<x-confirm-modal>`

- Usage : toute action irréversible ou qui écrit des mouvements : annuler une vente, valider un inventaire, annuler un transfert, désactiver un compte.
- Contenu : titre qui nomme l'action et l'objet (« Annuler la vente BS-2026-000128 »), conséquence en une phrase (« Les 3 articles seront remis en stock à la Boutique du Plateau. »), champ de motif quand la règle métier l'exige, deux boutons.
- Variantes : `default` (bouton de confirmation d'accent), `danger` (bouton rouge brique).
- Le bouton de confirmation reprend le verbe du titre (« Annuler la vente »). Le bouton de sortie est « Ne pas annuler » ou « Fermer », pas « Annuler », pour éviter la confusion quand l'action elle-même est une annulation.
- Focus placé sur le champ de motif s'il existe, sinon sur le bouton de sortie. Focus piégé dans la fenêtre, rendu à l'élément d'origine à la fermeture. Échap ferme.
- Pas de confirmation pour une action sans conséquence ou facilement réversible.

### Bandeau, `<x-alert>`

- Usage : message attaché à la page, qui reste affiché tant que le problème existe. Variantes `danger`, `warning`, `info`, `success`.
- Forme : fond clair de l'état, filet gauche de 3 px, icône, titre court en 600, corps en couleur de texte, action facultative en lien.
- `danger` porte `role="alert"`, les autres `role="status"`. Un bandeau d'erreur de formulaire reçoit le focus à l'affichage et liste les champs en erreur sous forme de liens.
- Fermeture possible pour `info` et `success` seulement.

### Toast, `<x-toast>`

- Usage : confirmation brève d'une action réussie. Jamais pour une erreur bloquante.
- Position : en haut à droite sous la barre supérieure, en haut en pleine largeur sous 768 px. Trois toasts empilés au plus.
- Durée : 5 secondes, suspendue au survol et au focus. Bouton de fermeture toujours présent.
- Peut porter un lien d'action (« Imprimer le bon », « Voir la vente »). Dans ce cas la durée passe à 8 secondes.
- Conteneur unique en `aria-live="polite"` avec `role="status"`.

### État vide, `<x-empty-state>`

- Usage : liste sans contenu ou sans résultat.
- Contenu : icône de 32 px en `#545B57`, titre (« Aucun mouvement sur cette période »), explication en une phrase, action.
- Variantes : `first-use` (aucune donnée encore : l'action crée le premier élément, si le rôle le permet), `no-results` (les filtres n'ont rien trouvé : l'action réinitialise les filtres), `all-clear` (« Aucun produit sous seuil », sans action).
- Pas d'illustration.

### Pagination, `<x-pagination>`

- Contenu : « 1 à 25 sur 812 », choix du nombre de lignes (25, 50, 100), précédent, numéros de page avec ellipse, suivant.
- Balise `<nav aria-label="Pagination">`, page courante en `aria-current="page"`.
- Sous 768 px : « Précédent », « Page 3 sur 33 », « Suivant », boutons de 44 px.
- Vue de pagination Laravel personnalisée, les paramètres de filtre sont conservés dans les liens.

### Boutons

| Variante | Classe | Emploi |
| :-- | :-- | :-- |
| Principal | `.btn-primary` | L'action principale de la page, une seule fois |
| Secondaire | `.btn-outline-secondary` | Actions voisines, « Exporter », « Imprimer » |
| Danger | `.btn-danger` dans une fenêtre de confirmation, `.btn-outline-danger` dans une page | Annulation de document, désactivation |
| Lien | `.btn-link` | « Réinitialiser », « Fermer » |
| Icône seule | `.btn-icon` | Actions de ligne, avec `aria-label` et infobulle, 36 px sur ordinateur et 44 px sur tablette |

Un bouton en cours d'envoi est désactivé, garde sa largeur et affiche « Enregistrement… » avec un indicateur. Cela évite la double soumission d'une vente.

## 8. Formulaires

### Mise en page

- Libellé au-dessus du champ, aligné à gauche. Jamais de libellé flottant ni de texte indicatif à la place du libellé.
- Une colonne par défaut. Deux colonnes seulement pour des champs courts qui vont ensemble (prix d'achat et prix de vente, date de début et date de fin), et une seule sous 768 px.
- Les champs sont regroupés par `<fieldset>` avec `<legend>` quand le formulaire dépasse six champs.
- Largeur du champ proportionnée au contenu attendu : une quantité fait 120 px, pas toute la ligne.
- Actions en bas à gauche : bouton principal, puis « Annuler » en lien qui revient à la page précédente. Sur tablette, la barre d'actions des formulaires longs reste collée en bas de l'écran.

### Champs obligatoires et facultatifs

Un champ obligatoire porte un astérisque après son libellé, l'attribut `required` et `aria-required="true"`. La mention « Les champs marqués d'un astérisque sont obligatoires. » figure en tête du formulaire. Un formulaire dont tous les champs sont obligatoires (connexion) n'affiche ni astérisque ni mention.

### Aide et erreurs

- L'aide est sous le champ, en 13 px, et reste visible pendant la saisie.
- L'erreur remplace l'aide sous le champ, en `#A32A1F`, précédée de l'icône `bi-exclamation-circle`. Le champ reçoit `aria-invalid="true"`.
- La validation fait foi côté serveur (Form Requests). Après un refus, la page réaffiche les valeurs saisies, un bandeau récapitule les erreurs et le focus va sur ce bandeau.
- La validation côté navigateur se limite aux contrôles natifs (`required`, `min`, `maxlength`, `pattern`). Elle s'applique à la sortie du champ, pas à chaque frappe.
- Un message d'erreur dit quoi faire : « Indiquez une quantité entre 1 et 14. »

### Attributs `autocomplete`

| Champ | `type` | `autocomplete` | `inputmode` |
| :-- | :-- | :-- | :-- |
| E-mail de connexion | `email` | `username` | `email` |
| Mot de passe de connexion | `password` | `current-password` | |
| Mot de passe actuel (changement) | `password` | `current-password` | |
| Nouveau mot de passe | `password` | `new-password` | |
| Nom d'un utilisateur créé par l'administrateur | `text` | `off` | |
| E-mail d'un utilisateur ou d'un fournisseur | `email` | `off` | `email` |
| Téléphone d'un fournisseur | `tel` | `off` | `tel` |
| Recherche | `search` | `off` | `search` |
| Quantité | `text` | `off` | `numeric` |
| Montant | `text` | `off` | `numeric` |

Les fiches créées par un employé concernent des tiers : le remplissage automatique du navigateur y est coupé pour qu'il n'y propose pas les coordonnées de la personne connectée.

### Mot de passe

Un seul champ, sans champ de confirmation. Un bouton dans le champ bascule l'affichage : icône `bi-eye` ou `bi-eye-slash`, `aria-label` « Afficher le mot de passe » ou « Masquer le mot de passe », `aria-pressed` tenu à jour. Le collage n'est jamais bloqué. La règle de composition est affichée en aide avant la saisie. Le changement de mot de passe demande le mot de passe actuel puis le nouveau.

### Quantités

- `type="text"` avec `inputmode="numeric"` et `pattern="[0-9]*"` : la tablette ouvre le pavé numérique, et la molette de la souris ne modifie pas la valeur comme le ferait `type="number"`.
- Entiers uniquement. Tout caractère autre qu'un chiffre est retiré à la saisie.
- Le champ est aligné à droite, en chiffres tabulaires, large de 96 à 120 px.
- Sur tablette, le champ est encadré de deux boutons de 44 px, moins et plus. Le bouton moins se désactive à la valeur minimale.
- Quand un plafond est connu (disponible, reste à recevoir), il est écrit à côté du champ : « sur 14 disponibles ».
- Le contenu est sélectionné à la prise de focus pour être remplacé d'une frappe.

### Montants en F CFA

- Saisie en entier, sans décimale. Le champ accepte les chiffres et les espaces, et reformate en milliers à la sortie du champ : `1200000` devient `1 200 000`.
- Suffixe fixe « F CFA » dans un groupe de saisie (`.input-group-text`), hors du champ.
- La valeur envoyée au serveur est l'entier sans séparateur. Le serveur valide un entier positif ou nul.
- Aligné à droite, en chiffres tabulaires.

### Dates

`<input type="date">` natif, qui affiche le format de la langue du navigateur et ouvre le sélecteur du système sur tablette. Les filtres de période proposent deux champs (« Du », « Au ») et des raccourcis : Aujourd'hui, 7 derniers jours, Ce mois, Mois dernier.

### Motif et justification

Les sorties internes, ajustements manuels, annulations et écarts d'inventaire exigent un motif. Le champ est une zone de texte de trois lignes avec compteur (500 caractères au plus). Pour une sortie interne, une liste de motifs courants (usage interne, casse, don, échantillon) préremplit le texte, qui reste modifiable.

### Image de produit

Champ fichier natif avec aperçu. Aide : « JPEG, PNG ou WebP, 2 Mo au plus. » Le refus d'un fichier trop lourd est signalé avant l'envoi quand JavaScript est disponible.

## 9. Tableaux de données

### Structure

- Balises `<table>`, `<caption>`, `<thead>`, `<th scope="col">`. La première cellule signifiante de chaque ligne (libellé du produit, référence du bon) est un `<th scope="row">`.
- En-tête sur fond `#EAE7E0`, en surtitre de 12 px. L'en-tête reste collé en haut pendant le défilement de la page.
- Lignes séparées par un filet de 1 px, sans zébrure. Survol en `#EAE7E0`.
- Colonnes numériques (quantités, montants, écarts) alignées à droite avec leur en-tête, en chiffres tabulaires. L'unité « F CFA » est dans l'en-tête de colonne et non répétée dans chaque cellule, sauf sur la ligne de total.
- Colonnes de texte alignées à gauche. Dates au format `JJ/MM/AAAA`, sur une largeur fixe.
- Les références (SKU, numéros de bon) sont en police de code et ne se coupent pas.
- Un libellé trop long est tronqué par une ellipse sur une ligne, avec le texte complet dans l'attribut `title` et sur la fiche.
- Une quantité nulle s'écrit `0`. Une valeur absente s'écrit par un tiret court `-` accompagné d'un texte pour lecteur d'écran (« non renseigné »).
- Un écart s'écrit avec son signe : `+1`, `-2`, `0`. Le signe porte l'information, la couleur la double.

### Tri

- Un en-tête triable est un bouton dans le `<th>`, avec une icône de sens. Le `<th>` porte `aria-sort`.
- Le tri est fait par le serveur et inscrit dans l'URL (`?sort=name&dir=asc`). Un clic alterne croissant et décroissant.
- Chaque liste a un tri par défaut annoncé : historiques par date décroissante, catalogue par libellé.

### Filtres et recherche

Par `<x-filter-bar>`. La recherche d'une liste filtre cette liste. La recherche globale de la barre supérieure ouvre une fiche produit. Les deux ne se remplacent pas.

### Pagination

25 lignes par défaut, côté serveur. Pas de défilement infini : un contrôleur ou un comptable doit pouvoir citer une page et y revenir.

### Ligne cliquable et actions de ligne

- Quand une ligne mène à une fiche, le lien est posé sur la cellule titre et étendu à toute la ligne par un pseudo-élément (`.stretched-link`). La ligne reste une ligne de tableau pour les lecteurs d'écran, avec un seul lien principal.
- Les actions de ligne sont à droite, dans une dernière colonne sans en-tête visible (en-tête « Actions » pour lecteur d'écran). Deux boutons icône au plus, le reste dans un menu `bi-three-dots-vertical`.
- Les boutons d'action sont placés au-dessus du lien étendu (`position: relative; z-index: 2`) pour rester cliquables.
- Aucune action destructive en un clic dans une ligne : elle ouvre une fenêtre de confirmation.
- Pas de case à cocher ni d'action groupée dans cette version.

### Tablette et petit écran

Chaque colonne reçoit une priorité.

| Priorité | Visible à partir de | Colonnes types |
| :-- | :-- | :-- |
| 1 | Toujours | Libellé ou référence, quantité ou montant, badge d'état |
| 2 | 768 px | Date, emplacement, auteur |
| 3 | 1200 px | SKU en colonne propre, catégorie, fournisseur, prix d'achat |

- Sur tablette, les colonnes de priorité 3 sont masquées. Leur contenu reste accessible sur la fiche. Le SKU passe sous le libellé, dans la même cellule.
- Sous 768 px, le tableau en mode `cards` devient une liste de cartes : la cellule titre en tête, le badge à droite, puis les autres cellules en paires « libellé : valeur » tirées de l'attribut `data-label`. Les actions de ligne passent en bas de la carte.
- Les tableaux en mode `scroll` (comptage d'inventaire, revue des écarts, lignes d'un document) gardent leur forme et défilent horizontalement dans leur conteneur, première colonne figée. Le conteneur porte `tabindex="0"`, `role="region"` et un `aria-label` pour être atteignable au clavier.

## 10. États et notifications

### Chargement

- Rendu serveur complet : pas d'indicateur, la page arrive entière.
- Contenu rechargé en Fetch (résultats filtrés, cartes KPI rafraîchies) : squelette à la place du contenu attendu, soit des blocs `#EAE7E0` aux dimensions des lignes à venir. Le squelette n'apparaît qu'après 300 ms d'attente, pour ne pas clignoter sur une réponse rapide. La zone porte `aria-busy="true"`.
- Le balayage lumineux du squelette est coupé quand `prefers-reduced-motion` vaut `reduce` : le bloc reste fixe.
- Action en cours : le bouton passe en état d'envoi (section 7). Pas de voile sur toute la page.

### Vide

`<x-empty-state>` avec une explication et une action. Trois cas distincts : rien n'a encore été créé, les filtres ne trouvent rien, il n'y a rien à signaler. Un tableau vide n'affiche pas ses en-têtes seuls.

### Erreur

| Cas | Forme | Contenu |
| :-- | :-- | :-- |
| Champ invalide | Message sous le champ et bandeau récapitulatif | Quoi corriger |
| Règle métier refusée (stock insuffisant, session d'inventaire déjà ouverte) | Bandeau `danger` en haut du formulaire, saisie conservée | Le chiffre utile et la marche à suivre |
| Échec d'un appel Fetch | Message dans la zone concernée, avec « Réessayer » | « Chargement impossible. Vérifiez la connexion. » |
| Session expirée (419) ou accès refusé (403) en Fetch | Bandeau avec lien vers la connexion ou le tableau de bord | Section 2 |
| Page introuvable ou interdite | Pages 404 et 403 | Voir `wireframes.md` |
| Erreur serveur (500) | Page d'erreur sobre | « Une erreur est survenue. Réessayez dans un instant. Si le problème persiste, prévenez l'administrateur. » |

Bandeau de stock insuffisant, pour une vente de plusieurs lignes :

> Stock insuffisant. La vente n'a pas été enregistrée.
> Cartouche toner HP 59A noir : 5 demandés, 3 disponibles à la Boutique du Plateau (14 à l'Entrepôt central).
> Réduisez la quantité ou demandez un transfert depuis l'entrepôt.

La ligne fautive du panier est marquée en rouge brique, son champ de quantité reçoit `aria-invalid="true"` et un bouton « Ramener à 3 » corrige d'un geste. Les autres lignes sont conservées.

### Succès

Toast après une action réussie, avec la référence du document créé et, s'il y a lieu, un lien d'action. Après la création d'un document, l'utilisateur arrive sur le détail de ce document. Après une vente, il reste sur le point de vente, panier vidé, prêt pour le client suivant.

### Répartition des notifications

| Canal | Pour quoi | Durée de vie |
| :-- | :-- | :-- |
| Toast | Confirmation d'une action de l'utilisateur | 5 à 8 secondes |
| Bandeau | Erreur bloquante, avertissement lié à la page (« Un inventaire est en cours sur cet emplacement ») | Tant que la situation dure |
| Cloche | Alertes de seuil émises par le système (D-013) | Jusqu'à lecture, puis consultables au centre d'alertes |

### Cloche d'alertes

- Icône `bi-bell` dans la barre supérieure, avec une pastille rouge brique portant le nombre de notifications non lues (« 9+ » au-delà de 9). Sans alerte non lue, pas de pastille.
- `aria-label` complet : « Alertes de stock, 4 non lues ».
- Le clic ouvre un panneau des 5 dernières alertes : produit, quantité restante, seuil, emplacement, ancienneté. Un clic sur une alerte ouvre la fiche produit et marque l'alerte comme lue. En pied : « Tout marquer comme lu » et « Voir toutes les alertes ».
- Le compteur est rafraîchi par un appel Fetch toutes les 60 secondes et après chaque opération de stock faite dans l'onglet. L'appel est suspendu quand l'onglet est masqué.
- Une alerte est émise au franchissement du seuil, pas à chaque mouvement sous le seuil. Le compteur de la cloche compte donc des événements non lus. Le nombre de produits actuellement sous seuil est celui de la carte KPI et du centre d'alertes.
- La cloche est visible des administrateurs et des gestionnaires. Le vendeur voit l'état du stock sur chaque produit, sans notification.

## 11. Formats locaux

Langue `fr`, fuseau `Africa/Abidjan` (UTC+0, sans heure d'été). Les formats sont produits par des fonctions utilitaires uniques côté serveur (directives Blade `@money`, `@date`, `@datetime`, `@qty`, noms indicatifs) et par `Intl.NumberFormat('fr-FR')` côté navigateur.

| Donnée | Format | Exemple |
| :-- | :-- | :-- |
| Montant | Entier, milliers séparés par une espace insécable, suffixe « F CFA » précédé d'une espace insécable | `1 200 000 F CFA` |
| Montant nul | | `0 F CFA` |
| Quantité | Entier, milliers séparés par une espace insécable, sans unité | `1 250` |
| Écart | Entier signé | `+12`, `-3`, `0` |
| Pourcentage | Entier suivi d'une espace insécable et du signe | `82 %` |
| Date | `JJ/MM/AAAA` | `13/11/2026` |
| Date et heure | `JJ/MM/AAAA HH:MM`, sur 24 heures | `13/11/2026 14:35` |
| Heure seule (opérations du jour) | `HH:MM` | `14:35` |
| Période | « du JJ/MM/AAAA au JJ/MM/AAAA » | du 01/11/2026 au 13/11/2026 |
| Téléphone | `+225` suivi de cinq groupes de deux chiffres | `+225 07 08 45 12 36` |
| SKU | Préfixe de catégorie, tiret, six chiffres | `IMP-000042` |
| Code-barres | 13 chiffres sans espace | `2000000000428` |
| Référence de bon | Préfixe, année, numéro sur six chiffres | `BS-2026-000128` |
| Délai de livraison | Entier suivi de « jours » | `7 jours` |

- Un montant ne se coupe jamais entre le nombre et « F CFA ». Dans un tableau, l'unité est portée par l'en-tête de colonne.
- Les dates relatives (« il y a 12 min ») ne servent que dans le panneau de la cloche. La date exacte figure alors dans l'attribut `title` et dans une balise `<time datetime>`.
- Le téléphone est enregistré au format affiché. La saisie accepte les chiffres avec ou sans espaces et l'indicatif, puis normalise.
- Les exports CSV écrivent les montants en entiers sans séparateur ni unité, les dates au format `JJ/MM/AAAA`, avec le point-virgule comme séparateur de colonnes et un BOM UTF-8 pour s'ouvrir correctement dans Excel.

## 12. Accessibilité

Cible : WCAG 2.1 niveau AA.

- Lien d'évitement : premier élément focalisable de chaque page, « Aller au contenu », visible à la prise de focus, qui mène à `#contenu`. Un second lien, « Aller à la recherche de produit », le suit.
- Focus visible : contour de 2 px en `#0B5C6E`, décalé de 2 px, sur tout élément interactif, déclenché par `:focus-visible`. Sur la barre latérale sombre, le contour est en `#7FC6D6`. Le contour n'est jamais supprimé sans équivalent.
- Navigation au clavier : tout se fait sans souris. L'ordre de tabulation suit l'ordre visuel. Les menus, onglets, listes de résultats et fenêtres modales suivent les modèles ARIA usuels (flèches, Entrée, Échap). Le focus est piégé dans une fenêtre modale ou un panneau offcanvas ouvert, puis rendu à l'élément déclencheur.
- Raccourcis : `/` place le curseur dans la recherche globale. Au point de vente, la recherche a le focus à l'ouverture et après chaque ajout. Aucun raccourci à une seule lettre n'agit pendant la saisie dans un champ.
- HTML sémantique : `<header>`, `<nav>`, `<main>`, `<footer>`, un seul `<h1>`, des niveaux de titre sans saut. Les boutons sont des `<button>`, les liens des `<a href>`. Les tableaux de données sont de vrais tableaux.
- Formulaires : chaque champ a un `<label>` relié. Aide et erreur sont reliées par `aria-describedby`. Les groupes de cases ou de boutons radio sont dans un `<fieldset>` avec `<legend>`.
- Zones vivantes : le conteneur de toasts est en `aria-live="polite"`. Les résultats de recherche annoncent leur nombre dans une zone `role="status"` (« 8 produits trouvés », « Aucun produit trouvé »). Un bandeau d'erreur bloquante porte `role="alert"`. Le total du panier est annoncé à chaque modification, en mode `polite`.
- Couleur : aucune information n'est portée par la couleur seule. États, écarts et sens de mouvement ont un libellé, un signe ou une icône.
- Icônes : une icône décorative porte `aria-hidden="true"`. Une icône seule dans un bouton est doublée d'un `aria-label`.
- Cibles tactiles : 44 px par 44 px au moins sur tablette, avec 8 px d'écart entre deux cibles voisines.
- Zoom : l'interface reste utilisable à 200 % de zoom et à 400 % sur une largeur équivalente à 320 px. La balise `viewport` n'interdit pas le zoom.
- Mouvement : sous `prefers-reduced-motion: reduce`, les transitions de panneau, de toast et de squelette sont supprimées. Rien ne clignote, rien ne défile seul.
- Session : un avertissement précède l'expiration de session quand un formulaire est en cours de saisie, et le panier du point de vente est conservé dans `sessionStorage`.
- Langue : `<html lang="fr">`. Les titres de page suivent le modèle « Objet · Rubrique · StockFlow ».

Contrôle prévu : parcours au clavier de chaque écran, passage d'un outil d'audit automatique, essai avec un lecteur d'écran sur les trois parcours principaux (vente, réception, comptage).

## 13. Documents imprimables

Documents concernés : bon de sortie (vente), bon de réception, bon de transfert, fiche d'inventaire (feuille de comptage et procès-verbal d'écarts), états PDF (état des stocks, historique des mouvements).

### Production

Chaque bon est une vue Blade rendue avec `layouts/print.blade.php`. Elle sert à l'impression depuis le navigateur (feuille `@media print`) et à la génération du PDF par `barryvdh/laravel-dompdf`. La mise en page des gabarits destinés à Dompdf s'appuie sur des tableaux et des blocs simples : ce moteur ne gère ni `flex` ni `grid`.

### Page

- A4 portrait, marges de 15 mm (`@page { size: A4; margin: 15mm; }`). Les états larges (historique des mouvements) passent en paysage.
- Noir sur papier blanc. Aucun aplat de couleur, aucun fond de cellule : les documents sortent sur des imprimantes laser monochromes et sont photocopiés. Les états sont écrits en toutes lettres.
- Filets de 0,5 pt en gris `#555555`, en-tête de tableau en gras souligné d'un filet de 1 pt.
- Police : DejaVu Sans, livrée avec Dompdf, qui couvre les accents français et dont les chiffres ont une chasse égale. Corps de 9 pt dans les tableaux, 10 pt ailleurs, titre de 14 pt. L'emploi de Public Sans dans les PDF demanderait de l'enregistrer dans Dompdf, sans prise en charge assurée des chiffres tabulaires : ce n'est pas retenu.
- Colonnes numériques alignées à droite, formats de la section 11.

### En-tête

Sur chaque document, de haut en bas :

1. à gauche, la raison sociale de l'entreprise, son adresse et son téléphone. À droite, le type de document en capitales (« BON DE SORTIE »), sa référence, sa date et son heure ;
2. un bloc de contexte sur deux colonnes : emplacement, auteur, puis selon le cas client, fournisseur et numéro de bon de livraison, emplacement source et destination, périmètre de l'inventaire ;
3. pour un document annulé, la mention « ANNULÉ le JJ/MM/AAAA par Nom : motif » dans un cadre, sous l'en-tête. Pas de filigrane en travers de la page.

Le logo de l'entreprise cliente peut remplacer la raison sociale s'il est fourni en noir. Le logotype StockFlow n'apparaît qu'en pied de page.

### Corps

- Tableau de lignes : numéro, SKU, désignation, quantité, puis prix unitaire et montant pour le bon de sortie. L'en-tête du tableau se répète sur chaque page (`thead { display: table-header-group; }`). Une ligne ne se coupe pas entre deux pages.
- Total en bas à droite, en gras. Pour le bon de sortie, le montant est aussi le seul total : le modèle ne porte ni remise ni taxe.
- Le bon de transfert et la feuille de comptage n'affichent aucun prix.
- La feuille de comptage liste les produits avec une colonne « Quantité comptée » vide à remplir à la main et une colonne « Observation ». Elle n'imprime pas le stock théorique, pour que le comptage reste à l'aveugle.
- Le procès-verbal d'écarts reprend théorique, compté, écart, valeur de l'écart et justification, avec un total des écarts en valeur.

### Zones de signature

En bas de la dernière page, des cadres de 60 mm sur 25 mm, chacun avec son intitulé, un emplacement « Nom » et un emplacement « Date ».

| Document | Signatures |
| :-- | :-- |
| Bon de sortie | Le vendeur, le client (« Reçu conforme ») |
| Bon de réception | Le réceptionnaire, le livreur |
| Bon de transfert | L'expéditeur, le destinataire |
| Feuille de comptage | Le ou les compteurs |
| Procès-verbal d'écarts | Le gestionnaire de stock, l'administrateur |

### Pied de page

Sur chaque page : référence du document, « Page 1 sur 2 », « Édité le JJ/MM/AAAA HH:MM par Nom », et « StockFlow » en petit. Pour les états, les filtres appliqués sont rappelés sous le titre (période, emplacement, catégorie) avec le nombre de lignes.

### À l'écran

La page d'un bon affiche le document centré sur une feuille de 210 mm de large, avec une barre d'actions au-dessus (« Imprimer », « Télécharger le PDF », retour) masquée à l'impression par `.d-print-none`. La navigation de l'application n'est pas imprimée.
