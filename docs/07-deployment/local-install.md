# Installation locale par script

Ce document s'adresse à qui installe StockFlow sur un poste de développement ou de démonstration. Pour un serveur de production, voir `deployment.md`.

## Prérequis du poste

- Docker Desktop (Windows, macOS) ou Docker Engine (Linux), avec Docker Compose v2 : la commande `docker compose version` doit répondre.
- Git.
- Sous Windows : PowerShell 7 (`pwsh`) pour `install.ps1`, ou Git Bash pour `install.sh`.
- 4 Go de mémoire libre et environ 5 Go de disque pour les images et les volumes.
- Ports libres sur le poste : 8080 (application), 3307 (MySQL), 5173 (Vite en mode développement). Ils se changent dans `.env` (`APP_PORT`, `FORWARD_DB_PORT`, `VITE_PORT`) ou par variable d'environnement du même nom, qui l'emporte sur `.env`.

PHP, Composer, Node et MySQL tournent dans les conteneurs : rien d'autre n'est à installer.

## Installer

Depuis le dossier du clone :

| Système | Données de référence seules | Avec le jeu de démonstration |
| :-- | :-- | :-- |
| Windows (PowerShell 7) | `pwsh -File scripts/install.ps1` | `pwsh -File scripts/install.ps1 -Demo` |
| Linux, macOS, Git Bash | `bash scripts/install.sh` | `bash scripts/install.sh --demo` |

Les deux scripts font la même chose, dans cet ordre, et s'arrêtent à la première erreur avec un message qui indique la cause. Ils affichent onze étapes numérotées :

1. Vérification de Docker, du démon Docker, de Docker Compose v2 et des fichiers du projet.
2. Copie de `.env.example` en `.env` si `.env` n'existe pas. Un `.env` existant n'est jamais modifié. Le script refuse de continuer si `.env` contient `APP_ENV=production`.
3. `docker compose up -d --build --wait` : construction de l'image de développement et démarrage de `app`, `web` et `db`. L'option `--wait` attend que MySQL passe son contrôle de santé.
4. Attente explicite de MySQL (`mysqladmin ping`), jusqu'à 5 minutes.
5. `composer install` dans le conteneur.
6. `php artisan key:generate`, seulement si `APP_KEY` est vide.
7. `npm ci`.
8. `npm run build` (une trentaine de secondes à quelques minutes selon le poste).
9. Sans option : `php artisan migrate --force`. Avec l'option démonstration : `php artisan migrate:fresh --force --seed --seeder=DemoSeeder` (une à quelques minutes, davantage sur un poste chargé).
10. Sans option : `php artisan db:seed --force` (deux emplacements et premier administrateur, sans effet s'ils existent déjà). Avec l'option démonstration : `php artisan stock:verify`.
11. Contrôle de `http://localhost:<APP_PORT>/up`, qui doit répondre 200.

Le script affiche enfin l'adresse et les identifiants.

Durées mesurées par le QA (`docs/06-testing/installation-check.md`) pour une installation complète avec démonstration depuis un clone neuf : 476 s avec `install.sh`, 936 s avec `install.ps1` sur un poste chargé. Comptez quelques minutes, davantage sur un poste chargé.

Options :

| PowerShell | Bash | Effet |
| :-- | :-- | :-- |
| `-Demo` | `--demo` | Charge le jeu de démonstration. Il remplace toute la base de développement |
| `-Yes` | `--yes` | Accepte d'avance la confirmation demandée par la démonstration quand la base contient déjà des comptes |
| `-Check` | `--check` | Vérifie seulement les prérequis |

### Identifiants affichés

- Sans démonstration : l'adresse de l'administrateur (`ADMIN_EMAIL`, par défaut `admin@stockflow.example`). À la première installation, sans `ADMIN_PASSWORD`, le seeder affiche une ligne « Compte administrateur créé : adresse / mot de passe ». Ce mot de passe n'est affiché qu'une fois et doit être changé à la première connexion. Pour le fixer à l'avance, renseignez `ADMIN_PASSWORD` dans `.env` avant de lancer le script : il n'est alors pas réaffiché, le script renvoie à `.env`.
- Avec démonstration : les comptes de démonstration, mot de passe commun `demo-stockflow-2026` (liste complète dans `README.md`).

### Relancer le script

Le script se relance sans casse : `.env` et `APP_KEY` sont conservés, les migrations déjà passées sont ignorées, le seeder de référence ne recrée rien. Seule l'option démonstration efface la base ; si la base contient déjà des comptes, elle demande de taper `OUI`, ou exige `-Yes` / `--yes` quand il n'y a pas de terminal.

## Démarrage des jours suivants

| Système | Commande |
| :-- | :-- |
| Windows | `pwsh -File scripts/start.ps1` |
| Linux, macOS, Git Bash | `bash scripts/start.sh` |

Le script démarre les conteneurs (`docker compose up -d --wait`), attend que `/up` réponde 200 et ouvre l'adresse dans le navigateur. Option `-NoOpen` / `--no-open` pour ne pas ouvrir le navigateur.

Arrêt en fin de journée : `docker compose stop`. Les données restent dans les volumes. Ne lancez pas `docker compose down -v` : l'option `-v` supprime la base.

## Seconde installation sur le même poste

Le nom du projet Compose est le nom du dossier du clone : deux clones dans deux dossiers différents ont des conteneurs et des volumes distincts. Les ports, eux, doivent être différents. Exemple utilisé pour la vérification par le QA (T-907), à lancer pendant que l'installation principale occupe 8080, 3307 et 5173 :

```bash
git clone <dépôt> stockflow-qa && cd stockflow-qa && APP_PORT=18080 FORWARD_DB_PORT=13307 VITE_PORT=15173 bash scripts/install.sh --demo --yes
```

Équivalent PowerShell :

```powershell
git clone <dépôt> stockflow-qa; Set-Location stockflow-qa; $env:APP_PORT='18080'; $env:FORWARD_DB_PORT='13307'; $env:VITE_PORT='15173'; pwsh -File scripts/install.ps1 -Demo -Yes
```

Les variables d'environnement doivent rester définies pour toutes les commandes `docker compose` suivantes dans ce clone. Pour une installation durable, mettez plutôt ces valeurs dans le `.env` du clone.

Changez aussi `APP_URL` dans le `.env` du clone (par exemple `APP_URL=http://localhost:18080`) : les variables du poste ne sont pas transmises au conteneur `app`, et avec la valeur par défaut les adresses produites par l'application pointeraient vers l'installation principale sur le port 8080.

Nettoyage de cette seconde installation, depuis son dossier et seulement là : `docker compose down -v --rmi local`.

## Si le script s'arrête

| Message | Cause probable | Que faire |
| :-- | :-- | :-- |
| « Docker est introuvable » | Docker non installé ou absent du `PATH` | Installer Docker Desktop, rouvrir le terminal |
| « Le démon Docker ne répond pas » | Docker Desktop arrêté | Lancer Docker Desktop, attendre qu'il soit prêt, relancer |
| Échec de `docker compose up` sur un port | Port 8080, 3307 ou 5173 déjà pris | Changer `APP_PORT`, `FORWARD_DB_PORT` ou `VITE_PORT` dans `.env` |
| « MySQL ne répond pas après 5 minutes » | Premier démarrage après un redémarrage du poste, disque lent | `docker compose logs db`, puis relancer le script |
| `/up` ne répond pas 200 | Erreur PHP au démarrage, assets non compilés | `docker compose logs app web`, `storage/logs/laravel-*.log` |
