# Déploiement de StockFlow en production

Ce document décrit l'installation de StockFlow sur un serveur, la mise à jour et le retour arrière. La sauvegarde et la restauration sont détaillées dans `backup-restore.md`.

## Architecture de production

Un serveur Linux fait tourner trois conteneurs décrits dans `compose.prod.yaml` :

| Service | Image | Rôle |
| :-- | :-- | :-- |
| `app` | `stockflow-app:<version>`, construite depuis `docker/php/Dockerfile` (étape `prod`) | PHP-FPM 8.4 avec le code, les dépendances et les fichiers front compilés |
| `web` | `stockflow-web:<version>`, construite depuis le même Dockerfile (étape `web`) | Nginx avec la configuration `docker/nginx/default.conf`, les fichiers publics et les fichiers CSS et JavaScript compilés de la même version que le code |
| `db` | `mysql:8.4` | Base de données, fuseau `Africa/Abidjan`, jeu de caractères `utf8mb4` |

Le service `web` n'écoute que sur `127.0.0.1:8080`. Le HTTPS est assuré en amont par un proxy inverse installé sur le serveur (Caddy ou Nginx). MySQL n'est publié sur aucun port : il n'est joignable que par le conteneur `app`.

Données persistantes, dans des volumes Docker :

| Volume | Contenu |
| :-- | :-- |
| `dbdata` | Fichiers de la base MySQL |
| `storage` | `storage/app` : images des produits |
| `logs` | `storage/logs` : journaux de l'application |

L'image `app` ne contient aucun secret : `.env.production` lui est fourni au démarrage.

## 1. Prérequis du serveur

- Un serveur Linux 64 bits (par exemple Debian 12 ou Ubuntu 24.04), avec 2 processeurs et 4 Go de mémoire au moins.
- Docker Engine et le greffon Docker Compose v2 (commande `docker compose`). Suivez la documentation officielle de Docker pour votre distribution.
- `git`, `curl`, `gzip` et `openssl`.
- Un nom de domaine dont l'enregistrement DNS pointe vers le serveur, par exemple `stock.example.ci`.
- Un proxy inverse avec certificat TLS : Caddy ou Nginx.
- Un pare-feu qui n'ouvre que les ports 22 (SSH), 80 et 443.

Le compte qui déploie doit pouvoir lancer `docker` (membre du groupe `docker`, ou `root`).

## 2. Récupérer le code

```bash
sudo mkdir -p /opt/stockflow
sudo chown "$USER" /opt/stockflow
git clone https://github.com/ivan9802/StockfLow.git /opt/stockflow
cd /opt/stockflow
git checkout main
```

Les scripts de `scripts/` sont enregistrés comme exécutables dans le dépôt.

Toutes les commandes qui suivent se lancent depuis `/opt/stockflow`.

## 3. Préparer `.env.production`

Le fichier `.env.production` sert à deux choses : Docker Compose y lit les mots de passe MySQL, et l'application y lit sa configuration. Il ne doit jamais être versionné (il est déjà exclu par `.gitignore` et `.dockerignore`).

```bash
cp .env.example .env.production
chmod 600 .env.production
```

Générez la clé de l'application et deux mots de passe forts :

```bash
echo "base64:$(openssl rand -base64 32)"   # valeur de APP_KEY
openssl rand -hex 24                        # valeur de DB_PASSWORD
openssl rand -hex 24                        # valeur de DB_ROOT_PASSWORD
```

Préférez des mots de passe sans `$`, `#`, guillemet ni espace : ces caractères sont interprétés par Docker Compose. Les valeurs hexadécimales ci-dessus n'en contiennent pas.

Modifiez ensuite le fichier. Variables à changer par rapport à `.env.example` :

| Variable | Valeur en production |
| :-- | :-- |
| `APP_ENV` | `production` (vérifié par le script de déploiement) |
| `APP_DEBUG` | `false` (vérifié par le script de déploiement) |
| `APP_KEY` | La valeur `base64:...` générée ci-dessus. Ne la changez plus jamais : elle chiffre les sessions |
| `APP_URL` | L'adresse publique, en `https://`, par exemple `https://stock.example.ci` |
| `LOG_LEVEL` | `warning` |
| `DB_PASSWORD` | Mot de passe fort |
| `DB_ROOT_PASSWORD` | Autre mot de passe fort |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_ENCRYPT` | `true` (déjà la valeur de `.env.example`) |
| `TRUSTED_PROXIES` | `*` : l'application lit l'adresse du client et le schéma HTTPS dans les en-têtes `X-Forwarded-*` du proxy inverse. Sans cette valeur, la limitation des tentatives de connexion compterait toute l'entreprise comme une seule adresse et HSTS ne serait pas envoyé |

Variables à garder telles quelles : `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_PORT=3306`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`, `APP_TIMEZONE=Africa/Abidjan`, `APP_LOCALE=fr`.

`APP_PORT=8080` fixe le port local du service `web` ; le script de déploiement le lit dans `.env.production` pour ses contrôles. `FORWARD_DB_PORT` et `VITE_PORT` ne servent qu'en développement : supprimez-les.

