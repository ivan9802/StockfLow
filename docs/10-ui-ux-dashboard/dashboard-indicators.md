# Règles de calcul des indicateurs

Tous les calculs sont dans `App\Services\DashboardService`, en requêtes agrégées (une requête par famille d'indicateurs ou par graphique, jamais de chargement ligne à ligne). Les dates s'entendent dans le fuseau `Africa/Abidjan`, bornes comprises.

## État du stock, mesuré à l'instant

La base conserve les niveaux de stock courants et l'historique des mouvements, pas de photographie du stock à une date passée. Ces indicateurs ne dépendent donc pas de la période et n'ont pas de comparaison. Ils sont signalés « à l'instant » à l'écran.

| Indicateur | Calcul |
| :-- | :-- |
| Références actives | Produits actifs non supprimés |
| Unités en stock | Somme des quantités de tous les emplacements, produits actifs |
| Valeur du stock | Somme de quantité × prix d'achat courant de la fiche, produits actifs. Le coût d'achat réel de chaque lot n'est pas suivi (pas de coût moyen pondéré) : la valeur est une estimation |
| Sous le seuil | Produits actifs dont le stock total est supérieur à 0 et au plus égal au seuil d'alerte |
| En rupture | Produits actifs dont le stock total vaut 0 |
| Commandes en attente | Commandes au statut « Commandée » ou « Reçue en partie » |

## Flux de la période

| Indicateur | Calcul | Lecture d'une hausse |
| :-- | :-- | :-- |
| Chiffre d'affaires | Somme des `total_amount` des ventes enregistrées dont `sold_at` est dans la période. Les ventes annulées sont exclues. Le montant est celui du jour de la vente, figé dans la vente | Favorable |
| Ventes | Nombre de ces ventes | Favorable |
| Panier moyen | Chiffre d'affaires ÷ nombre de ventes, arrondi au franc ; 0 sans vente | Favorable |
| Unités entrées | Somme des quantités des mouvements entrants de la période, transferts exclus : réceptions, annulations de vente, ajustements et régularisations à la hausse | Neutre |
| Unités sorties | Somme des quantités des mouvements sortants, transferts exclus : ventes, sorties internes, ajustements et régularisations à la baisse | Neutre |
| Transferts | Nombre de transferts effectués (non annulés) dont la date est dans la période | Neutre |
| Ajustements | Nombre de mouvements d'ajustement manuel et de régularisation d'inventaire | Défavorable : davantage de corrections signale davantage d'écarts |

Les transferts sont exclus des entrées et des sorties : ils déplacent le stock d'un emplacement à l'autre sans le faire entrer ni sortir de l'entreprise. Les compter doublerait les flux.

Une vente annulée a laissé deux mouvements (sortie puis annulation) : elle apparaît dans les unités sorties et dans les unités entrées, pas dans le chiffre d'affaires. C'est voulu : les mouvements de stock ont réellement eu lieu.

## Comparaison avec la période précédente

| Période choisie | Période de référence |
| :-- | :-- |
| Ce mois-ci (1er au 19 novembre) | Même tranche du mois précédent (1er au 19 octobre) ; le 31 mars se compare au 1er au 28 février |
| Le mois dernier | Le mois complet d'avant |
| Cette année | Même tranche de l'année précédente |
| L'année dernière | L'année complète d'avant |
| Toute autre période de N jours | Les N jours qui précèdent immédiatement (7 derniers jours comparés aux 7 jours d'avant, cette semaine aux mêmes jours de la semaine précédente) |

Pour chaque flux :

- variation = (valeur − référence) ÷ référence × 100, arrondie à une décimale ;
- si la référence vaut 0, la variation n'est pas calculée (pas de division par zéro) : la carte affiche « nouveau » si la valeur est positive ;
- tendance : hausse, baisse ou stable ;
- lecture : favorable ou défavorable selon le sens de l'indicateur (tableau ci-dessus), neutre si la tendance est stable ou l'indicateur neutre.

La couleur n'est jamais le seul signal : une flèche, le signe du pourcentage et une phrase pour les lecteurs d'écran accompagnent chaque évolution.

## Périmètre selon le profil

| Donnée | Administrateur, gestionnaire | Vendeur |
| :-- | :-- | :-- |
| Ventes, chiffre d'affaires, panier, top produits | Toutes les ventes | Ses propres ventes |
| Valeur du stock, commandes en attente | Oui | Non |
| Entrées, sorties, transferts, ajustements, graphique des mouvements | Oui | Non |
| Stock par emplacement | Oui | Non (consultable dans « État des stocks ») |
| Unités en stock, sous le seuil, ruptures, niveau de stock | Oui | Oui |

Le filtrage est fait dans les requêtes, pas à l'affichage : le JSON du vendeur ne contient pas les clés réservées.

## Limites

- Pas de comparaison pour l'état du stock (voir plus haut). Reconstituer le stock d'une date passée en rejouant les mouvements serait possible, mais coûteux sur une longue période ; ce n'est pas fait dans cette version.
- La valeur du stock suit le prix d'achat de la fiche, pas le coût réel des lots.
- Le chiffre d'affaires est celui des bons de sortie : StockFlow ne gère ni remises, ni taxes, ni encaissements.
