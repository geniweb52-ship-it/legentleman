# Déploiement — LE GENTLEMAN sur le VPS

Site : **https://legentleman.shop** · VPS : `54.37.137.215` (Ubuntu, utilisateur `ubuntu`) · code : branche `main`.

## Comment c'est installé

Le VPS héberge aussi **lokky** et **ekolys**. Le site ne touche pas à leurs conteneurs : il tourne dans les siens,
et seul le Caddy commun le relie à Internet.

```
Internet ─► lokky-caddy-1 (ports 80/443, https automatique)
               │  /opt/caddy-sites/legentleman.caddy
               │  réseau Docker « legentleman_edge »
               ▼
            legentleman-web (PHP 8.3 + Apache, lit les .htaccess) ──► legentleman-db (MySQL 8)
```

| Élément | Emplacement sur le VPS | Modèle dans le dépôt |
|---|---|---|
| Code du site (clone git de `main`) | `/opt/legentleman/app` | — |
| Conteneurs PHP + MySQL | `/opt/legentleman/docker-compose.yml`, `Dockerfile` | `deploy/` |
| Secrets MySQL (générés sur le serveur) | `/opt/legentleman/.env` (lisible par `ubuntu` seul) | — |
| Configuration du site | `/opt/legentleman/app/config.php` (jamais dans git) | `config.example.php` |
| Bloc Caddy | `/opt/caddy-sites/legentleman.caddy` | `deploy/legentleman.caddy` |
| Mise à jour automatique | `/opt/legentleman/auto-update.sh` + crontab `ubuntu` | `deploy/auto-update.sh` |
| Base de données | volume Docker `legentleman_db-data` | `sql/schema.sql` |

Les protections (`.git`, `config.php`, `sql/`, `includes/`, `deploy/`, scripts dans `assets/uploads`…) viennent des
fichiers `.htaccess`, appliqués par Apache dans le conteneur. `robots.txt` et `sitemap.xml` passent aussi par le `.htaccess`.

## Mettre le site à jour

**Il suffit de pousser sur `main`** : le VPS vérifie GitHub toutes les 2 minutes et récupère les nouveaux commits
(PHP, CSS, JS, images : visibles tout de suite, sans redémarrage).

```bash
ssh 54.37.137.215 "tail /opt/legentleman/auto-update.log"          # historique des mises à jour
ssh 54.37.137.215 "/opt/legentleman/auto-update.sh"                 # forcer une mise à jour maintenant
```

- Si la mise à jour est impossible (historique réécrit par un `push --force`, fichier modifié sur le serveur),
  le site reste sur la version précédente et l'erreur est notée **une fois** dans le journal.
- **Base de données** : jamais modifiée automatiquement. Si un commit change `sql/`, le journal le signale ;
  lancer alors la migration à la main (voir « Base de données »).
- **Fichiers de `deploy/`** : ce sont des modèles. Après une modification, les recopier sur le VPS
  (`/opt/legentleman/` ou `/opt/caddy-sites/`) puis appliquer (voir plus bas).

## Commandes utiles

```bash
ssh 54.37.137.215
cd /opt/legentleman

docker compose ps                                 # état des conteneurs
docker compose logs --tail 50 web                 # erreurs PHP / Apache
docker compose restart web                        # redémarrer le site
docker compose up -d --build                      # après une modification de Dockerfile / docker-compose.yml

# Mot de passe admin oublié (10 caractères minimum)
docker compose exec web php sql/reset-admin.php admin@legentleman.sn 'NouveauMotDePasse'

# Console MySQL
docker compose exec db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" le_gentleman'
```

## Base de données

```bash
cd /opt/legentleman

# Sauvegarde
mkdir -p backups
docker compose exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction le_gentleman' \
  > backups/le_gentleman-$(date +%F-%H%M).sql

# Lancer une migration (fichier envoyé sur le serveur ou présent dans app/sql/)
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 le_gentleman' \
  < app/sql/migration-XXXX.sql
```

Sauvegarde automatique chaque nuit, 14 jours conservés (à installer une fois) :

```bash
( crontab -l; echo '30 3 * * * cd /opt/legentleman && docker compose exec -T db sh -c '"'"'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction le_gentleman'"'"' | gzip > backups/auto-$(date +\%F).sql.gz && find backups -name "auto-*" -mtime +14 -delete' ) | crontab -
```

