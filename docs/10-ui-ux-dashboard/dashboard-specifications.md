# Spécifications du tableau de bord

## Organisation

| Ordre | Bloc | Administrateur et gestionnaire | Vendeur |
| :-- | :-- | :-- | :-- |
| 1 | En-tête : titre, date du jour, période active, période de comparaison, sélecteur de période | Oui | Oui |
| 2 | État du stock, à l'instant : valeur, unités, références actives, sous le seuil, en rupture, commandes en attente | Oui | Sans valeur ni commandes |
| 3 | Sur la période : chiffre d'affaires, ventes, panier moyen, unités entrées, unités sorties, transferts, ajustements | Oui | Ses ventes seulement |
| 4 | Graphiques : entrées et sorties ; ventes | Les deux | Ses ventes |
| 5 | Graphiques : produits les plus vendus ; stock par emplacement ; niveau de stock | Les trois | Top de ses ventes, niveau de stock |
| 6 | Activité : à réapprovisionner, alertes récentes, derniers mouvements, dernières ventes | Tout | Ses dernières ventes |

## Sélection de la période

Préréglages : aujourd'hui, hier, 7 derniers jours, 30 derniers jours (par défaut), cette semaine, la semaine dernière, ce mois-ci, le mois dernier, cette année, l'année dernière, période personnalisée. La semaine commence le lundi. Les bornes sont des journées entières dans le fuseau `Africa/Abidjan`.

Période personnalisée : date de début et date de fin au format du navigateur, bouton « Appliquer », lien « Réinitialiser » (retour aux 30 derniers jours avec comparaison). Règles, vérifiées par le serveur :

- les deux dates sont obligatoires et doivent exister au calendrier ;
- la date de fin est postérieure ou égale à la date de début ;
- la période couvre deux ans au plus.

Le navigateur vérifie l'ordre des dates avant l'envoi pour éviter un aller-retour, mais seule la validation du serveur fait foi (`DashboardPeriodRequest`, classe `App\Support\Period`). Une période invalide reçue par Fetch renvoie HTTP 422 avec le message ; reçue dans l'adresse de la page, elle ramène à la période par défaut avec un bandeau d'avertissement.

## Actualisation

1. La page arrive complète depuis le serveur : elle est lisible sans JavaScript (graphiques remplacés par des listes).
2. Un changement de préréglage, de la case de comparaison, ou le bouton « Appliquer » interroge `GET /internal/dashboard` par Fetch.
3. Pendant la requête : voile léger sur les indicateurs (`aria-busy`), indicateur tournant sur « Appliquer ». Une requête plus récente annule la précédente.
4. À la réponse : les valeurs défilent jusqu'au nouveau chiffre, puis sont brièvement surlignées ; les graphiques passent aux nouvelles données par transition ; l'adresse de la page est mise à jour (la période se partage par un lien).
5. En cas d'erreur réseau : message sous le sélecteur, les données affichées restent celles de la période précédente.
6. Rafraîchissement automatique toutes les 30 secondes, seulement si la période inclut aujourd'hui et si l'onglet est visible.

## Point d'accès

`GET /internal/dashboard?period=<préréglage>&from=AAAA-MM-JJ&to=AAAA-MM-JJ&compare=0|1`

Protégé par la session, le jeton CSRF, le droit `stock.view` et une limite de 120 appels par minute. Réponse :

```json
{
  "period":   { "preset": "this_month", "from": "2026-11-01", "to": "2026-11-19", "days": 19, "granularity": "day", "label": "Ce mois-ci (du 01/11/2026 au 19/11/2026)" },
  "previous": { "preset": "previous", "from": "2026-10-01", "to": "2026-10-19", "days": 19, "granularity": "day", "label": "Du 01/10/2026 au 19/10/2026" },
  "snapshot": { "active_products": 60, "total_units": 1458, "low_count": 8, "out_count": 7, "stock_value": 27590600, "pending_orders": 8 },
  "kpis":     { "revenue": { "value": 102000, "previous": 68000, "change": 50.0, "trend": "up", "tone": "good" } },
  "charts":   { "sales": [], "movements": [], "warehouses": [], "stock_status": [] },
  "top_products": [],
  "alerts": 4,
  "updated_at": "15:00"
}
```

Les clés réservées à l'administrateur et au gestionnaire (`stock_value`, `pending_orders`, mouvements, emplacements) sont absentes de la réponse du vendeur, pas seulement masquées à l'affichage.

## Graphiques

| Graphique | Type | Données | Pas |
| :-- | :-- | :-- | :-- |
| Entrées et sorties de stock | Courbes | Unités entrées et sorties hors transferts | Jour jusqu'à 62 jours, semaine jusqu'à 183, mois au-delà |
| Ventes | Barres | Chiffre d'affaires par intervalle, nombre de ventes dans l'infobulle | Idem |
| Produits les plus vendus | Barres horizontales | 5 premiers en unités, chiffre d'affaires dans l'infobulle ; clic vers la fiche | Période |
| Stock par emplacement | Anneau | Unités par emplacement, part en % dans l'infobulle | À l'instant |
| Niveau de stock | Anneau | Produits en stock, sous le seuil, en rupture | À l'instant |

Chaque intervalle de la période a son point, y compris sans donnée. Un graphique sans aucune donnée affiche « Aucune donnée sur cette période ». Chart.js est chargé à la demande dans un fichier séparé (70 Ko compressés) que les autres écrans ne téléchargent pas.
