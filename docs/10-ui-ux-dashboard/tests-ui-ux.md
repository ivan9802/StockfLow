# Tests de la refonte

Deux familles de vérifications : les tests automatisés du dépôt, qui couvrent les calculs, la validation et les droits ; un parcours réel dans Chrome, qui couvre le JavaScript, les graphiques, les animations et le responsive.

## Tests automatisés

| Fichier | Tests | Ce qui est vérifié |
| :-- | --: | :-- |
| `tests/Unit/PeriodTest.php` | 26 | Bornes des dix préréglages au 19/11/2026 ; période de comparaison de chaque préréglage, y compris un 31 mars comparé à février ; période personnalisée ; refus d'une date de début postérieure, d'une date inexistante, d'un mauvais format, d'une période de plus de deux ans, d'un préréglage inconnu ; pas des graphiques (jour, semaine, mois) ; intervalles sans trou |
| `tests/Feature/Dashboard/DashboardPeriodTest.php` | 12 | Chiffre d'affaires du mois comparé à la même tranche du mois précédent (+50 %, vente annulée exclue) ; séries quotidiennes complètes et égales aux totaux ; transferts exclus des entrées et sorties ; hausse des ajustements lue comme défavorable ; pas de pourcentage quand la référence vaut 0 ; comparaison désactivable ; classement des produits les plus vendus ; vendeur limité à ses ventes et privé des données sensibles ; périodes invalides refusées en 422 ; période personnalisée appliquée ; page affichée avec sa période, période invalide ramenée au défaut ; état du stock indépendant de la période |
| `tests/Feature/Dashboard/DashboardEmptyDatabaseTest.php` | 2 | Tableau de bord sur une base sans produit ni emplacement, pour l'administrateur et le vendeur |
| `tests/Feature/Modules/OperationsHttpTest.php` | adapté | Indicateurs de l'instant égaux aux agrégats recalculés, vendeur sans valeur du stock ni mouvements, affichage de tous les écrans |

Résultat de la suite complète le 19/11/2026 : **166 tests réussis, 714 vérifications, aucun échec** (`php artisan test --testsuite=Unit,Feature`, 2 min 14 s).

Une exécution précédente, le même jour, avait révélé deux échecs : le tableau de bord renvoyait une erreur 500 sur une base sans emplacement, parce qu'une série vide était retirée de la réponse. Corrigé dans `DashboardService::forPeriod()` et couvert par `DashboardEmptyDatabaseTest`.

## Parcours dans un navigateur réel

Chrome sans fenêtre, piloté par son protocole de débogage depuis un script Node sans dépendance (hors dépôt), sur la base de démonstration, avec un compte administrateur.

| Vérification | 1440 px | 820 px | 390 px | 1440 px, moins d'animations |
| :-- | :-- | :-- | :-- | :-- |
| Connexion puis tableau de bord | Oui | Oui | Oui | Oui |
| Cinq graphiques dessinés, Chart.js chargé | Oui | Oui | Oui | Oui |
| Changement de période sans rechargement de la page | Oui | Oui | Oui | Oui |
| Chiffres après changement (mois dernier : 9 519 300 F CFA, +23 % contre 7 736 200 F CFA) identiques à ceux du serveur | Oui | Oui | Oui | Oui |
| Adresse mise à jour (`?period=last_month&compare=1`) | Oui | Oui | Oui | Oui |
| Période personnalisée incohérente refusée avec un message | Oui | Oui | Oui | |
| Aucune erreur JavaScript, aucun avertissement de console, aucun blocage CSP | Oui | Oui | Oui | Oui |
| Aucun défilement horizontal (tableau de bord, produits, stock, point de vente, inventaires, commandes) | Oui | Oui | Oui | |

Les captures ont été relues une par une. Elles ont fait apparaître les défauts UI-11 à UI-15 de `audit-interface.md`, tous corrigés puis vérifiés par une nouvelle capture. Une première capture montrait des graphiques incomplets : elle avait été prise en pleine animation. La capture en mode « moins d'animations », sans animation, a confirmé que les graphiques sont complets.

## Non couvert

- Pas de test de bout en bout dans le dépôt : le script Chrome est resté hors dépôt (tâche T-808).
- Pas de test sur tablette ou téléphone physiques, seulement par émulation de largeur.
- Pas de mesure outillée des contrastes des nouvelles couleurs de graphique (`#C2672E` sur fond clair) : les graphiques portent leurs valeurs en infobulle et en texte de repli, mais la couleur seule ne suffit pas à distinguer les séries pour certains daltonismes.