**Importer un export phpMyAdmin** (ex. depuis WAMP) : l'export recrée toutes les tables, y compris `admins`.
Faire d'abord une sauvegarde, puis encadrer le fichier par `SET FOREIGN_KEY_CHECKS=0;` / `SET FOREIGN_KEY_CHECKS=1;`
(sinon l'import s'arrête en cours de route sur une erreur de clé étrangère) et retirer la table `admins`
pour garder le compte en ligne.

## Caddy (partagé avec lokky et ekolys)

Le conteneur `lokky-caddy-1` appartient au projet lokky (`/opt/lokky`). Il lit tous les fichiers `/opt/caddy-sites/*.caddy`
et il est relié au réseau `legentleman_edge` : ce réseau est déclaré dans `deploy/docker-compose.yml` **du dépôt lokky**,
sinon un redéploiement de lokky recréerait Caddy sans lui (legentleman.shop afficherait une erreur 502).

Modifier le bloc Caddy du site sans couper les autres sites :

```bash
nano /opt/caddy-sites/legentleman.caddy
docker exec lokky-caddy-1 caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile   # « Valid configuration »
docker exec lokky-caddy-1 caddy reload   --config /etc/caddy/Caddyfile --adapter caddyfile
```

Les certificats https de `legentleman.shop` et `www.legentleman.shop` sont obtenus et renouvelés par Caddy.

## DNS (Hostinger)

Zone de `legentleman.shop` : uniquement `A @ → 54.37.137.215` et `CNAME www → legentleman.shop` (TTL 300 conseillé).
Vérification : `nslookup legentleman.shop 8.8.8.8` doit répondre `54.37.137.215` seulement.

## Réinstaller de zéro (nouveau serveur)

Prérequis : Docker, et un Caddy en conteneur qui importe `/opt/caddy-sites/*.caddy` (ici celui de lokky).

```bash
sudo mkdir -p /opt/legentleman && sudo chown ubuntu:ubuntu /opt/legentleman && cd /opt/legentleman
git clone -q https://github.com/geniweb52-ship-it/legentleman.git app
cp app/deploy/Dockerfile app/deploy/docker-compose.yml app/deploy/setup.sh app/deploy/auto-update.sh .
sh setup.sh                     # secrets, config.php, droits des photos, réseau legentleman_edge
docker compose up -d --build    # la base est créée à partir de sql/schema.sql au premier démarrage

# Compte admin (mot de passe aléatoire, enregistré dans un fichier lisible par ubuntu seul)
umask 077; docker compose exec -T web php sql/reset-admin.php admin@legentleman.sn "$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)" > admin-identifiants.txt

# Caddy
cp app/deploy/legentleman.caddy /opt/caddy-sites/
docker network connect legentleman_edge lokky-caddy-1
docker exec lokky-caddy-1 caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
docker exec lokky-caddy-1 caddy reload   --config /etc/caddy/Caddyfile --adapter caddyfile

# Mise à jour automatique
( crontab -l 2>/dev/null; echo '*/2 * * * * /bin/sh /opt/legentleman/auto-update.sh >> /opt/legentleman/auto-update.log 2>&1' ) | crontab -
```

Après l'installation : se connecter à `/admin/` avec `admin-identifiants.txt`, changer le mot de passe
(Admin → Paramètres) puis supprimer ce fichier.

## Vérifications

```bash
curl -I https://legentleman.shop/                      # 200
curl -I https://www.legentleman.shop/                  # 301 vers https://legentleman.shop/
curl -I https://legentleman.shop/admin/                # 302 vers login.php
curl -I https://legentleman.shop/config.php            # 403
curl -I https://legentleman.shop/sql/schema.sql        # 403
curl -I https://legentleman.shop/.git/config           # 403
curl -I https://legentleman.shop/deploy/setup.sh       # 403
curl -s https://legentleman.shop/robots.txt            # « Disallow » + « Sitemap: https://legentleman.shop/sitemap.xml »
curl -s https://legentleman.shop/sitemap.xml | head -4 # XML <urlset>, adresses en https://legentleman.shop/
```

## Google (après la mise en ligne)

1. [Google Search Console](https://search.google.com/search-console) : ajouter la propriété **Domaine** `legentleman.shop`
   et la valider avec l'enregistrement TXT fourni par Google (à ajouter dans la zone DNS chez Hostinger).
2. Search Console → **Sitemaps** : envoyer `sitemap.xml`.
3. Search Console → **Inspection de l'URL** : `https://legentleman.shop/` → « Demander l'indexation ».

Le titre, la description, l'icône (48/96/192 px), les données structurées (boutique, produits avec prix en FCFA)
et les balises de partage (WhatsApp, Facebook) sont déjà en place dans le code.
