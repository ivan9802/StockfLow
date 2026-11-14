# Sauvegarde et restauration de StockFlow

Ce document décrit la sauvegarde quotidienne de StockFlow en production, sa conservation, sa copie hors site, le test mensuel et la restauration. Il suppose une installation faite selon `deployment.md`, dans `/opt/stockflow`.

## 1. Ce qui est sauvegardé

`scripts/backup.sh` produit deux fichiers horodatés :

| Fichier | Contenu |
| :-- | :-- |
| `stockflow-db_AAAA-MM-JJ_HH-MM.sql.gz` | Copie complète de la base MySQL : catalogue, stock, mouvements, documents, comptes, journal d'audit, alertes, sessions |
| `stockflow-files_AAAA-MM-JJ_HH-MM.tar.gz` | Le dossier `storage/app` du conteneur `app` : images des produits |

La copie de la base est faite avec `mysqldump --single-transaction` : elle est cohérente et ne bloque pas les ventes en cours. Les procédures stockées et les déclencheurs sont inclus.

Le script contrôle la sauvegarde avant de rendre la main : l'archive doit être un fichier gzip valide et la copie de la base doit se terminer par la ligne « Dump completed ». Sinon il affiche « ERREUR : la sauvegarde de la base est incomplète. » et sort en erreur.

Ce qui n'est pas sauvegardé par le script :

- `.env.production` : à conserver à part, en lieu sûr (section 4) ;
- les journaux de l'application (`storage/logs`) ;
- les images Docker, que le script de déploiement reconstruit depuis le code.

Le script lit `compose.prod.yaml` et `.env.production` dans le dossier courant. Il se lance donc depuis `/opt/stockflow`, et les conteneurs `app` et `db` doivent tourner.

## 2. Sauvegarde manuelle

```bash
cd /opt/stockflow
scripts/backup.sh /var/backups/stockflow
```

Sans argument, le dossier de destination est `./backups`, dans le dossier du dépôt. C'est là que le script de déploiement range la sauvegarde prise avant chaque mise à jour. Pour les sauvegardes planifiées, préférez un dossier hors du dépôt, comme `/var/backups/stockflow` : `./backups` n'est pas exclu de la construction de l'image (voir « Limites connues » dans `deployment.md`).

Préparez le dossier une fois :

```bash
sudo mkdir -p /var/backups/stockflow
sudo chmod 700 /var/backups/stockflow
```

Le script se termine par :

```text
Sauvegarde terminée : /var/backups/stockflow/stockflow-db_2026-11-14_02-30.sql.gz et /var/backups/stockflow/stockflow-files_2026-11-14_02-30.tar.gz
```

## 3. Planification quotidienne

Ajoutez une tâche dans la crontab de `root`, qui peut lancer `docker` :

```bash
sudo crontab -e
```

Exemple de ligne, pour une sauvegarde chaque nuit à 2 h 30, heure du serveur :

```cron
30 2 * * * cd /opt/stockflow && scripts/backup.sh /var/backups/stockflow >> /var/log/stockflow-backup.log 2>&1
```

Le `cd` est indispensable : le script cherche ses fichiers de configuration dans le dossier courant.

Le lendemain, vérifiez que la tâche a fonctionné :

```bash
ls -lt /var/backups/stockflow | head
tail -n 20 /var/log/stockflow-backup.log
```

Une sauvegarde de la base qui pèse quelques centaines d'octets est vide : la tâche a échoué.

## 4. Conservation

À chaque exécution, le script supprime dans le dossier de destination les fichiers `stockflow-*` de plus de 30 jours. Pour changer cette durée, définissez `RETENTION_DAYS` :

```cron
30 2 * * * cd /opt/stockflow && RETENTION_DAYS=60 scripts/backup.sh /var/backups/stockflow >> /var/log/stockflow-backup.log 2>&1
```

Les sauvegardes prises par le script de déploiement dans `./backups` suivent la même règle, appliquée seulement lors d'un déploiement.

Gardez aussi une copie de `.env.production` hors du serveur, dans un coffre de mots de passe ou un support chiffré. Il contient les mots de passe MySQL et la clé de l'application. Sans lui, une restauration sur un nouveau serveur oblige à recréer la configuration, et tous les utilisateurs devront se reconnecter.

## 5. Copie hors site

Une sauvegarde qui reste sur le serveur disparaît avec lui (panne de disque, incendie, piratage). Copiez chaque nuit les sauvegardes sur une autre machine, dans un autre lieu.

Exemple avec `rsync` vers un second serveur joignable en SSH par clé, une heure après la sauvegarde. À adapter :

```cron
30 3 * * * rsync -a /var/backups/stockflow/ sauvegarde@serveur-distant:/srv/sauvegardes/stockflow/ >> /var/log/stockflow-backup.log 2>&1
```

