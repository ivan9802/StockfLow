# Journal de la refonte de l'interface

## 19/11/2026

### Tableau de bord

- Sélection de la période : onze préréglages et une période personnalisée, validés par le serveur.
- Comparaison facultative avec la période précédente : variation en pourcentage, valeur de référence, tendance, lecture favorable ou défavorable selon l'indicateur.
- Indicateurs à l'instant : valeur du stock, unités, références actives, sous le seuil, ruptures, commandes en attente.
- Indicateurs de la période : chiffre d'affaires, ventes, panier moyen, unités entrées, unités sorties, transferts, ajustements.
- Cinq graphiques Chart.js : entrées et sorties, ventes, produits les plus vendus, stock par emplacement, niveau de stock.
- Actualisation sans rechargement, adresse partageable, rafraîchissement automatique quand la période inclut aujourd'hui.
- Activité récente en tableaux compacts : à réapprovisionner, alertes récentes, derniers mouvements, dernières ventes.
- Une seule vue pour tous les profils ; la vue séparée du vendeur (`dashboard/agent.blade.php`) est supprimée.

### Design system v2

- Rayons de 6, 10 et 14 px, badges en pilule, ombres légères à trois niveaux.
- États de chargement des boutons, voile sur les blocs en cours de rechargement, squelettes.
- En-têtes de tableaux figés, navigation latérale avec repère animé, menus et modales modernisés.
- Connexion en deux colonnes sur ordinateur.

### Animations

- Apparition des pages, cartes en cascade, défilement des valeurs, transitions des graphiques, survols, modales, toasts, lignes filtrées.
- Désactivées quand le système demande moins d'animations.

### Corrections

- Montants qui passaient à la ligne dans les cartes d'indicateurs.
- Tableaux d'activité écrasés ou tronqués sur le tableau de bord.
- Majuscule fautive au nom du mois dans la date.
- Tableau de bord en erreur 500 sur une base sans emplacement (trouvé par la suite de tests, voir `tests-ui-ux.md`).

### Dépendance

- `chart.js` 4.6, installé par npm et compilé par Vite en fichier séparé (décision D-021, qui remplace D-015).

### Fichiers

| Ajoutés | Modifiés | Supprimé |
| :-- | :-- | :-- |
| `app/Support/Period.php` | `app/Services/DashboardService.php` | `resources/views/dashboard/agent.blade.php` |
| `app/Http/Requests/DashboardPeriodRequest.php` | `app/Http/Controllers/DashboardController.php` | |
| `resources/scss/_design-system.scss` | `app/Http/Controllers/Internal/DashboardDataController.php` | |
| `resources/scss/_dashboard.scss` | `app/Support/Format.php` (format des pourcentages) | |
| `resources/scss/_motion.scss` | `resources/scss/_variables.scss`, `app.scss` | |
| `resources/views/components/dash-kpi.blade.php` | `resources/views/dashboard/index.blade.php` | |
| `resources/views/components/chart-card.blade.php` | `resources/views/layouts/guest.blade.php`, `auth/login.blade.php` | |
| `resources/views/dashboard/_delta.blade.php` | `resources/js/modules/dashboard.js`, `live-list.js`, `app.js` | |
| `tests/Unit/PeriodTest.php` | `tests/Feature/Modules/OperationsHttpTest.php` | |
| `tests/Feature/Dashboard/DashboardPeriodTest.php` | `package.json`, `package-lock.json` | |
| `tests/Feature/Dashboard/DashboardEmptyDatabaseTest.php` | `DECISIONS.md`, `TASKS.md` | |