Renseignez les variables d'identité de l'entreprise, présentes en fin de `.env.example`. Elles sont lues par `config/stockflow.php` et reprises sur l'écran de connexion, les bons imprimés et la page des mentions légales :

```dotenv
COMPANY_NAME="Ivoire Bureautique & Tech"
COMPANY_ADDRESS="Abidjan, Côte d'Ivoire"
COMPANY_PHONE="+225 00 00 00 00 00"
COMPANY_EMAIL=contact@example.ci
COMPANY_RCCM="CI-ABJ-00-0000-B00-00000"
COMPANY_PUBLISHER="Nom du directeur de la publication"
COMPANY_HOST="Nom et adresse de l'hébergeur"
```

Remplacez chaque valeur par les informations réelles de l'entreprise.

Pour fixer le premier compte administrateur, renseignez si vous le souhaitez :

```dotenv
ADMIN_EMAIL=admin@example.ci
ADMIN_NAME="Prénom Nom"
ADMIN_PASSWORD=un-mot-de-passe-provisoire-de-12-caracteres
```

Sans `ADMIN_PASSWORD`, un mot de passe est généré et affiché une seule fois pendant le déploiement. Dans les deux cas, il devra être changé à la première connexion. Retirez `ADMIN_PASSWORD` du fichier après la première connexion.

## 4. Configurer le proxy inverse

Le proxy reçoit le HTTPS sur les ports 80 et 443 et transmet les requêtes à `127.0.0.1:8080`. Les deux configurations ci-dessous sont des exemples à adapter (nom de domaine, chemins des certificats).

### Exemple avec Caddy

Caddy obtient et renouvelle seul le certificat Let's Encrypt. Fichier `/etc/caddy/Caddyfile` :

```caddyfile
stock.example.ci {
    encode gzip
    reverse_proxy 127.0.0.1:8080
}
```

```bash
sudo systemctl reload caddy
```

### Exemple avec Nginx

Le certificat est obtenu à part, par exemple avec Certbot. Fichier `/etc/nginx/sites-available/stockflow` :

```nginx
server {
    listen 80;
    server_name stock.example.ci;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name stock.example.ci;

    ssl_certificate     /etc/letsencrypt/live/stock.example.ci/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/stock.example.ci/privkey.pem;

    # Images de produits : 2 Mo côté application, marge pour le reste du formulaire.
    client_max_body_size 8m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host  $host;
        proxy_set_header X-Forwarded-Port  $server_port;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/stockflow /etc/nginx/sites-enabled/stockflow
sudo nginx -t
sudo systemctl reload nginx
```