Sans l'option `--delete`, la copie distante garde les fichiers supprimés localement par la rotation : prévoyez une rotation propre sur la machine distante. Un stockage objet (par exemple avec `rclone`) convient aussi.

Les sauvegardes contiennent des données personnelles et les empreintes des mots de passe. Limitez l'accès au dossier distant, et chiffrez-le si la machine n'est pas sous votre contrôle.

## 6. Test de restauration mensuel

Une sauvegarde qui n'a jamais été restaurée n'est pas une sauvegarde vérifiée. Une fois par mois, restaurez la dernière sauvegarde hors site sur une machine de test, jamais sur la production.

1. Sur la machine de test, installez StockFlow selon `deployment.md`, sections 1 à 5, avec son propre `.env.production`. Le premier déploiement crée une base vide.
2. Récupérez les deux fichiers de la dernière sauvegarde depuis la copie hors site.
3. Vérifiez leur intégrité :

   ```bash
   gzip -t stockflow-db_AAAA-MM-JJ_HH-MM.sql.gz
   gzip -t stockflow-files_AAAA-MM-JJ_HH-MM.tar.gz
   ```

4. Restaurez-les (section 7, étapes 6 à 8).
5. Comparez la ligne affichée par `stock:verify` (« N mouvements rejoués sur M lignes de stock ») avec celle de la production le même jour :

   ```bash
   docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan stock:verify
   ```

6. Connectez-vous avec un compte réel. Ouvrez la dernière vente de la veille et une fiche produit avec image.
7. Notez la date, le nom des fichiers testés, le résultat et la durée de la restauration dans le registre d'exploitation.
8. Supprimez la machine de test ou sa base : elle contient des données réelles.

## 7. Restaurer pas à pas

La restauration remplace la base de production par le contenu de la sauvegarde. Tout ce qui a été saisi après l'heure de la sauvegarde est perdu.

1. Choisissez la sauvegarde : la plus récente avant l'incident.

   ```bash
   ls -lt /var/backups/stockflow /opt/stockflow/backups | head -20
   ```

2. Si elle vient de la copie hors site, copiez les deux fichiers sur le serveur.
3. Vérifiez leur intégrité :

   ```bash
   gzip -t /var/backups/stockflow/stockflow-db_AAAA-MM-JJ_HH-MM.sql.gz
   gzip -t /var/backups/stockflow/stockflow-files_AAAA-MM-JJ_HH-MM.tar.gz
   ```

4. Prévenez les utilisateurs : l'application sera en maintenance pendant l'opération. Faites noter les opérations saisies depuis l'heure de la sauvegarde, pour les ressaisir.
5. Vérifiez que les conteneurs tournent. Sur un serveur neuf, faites d'abord l'installation complète de `deployment.md` (sections 1 à 5).

   ```bash
   cd /opt/stockflow
   docker compose -f compose.prod.yaml --env-file .env.production ps
   ```

6. Si la base actuelle contient encore des données, sauvegardez-la d'abord, pour garder une trace de l'état avant restauration :

   ```bash
   scripts/backup.sh ./backups
   ```

7. Lancez la restauration, avec la base puis, facultativement, les images :

   ```bash
   scripts/restore.sh /var/backups/stockflow/stockflow-db_AAAA-MM-JJ_HH-MM.sql.gz /var/backups/stockflow/stockflow-files_AAAA-MM-JJ_HH-MM.tar.gz
   ```

8. Le script demande une confirmation. Tapez `RESTAURER` en majuscules, puis Entrée. Toute autre réponse abandonne sans rien modifier.

Le script enchaîne alors :

1. mise en maintenance de l'application ;
2. import de la base ;
3. extraction des images dans `storage/app`, si l'archive est fournie ;
4. contrôle `php artisan stock:verify` ;
5. sortie du mode maintenance.

Il se termine par « Restauration terminée. ».

Si `stock:verify` signale un écart, le script s'arrête et l'application reste en maintenance. Ne la rouvrez pas avant d'avoir compris l'écart : essayez la sauvegarde précédente, ou prévenez le responsable technique. Pour rouvrir l'application volontairement :

```bash
docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan up
```

À savoir :

- L'extraction des images ajoute et remplace des fichiers ; elle ne supprime pas les images ajoutées après la sauvegarde.
- La base est vidée puis recréée avant l'import : les tables créées par une version plus récente disparaissent avec elle.

## 8. Contrôles après restauration

1. Le script s'est terminé par « Restauration terminée. ».
2. `stock:verify` affiche « Stock cohérent : ... aucun écart. ».
3. La page de connexion s'affiche et un compte réel se connecte.
4. La dernière vente visible dans « Historique des ventes » correspond à l'heure de la sauvegarde.
5. Les images des produits s'affichent sur leur fiche.
6. L'administrateur consigne la restauration (date, sauvegarde utilisée, motif) dans le registre d'exploitation, et les opérations perdues sont ressaisies.
