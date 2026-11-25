# Déploiement sur le VPS (Ubuntu / Debian, Caddy existant)

Serveur : `54.37.137.215` · domaine : `legentleman.shop`.
Caddy sert déjà d'autres projets sur ce VPS (ekolys, lokky) : on **ajoute** un bloc au Caddyfile, sans toucher aux autres,
et on n'installe **pas** Apache (il entrerait en conflit avec Caddy sur les ports 80/443).

⚠️ Caddy ne lit pas les fichiers `.htaccess` : les protections (`.git`, `config.php`, `sql/`, `includes/`,
scripts dans `assets/uploads`…) sont reprises dans le bloc Caddy de l'étape 5. Ne pas les retirer.

Prérequis DNS (chez Hostinger) : uniquement `A @ → 54.37.137.215` et `CNAME www → legentleman.shop`.
Vérifier avec `dig +short legentleman.shop @8.8.8.8` : une seule ligne, `54.37.137.215`.

## 0. Comment Caddy tourne sur ce VPS ?

```bash
systemctl status caddy --no-pager | head -3      # « active (running) » -> Caddy installé sur le système : suivre ce guide
docker ps --format '{{.Names}} {{.Image}}' | grep -i caddy   # une ligne -> Caddy est dans Docker : voir l'annexe en bas
```

## 1. PHP-FPM et MySQL

```bash
ssh <utilisateur>@54.37.137.215
sudo apt update
sudo apt install -y git mysql-server \
  php-fpm php-mysql php-gd php-curl php-mbstring php-xml php-exif
# (si MySQL tourne déjà sur le VPS pour un autre projet, apt le laisse en place)

PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'); echo "PHP $PHPV"    # 8.1 minimum
php -m | grep -Ei 'pdo_mysql|gd|curl|mbstring|exif'
ls /run/php/                       # repérer le socket, ex. php8.3-fpm.sock
```