Le proxy doit transmettre les en-têtes `X-Forwarded-For` et `X-Forwarded-Proto` (Caddy le fait d'office) et `TRUSTED_PROXIES=*` doit figurer dans `.env.production`. Comme le service `web` n'écoute que sur `127.0.0.1`, seul ce proxy peut fournir ces en-têtes.

## 5. Premier déploiement

```bash
cd /opt/stockflow
scripts/deploy.sh
```

Le script prend en argument facultatif un nom de version. Par défaut, il utilise l'identifiant court du commit courant, qui devient l'étiquette de l'image (`stockflow-app:a0859fe` par exemple).

La première construction de l'image prend plusieurs minutes.

Notez le mot de passe administrateur s'il apparaît dans la sortie :

```text
Compte administrateur créé : admin@example.ci / xxxxxxxxxxxxxxxx (à changer à la première connexion).
```

Le script se termine par :

```text
Déploiement de la version a0859fe terminé.
```

Connectez-vous ensuite avec le compte administrateur et suivez `docs/08-user-guides/admin-guide.md`, section 1.

## 6. Ce que fait le script de déploiement

Avant toute action, `scripts/deploy.sh` vérifie que `.env.production` existe et contient `APP_ENV=production`, `APP_DEBUG=false` et une `APP_KEY`. Sinon, il s'arrête. Il s'arrête aussi à la première commande en erreur.

| Étape | Action |
| :-- | :-- |
| 1/7 Sauvegarde avant déploiement | Si le conteneur `db` tourne, lance `scripts/backup.sh ./backups` : sauvegarde de la base et des images dans `./backups` (dossier exclu de Git et des images Docker). À la première installation, rien n'est sauvegardé |
| 2/7 Construction des images | Construit `stockflow-app:<version>` (dépendances Composer sans outils de développement, code, sans `.env` ni `tests`) et `stockflow-web:<version>` (Nginx, fichiers publics, CSS et JavaScript compilés par Vite) |
| 3/7 Mise en maintenance | Si une version est en service, lance `php artisan down` dans son conteneur `app` : les utilisateurs voient la page de maintenance pendant les migrations. Démarre ensuite `db` |
| 4/7 Migrations | Applique les migrations avec la nouvelle image (`migrate --force`), puis `ReferenceDataSeeder` : crée les deux emplacements et le premier administrateur s'ils n'existent pas. Ce seeder se relance sans danger |
| 5/7 Démarrage | Recrée les conteneurs avec les nouvelles images. Les conteneurs recréés ne portent pas le fichier de maintenance : le service reprend à cette étape |
| 6/7 Caches | Met en cache la configuration, les routes et les vues |
| 7/7 Contrôles après déploiement | Lance `php artisan stock:verify`, puis vérifie que `/up` et `/login` répondent 200 sur le port `APP_PORT` de `.env.production` (jusqu'à une minute d'attente) |

Si une étape échoue après la sauvegarde, appliquez le retour arrière (section 8).

## 7. Mettre à jour

1. Prévenez les utilisateurs : l'application sera indisponible quelques minutes.
2. Notez la version en service, pour un éventuel retour arrière :

   ```bash
   cd /opt/stockflow
   git log -1 --oneline
   ```

3. Récupérez la nouvelle version :

   ```bash
   git fetch origin
   git checkout main
   git pull --ff-only
   ```

4. Lisez le `CHANGELOG.md` : une nouvelle variable d'environnement peut être à ajouter dans `.env.production`.
5. Lancez le déploiement :

   ```bash
   scripts/deploy.sh
   ```

6. Faites les vérifications de la section 9.

## 8. Revenir à la version précédente

À utiliser quand la mise à jour échoue ou que la nouvelle version pose un problème bloquant.

Toute donnée saisie entre la sauvegarde et le retour arrière sera perdue. Notez les ventes et les mouvements saisis entre-temps pour les ressaisir.

1. Repérez la sauvegarde prise par le script à l'étape 1/7 : c'est la plus récente avant l'heure du déploiement.

   ```bash
   ls -lt backups/ | head
   ```

2. Revenez au commit noté avant la mise à jour :

   ```bash
   git checkout <commit_précédent>
   ```

3. Redéployez cette version. Le script prend au passage une nouvelle sauvegarde de l'état actuel, à conserver pour analyse.

   ```bash
   scripts/deploy.sh
   ```

4. Restaurez la sauvegarde repérée à l'étape 1 (procédure détaillée dans `backup-restore.md`). Le script vide la base avant l'import : les tables créées par la version annulée disparaissent avec elle.

   ```bash
   scripts/restore.sh backups/stockflow-db_AAAA-MM-JJ_HH-MM.sql.gz backups/stockflow-files_AAAA-MM-JJ_HH-MM.tar.gz
   ```

5. Faites les vérifications de la section 9.
6. Le dépôt est maintenant sur un commit détaché. Avant la prochaine mise à jour, revenez sur la branche avec `git checkout main`.

## 9. Vérifications après déploiement

1. La dernière ligne du script est « Déploiement de la version ... terminé. ».
2. Les trois conteneurs tournent :

   ```bash
   docker compose -f compose.prod.yaml --env-file .env.production ps
   ```

3. Le stock est cohérent (déjà lancé par le script, à relancer après une restauration) :

   ```bash
   docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan stock:verify
   ```

4. Dans un navigateur, `https://stock.example.ci/login` affiche la page de connexion avec sa mise en forme et le nom de l'entreprise. Le cadenas du navigateur ne signale aucun contenu non sécurisé.
5. Connectez-vous : le tableau de bord s'affiche, le menu correspond au profil.
6. La recherche de la barre supérieure renvoie des produits.
7. S'il existe déjà des ventes, un bon de sortie s'ouvre en impression avec l'en-tête de l'entreprise.
8. Aucune erreur récente dans les journaux :

   ```bash
   docker compose -f compose.prod.yaml --env-file .env.production logs --tail=100 app web
   docker compose -f compose.prod.yaml --env-file .env.production exec app sh -c 'tail -n 50 storage/logs/laravel-*.log'
   ```

Ne créez pas de vente ou de mouvement de test en production : il resterait dans l'historique, qui ne se supprime pas.

## 10. Ce qu'il ne faut jamais faire

- Charger le jeu de démonstration (`DemoSeeder`) ou lancer `php artisan migrate:fresh` : la base serait vidée et remplie de comptes au mot de passe public. Le seeder refuse de s'exécuter quand `APP_ENV=production`, mais `migrate:fresh` vide la base quand même.
- Mettre `APP_DEBUG=true` : les pages d'erreur afficheraient le code, la configuration et les secrets.
- Exposer MySQL : n'ajoutez pas de section `ports` au service `db`. Pour consulter la base, passez par `docker compose ... exec db mysql`.
- Publier le port 8080 au-delà de `127.0.0.1`, ou servir l'application en HTTP simple.
- Lancer `docker compose ... down -v` : l'option `-v` supprime les volumes, donc la base et les images.
- Modifier une quantité de stock directement en SQL ou par `php artisan tinker` : seul le moteur de stock garde l'historique cohérent. Une correction passe par un ajustement justifié dans l'application.
- Versionner `.env.production` ou l'envoyer par messagerie.
- Changer `APP_KEY` sur une installation en service.

## 11. Limites connues

- L'application n'envoie pas d'e-mail : un mot de passe oublié est réinitialisé par l'administrateur (D-010).
- La maintenance couvre les migrations, pas la construction des images : celle-ci se fait pendant que l'ancienne version sert encore les utilisateurs, ce qui raccourcit l'interruption.
- Le retour arrière perd les saisies faites entre la sauvegarde et la restauration (section 8).
