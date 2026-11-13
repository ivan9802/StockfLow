# Cahier des Charges : Application Web de Gestion des Stocks (**StockFlow**)

## 1. Contexte et Problématique Réelle
**Contexte :** Une PME de distribution de matériel informatique et bureautique basée à Abidjan (ex. : *Ivoire Bureautique & Tech*) gère environ 800 références réparties entre un entrepôt central et un point de vente. Actuellement, l'équipe utilise des fichiers Excel partagés et des bons papier pour suivre les mouvements.

**Problèmes identifiés :**
* **Désynchronisation des données :** Les ventes en boutique ne sont saisies qu'en fin de journée, entraînant des ventes de produits en rupture réelle.
* **Écarts d'inventaire fréquents :** Des erreurs de saisie manuelle et des oublis d'enregistrement génèrent des pertes financières estimées à 8 % du chiffre d'affaires annuel.
* **Absence d'alerte proactive :** Les réapprovisionnements sont déclenchés tardivement, causant des ruptures sur les articles à forte rotation.
* **Manque de traçabilité :** Impossible de savoir rapidement qui a validé une sortie de stock ou ajusté une quantité lors d'un inventaire physique.

**Objectif du projet :** Développer une application web centralisée permettant le suivi en temps réel des niveaux de stock, l'automatisation des alertes de réapprovisionnement et la traçabilité complète des mouvements afin de réduire les erreurs de gestion.

---

## 2. Périmètre et Utilisateurs Cibles
L'application s'articule autour de trois profils utilisateurs avec des droits strictement cloisonnés :

| Rôle | Responsabilités principales | Permissions clés |
| :--- | :--- | :--- |
| **Administrateur** | Supervision globale, paramétrage système, gestion des comptes. | CRUD utilisateurs/rôles, accès aux journaux d'audit, validation des écarts d'inventaire. |
| **Gestionnaire de Stock** | Réception fournisseurs, inventaires physiques, gestion des seuils. | Entrées/sorties de stock, création/modification produits, gestion fournisseurs, exports. |
| **Agent Commercial / Vendeur** | Consultation des disponibilités et enregistrement des ventes. | Consultation du catalogue et des stocks en temps réel, création de bons de sortie. |

---

## 3. Spécifications Fonctionnelles

### Module 1 : Authentification et Sécurité
* **F1.1 :** Connexion sécurisée par email et mot de passe (hachage Bcrypt).
* **F1.2 :** Gestion des rôles et permissions via les Middlewares et Policies de Laravel.
* **F1.3 :** Journal d'audit (*Audit Log*) enregistrant automatiquement chaque création, modification ou suppression sur les stocks (utilisateur, date/heure, ancienne valeur, nouvelle valeur).

### Module 2 : Gestion du Catalogue Produits & Fournisseurs
* **F2.1 :** CRUD complet des catégories et des produits (SKU unique, libellé, description, prix d'achat, prix de vente, seuil d'alerte minimum, image).
* **F2.2 :** Génération automatique de codes-barres ou références internes lors de la création d'un produit.
* **F2.3 :** Gestion des fiches fournisseurs (raison sociale, contact, délai moyen de livraison) et association aux produits.

### Module 3 : Mouvements de Stock en Temps Réel
* **F3.1 (Entrées) :** Enregistrement des réceptions de commandes fournisseurs avec incrémentation automatique du stock physique.
* **F3.2 (Sorties) :** Enregistrement des ventes ou sorties internes avec vérification bloquante de la disponibilité en stock (interdiction du stock négatif).
* **F3.3 (Ajustements) :** Création de bons d'inventaire physique permettant de comparer le stock théorique au stock réel compté et de régulariser les écarts avec justification obligatoire.

### Module 4 : Alertes et Tableau de Bord Décisionnel
* **F4.1 :** Calcul automatique et affichage des KPI en temps réel : valeur totale du stock, nombre de références actives, produits en alerte, mouvements du jour.
* **F4.2 :** Système de notification visuelle dans l'interface dès qu'un produit passe sous son seuil critique (`quantite_actuelle <= seuil_alerte`).
* **F4.3 :** Exportation des états de stock et de l'historique des mouvements aux formats CSV/PDF pour les contrôles comptables.