Réglages PHP (photos envoyées depuis l'admin, 15 Mo maximum côté site) :

```bash
printf "upload_max_filesize = 16M\npost_max_size = 40M\nmemory_limit = 512M\nexpose_php = Off\ndisplay_errors = Off\nlog_errors = On\n" \
  | sudo tee /etc/php/$PHPV/fpm/conf.d/99-legentleman.ini
sudo systemctl restart php$PHPV-fpm
```

## 2. Code du site

```bash
sudo git clone https://github.com/geniweb52-ship-it/legentleman.git /var/www/legentleman
# Dépôt privé : jeton GitHub (Settings > Developer settings > Personal access tokens) ou clé SSH de déploiement.

cd /var/www/legentleman
sudo chown -R www-data:www-data assets/uploads
```

`assets/app.css` et `assets/admin.css` sont déjà compilés dans le dépôt : Node.js n'est pas nécessaire.

## 3. Base de données

```bash
sudo mysql
```

```sql
CREATE DATABASE le_gentleman CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gentleman'@'localhost' IDENTIFIED BY '<MOT_DE_PASSE_BDD_LONG>';
GRANT ALL PRIVILEGES ON le_gentleman.* TO 'gentleman'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Nouvelle installation : schema.sql seul (il contient déjà toutes les migrations).
sudo mysql le_gentleman < /var/www/legentleman/sql/schema.sql
```

Pour reprendre les produits et commandes de l'installation locale : exporter la base depuis WAMP
(phpMyAdmin > Exporter), l'envoyer avec `scp le_gentleman.sql <utilisateur>@54.37.137.215:~`
puis `sudo mysql le_gentleman < ~/le_gentleman.sql` (à la place de schema.sql).
Les chemins d'images `/gentleman/...` sont recalculés automatiquement.

## 4. Configuration

```bash
cd /var/www/legentleman
sudo cp config.example.php config.php
php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"     # copier la clé affichée
sudo nano config.php
```

```php
const DB_USER = 'gentleman';
const DB_PASS = '<MOT_DE_PASSE_BDD_LONG>';
const BASE_URL = '';                               // site à la racine du domaine
const SITE_URL = 'https://legentleman.shop';       // obligatoire en production (liens de facture, Wave)
const APP_SECRET = '<CLÉ_GÉNÉRÉE_CI-DESSUS>';      // ne plus jamais la changer ensuite
const SHOP_TIKTOK = 'https://www.tiktok.com/@legentleman395';  // lien TikTok du pied de page (si absent : lien masqué)
```

```bash
sudo chown root:www-data config.php && sudo chmod 640 config.php

# Compte administrateur : remplace le mot de passe par défaut de schema.sql (10 caractères minimum).
sudo -u www-data php sql/reset-admin.php admin@legentleman.sn '<MOT_DE_PASSE_ADMIN>'
```

## 5. Caddy

```bash
sudo cp /etc/caddy/Caddyfile /etc/caddy/Caddyfile.bak-$(date +%F)     # sauvegarde avant modification
sudo nano /etc/caddy/Caddyfile
```

Ajouter à la fin (s'il existe déjà un bloc `legentleman.shop`, le **remplacer** par celui-ci ;
ne pas toucher aux blocs d'ekolys et lokky). Adapter `php8.3-fpm.sock` au socket vu à l'étape 1.

```caddy
www.legentleman.shop {
	redir https://legentleman.shop{uri} permanent
}

legentleman.shop {
	root * /var/www/legentleman
	encode zstd gzip

	# Équivalent des .htaccess : fichiers internes jamais servis.
	@interne {
		path /.git /.git/* *.gitignore *.htaccess /.env*
		path /sql /sql/* /includes /includes/* /node_modules /node_modules/* /assets/src /assets/src/*
		path /config.php /config.example.php /package.json /package-lock.json /tailwind.config.js /composer.*
		path *.sql *.md *.log *.bak
	}
	respond @interne 404

	# Dossier des photos envoyées : aucun script exécutable.
	@scripts_uploads path_regexp (?i)^/assets/uploads/.*\.(php|phtml|phar|pl|py|cgi|sh)$
	respond @scripts_uploads 404

	request_body {
		max_size 40MB
	}

	header {
		-Server
		Strict-Transport-Security "max-age=31536000"
	}

	# Référencement Google : robots.txt et sitemap.xml sont générés en PHP (équivalent des règles du .htaccess).
	rewrite /robots.txt /robots.php
	rewrite /sitemap.xml /sitemap.php

	php_fastcgi unix//run/php/php8.3-fpm.sock {
		try_files {path} {path}/index.php =404
	}
	file_server

	log {
		output file /var/log/caddy/legentleman.log
	}
}
```

```bash
sudo caddy fmt --overwrite /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile      # doit finir par « Valid configuration »
sudo systemctl reload caddy                             # reload : ekolys et lokky ne sont pas coupés
```

Caddy obtient le certificat https tout seul (Let's Encrypt) dès que le DNS pointe uniquement vers le VPS.
Suivre l'obtention : `sudo journalctl -u caddy -f` (chercher « certificate obtained successfully »).

## 6. Vérifications

```bash
curl -I https://legentleman.shop/                      # 200
curl -I https://www.legentleman.shop/                  # 301 vers https://legentleman.shop/
curl -I https://legentleman.shop/admin/                # 302 vers login.php
curl -I https://legentleman.shop/config.php            # 404
curl -I https://legentleman.shop/sql/schema.sql        # 404
curl -I https://legentleman.shop/.git/config           # 404
curl -I https://legentleman.shop/includes/db.php       # 404
curl -s https://legentleman.shop/robots.txt            # liste « Disallow » + ligne « Sitemap: https://legentleman.shop/sitemap.xml »
curl -s https://legentleman.shop/sitemap.xml | head -4 # XML commençant par <urlset>, adresses en https://legentleman.shop/
sudo tail -n 50 /var/log/php$PHPV-fpm.log
```

Puis dans le navigateur : passer une commande test, ouvrir `/admin/`, envoyer une photo produit.

## 7. Google (après la mise en ligne)

1. [Google Search Console](https://search.google.com/search-console) : ajouter la propriété **Domaine** `legentleman.shop`
   et la valider avec l'enregistrement TXT fourni par Google (à ajouter dans la zone DNS chez Hostinger).
2. Search Console → **Sitemaps** : envoyer `sitemap.xml`.
3. Search Console → **Inspection de l'URL** : `https://legentleman.shop/` → « Demander l'indexation ».

Le titre, la description, l'icône (48/96/192 px), les données structurées (boutique, produits avec prix en FCFA)
et les balises de partage (WhatsApp, Facebook) sont déjà en place dans le code.

## Mises à jour du site

```bash
cd /var/www/legentleman && sudo git pull
```

`config.php` et les photos envoyées depuis l'admin ne sont pas touchés par `git pull`.

## Sauvegarde quotidienne de la base (conseillé)

```bash
sudo mkdir -p /var/backups/legentleman
echo '30 3 * * * root mysqldump le_gentleman | gzip > /var/backups/legentleman/db-$(date +\%F).sql.gz && find /var/backups/legentleman -mtime +14 -delete' \
  | sudo tee /etc/cron.d/legentleman-backup
```

## Annexe : Caddy dans Docker

Si l'étape 0 montre Caddy dans un conteneur, il ne voit ni `/var/www/legentleman` ni le socket PHP de la machine.
Dans le `docker-compose.yml` qui lance Caddy, ajouter ces volumes au service Caddy puis `docker compose up -d` :

```yaml
    volumes:
      - /var/www/legentleman:/var/www/legentleman:ro
      - /run/php:/run/php
```

Le bloc Caddy de l'étape 5 reste identique (à mettre dans le Caddyfile monté dans le conteneur), et le rechargement
se fait avec `docker exec <conteneur_caddy> caddy reload --config /etc/caddy/Caddyfile`.
