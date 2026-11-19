# Audit de l'interface avant la refonte

Audit réalisé le 19/11/2026 sur la version du 16/11/2026, à partir des vues Blade, des feuilles `resources/scss`, des modules `resources/js` et d'un parcours réel dans Chrome (largeurs 1440, 820 et 390 px).

## Ce qui tenait déjà

- Un design system existait (`docs/03-design/ui-guidelines.md`) : palette à accent unique, Public Sans, badges avec libellé et icône, composants Blade réutilisables. La refonte le prolonge au lieu de le remplacer.
- Aucun défilement horizontal de page à 360 px, tableaux avec colonnes prioritaires.
- Accessibilité de base : lien d'évitement, focus visible, libellés de champs, `aria-live` sur les listes filtrées.
- Politique de sécurité du contenu stricte (pas de script ni de style en ligne), qui contraint les choix d'animation.

## Défauts relevés

| Réf. | Écran | Constat | Gravité |
| :-- | :-- | :-- | :-- |
| UI-01 | Tableau de bord | Indicateurs limités à « aujourd'hui », aucune période d'analyse, aucune comparaison | Majeure |
| UI-02 | Tableau de bord | Pas de graphique d'évolution ; la répartition par emplacement était la seule visualisation | Majeure |
| UI-03 | Tableau de bord | Le vendeur avait une page séparée (`dashboard/agent.blade.php`), à maintenir en double | Moyenne |
| UI-04 | Toutes | Surfaces plates, rayons de 4 px, aucune ombre : hiérarchie entre fond, cartes et menus peu marquée | Moyenne |
| UI-05 | Toutes | Aucun retour visuel pendant un envoi de formulaire (seul le bouton se désactivait) | Moyenne |
| UI-06 | Toutes | Aucune transition à l'ouverture des pages, des menus, des modales ni au filtrage d'une liste | Mineure |
| UI-07 | Navigation | Page active signalée par un filet sec, sans transition | Mineure |
| UI-08 | Connexion | Écran centré minimal, sans présentation de l'outil | Mineure |
| UI-09 | États vides | Icône seule, peu visible | Mineure |
| UI-10 | Tableaux | En-têtes non figés au défilement | Mineure |

## Défauts trouvés pendant la refonte et corrigés

| Réf. | Constat | Correction |
| :-- | :-- | :-- |
| UI-11 | Montants coupés sur deux lignes dans les cartes (« 27 590 600 F / CFA ») | Taille de police adaptée à la largeur, pas de retour à la ligne sur ordinateur |
| UI-12 | Tableau « Derniers mouvements » à neuf colonnes écrasé dans une demi-largeur | Version compacte à trois colonnes, détails en sous-ligne |
| UI-13 | Colonne « Statut » des dernières ventes tronquée | Version compacte, tableau défilant dans sa carte |
| UI-14 | Date de l'en-tête avec majuscule au mois (« Novembre ») | Seule la première lettre est mise en capitale |
| UI-15 | Cartes empilées une par ligne sur téléphone, page de plus de 7 000 px | Deux cartes par ligne sous 576 px |

## Hors périmètre

L'écran « Paramètres » cité dans le cahier de la refonte n'existe pas dans StockFlow : les réglages vivent dans `.env` et `config/stockflow.php`, et les seuls réglages modifiables depuis l'interface (emplacements, comptes) ont leurs écrans. Aucun écran factice n'a été créé.