---

## 4. Spécifications Techniques & Architecture

### Stack Technologique
* **Back-end :** PHP 8.2+ avec le framework **Laravel 11** (architecture MVC, Eloquent ORM, Migrations, Seeders, Form Requests pour la validation).
* **Front-end :** Moteur de templates **Blade**, **Bootstrap 5** (interface responsive), JavaScript natif / AJAX (Fetch API) pour l'actualisation dynamique des stocks et les recherches instantanées sans rechargement de page.
* **Base de données :** **MySQL 8.0** (ou MariaDB) avec moteur InnoDB pour garantir l'intégrité référentielle et la gestion des transactions ACID.

### Exigences Non-Fonctionnelles
* **Intégrité transactionnelle :** Utilisation systématique de `DB::transaction()` lors des mouvements de stock pour éviter toute incohérence en cas d'accès concurrents.
* **Performance :** Temps de réponse inférieur à 500 ms sur les requêtes de recherche produit (indexation des colonnes `sku` et `name`).
* **Ergonomie :** Interface épurée, utilisable sur ordinateur de bureau comme sur tablette en entrepôt.

---

## 5. Modèle Conceptuel de Données (Base SQL)

```text
+------------------+       +---------------------+       +--------------------+
|    categories    |       |      products       |       |     suppliers      |
+------------------+       +---------------------+       +--------------------+
| id (PK)          |<------| category_id (FK)    |   +-->| id (PK)            |
| name             |       | supplier_id (FK)    |---+   | name               |
| description      |       | id (PK)             |       | contact_email      |
+------------------+       | sku (UNIQUE)        |       | phone              |
                           | name                |       +--------------------+
                           | purchase_price      |
                           | selling_price       |
                           | current_stock       |
                           | alert_threshold     |
                           +---------------------+
                                      ^
                                      |
+------------------+       +---------------------+
|      users       |       |   stock_movements   |
+------------------+       +---------------------+
| id (PK)          |<------| user_id (FK)        |
| name             |       | product_id (FK)     |
| email            |       | id (PK)             |
| password         |       | type (IN/OUT/ADJ)   |
| role             |       | quantity            |
+------------------+       | stock_before        |
                           | stock_after         |
                           | reason / reference  |
                           | created_at          |
                           +---------------------+
```

---

## 6. Planning Prévisionnel de Réalisation (8 Semaines)

| Phase | Livrables principaux | Durée |
| :--- | :--- | :--- |
| **1. Analyse & Conception** | Validation du cahier des charges, maquettes UI (wireframes), MCD/MLD SQL. | Semaine 1 |
| **2. Initialisation & Auth** | Setup Laravel + Bootstrap, migrations SQL, seeders de test, authentification et rôles. | Semaine 2 |
| **3. Gestion Catalogue** | CRUD Catégories, Fournisseurs, Produits avec recherche dynamique et filtres. | Semaines 3 - 4 |
| **4. Moteur de Stock** | Gestion des entrées, sorties, inventaires physiques, transactions SQL et journal d'audit. | Semaines 5 - 6 |
| **5. Dashboard & Exports** | Tableau de bord KPI, système d'alertes seuils critiques, génération PDF/CSV. | Semaine 7 |
| **6. Tests & Soutenance** | Tests fonctionnels, correction des bugs, rédaction du rapport et préparation démo. | Semaine 8 |

---

## 7. Critères de Réussite et Validation Académique
1. **Zéro stock négatif possible** même lors de soumissions simultanées sur un même produit.
2. **Traçabilité à 100 %** : chaque variation de `current_stock` dans la table `products` correspond exactement à une ligne dans `stock_movements`.
3. **Jeu de données réaliste** : présentation lors de la soutenance avec une base pré-remplie (minimum 50 produits, 3 rôles actifs et 100 mouvements historiques) démontrant les alertes en direct.