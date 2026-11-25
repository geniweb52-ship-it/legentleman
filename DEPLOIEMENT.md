# Déploiement sur le VPS (Ubuntu / Debian)

Serveur : `54.37.137.215`. Remplacez `legentleman.sn` par votre nom de domaine, et les valeurs entre `< >`.
Le domaine doit pointer vers l'IP du VPS (enregistrements DNS `A` pour `legentleman.sn` et `www.legentleman.sn`)
avant l'étape 6 (certificat https).

## 1. Connexion et paquets

```bash
ssh <utilisateur>@54.37.137.215

sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server git unzip \
  php libapache2-mod-php php-mysql php-gd php-curl php-mbstring php-exif php-xml
sudo a2enmod rewrite headers
php -v            # PHP 8.1 minimum
php -m | grep -Ei 'pdo_mysql|gd|curl|mbstring|exif'
```

## 2. Code du site

```bash
sudo git clone https://github.com/geniweb52-ship-it/legentleman.git /var/www/legentleman
# Dépôt privé : utiliser un jeton GitHub (Settings > Developer settings > Personal access tokens)
# ou une clé SSH de déploiement, puis : git clone git@github.com:geniweb52-ship-it/legentleman.git ...

cd /var/www/legentleman
sudo chown -R www-data:www-data assets/uploads
sudo find assets/uploads -type d -exec chmod 755 {} \;
```

Les fichiers `assets/app.css` et `assets/admin.css` sont déjà compilés dans le dépôt : Node.js n'est pas nécessaire
sur le serveur.

## 3. Base de données

```bash
sudo mysql_secure_installation          # répondre Oui partout
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

Pour reprendre les produits et commandes de l'installation locale à la place, exporter la base depuis WAMP
(phpMyAdmin > Exporter, ou `mysqldump -u root le_gentleman > le_gentleman.sql`), l'envoyer avec
`scp le_gentleman.sql <utilisateur>@54.37.137.215:~` puis `sudo mysql le_gentleman < ~/le_gentleman.sql`.
Les chemins d'images `/gentleman/...` sont recalculés automatiquement.

## 4. Configuration

```bash
cd /var/www/legentleman
sudo cp config.example.php config.php
php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"     # copier la clé affichée
sudo nano config.php
```

À modifier dans `config.php` :

```php
const DB_USER = 'gentleman';
const DB_PASS = '<MOT_DE_PASSE_BDD_LONG>';
const BASE_URL = '';                              // site à la racine du domaine
const SITE_URL = 'https://legentleman.sn';        // obligatoire en production (liens de facture, Wave)
const APP_SECRET = '<CLÉ_GÉNÉRÉE_CI-DESSUS>';     // ne plus jamais la changer ensuite
```

```bash
sudo chown root:www-data config.php && sudo chmod 640 config.php

# Compte administrateur : remplace le mot de passe par défaut de schema.sql (10 caractères minimum).
sudo -u www-data php sql/reset-admin.php admin@legentleman.sn '<MOT_DE_PASSE_ADMIN>'
```

## 5. Apache

```bash
sudo nano /etc/apache2/sites-available/legentleman.conf
```

```apache
<VirtualHost *:80>
    ServerName legentleman.sn
    ServerAlias www.legentleman.sn
    DocumentRoot /var/www/legentleman

    <Directory /var/www/legentleman>
        AllowOverride All
        Require all granted
    </Directory>

    # Le dossier .git ne doit jamais être servi.
    RedirectMatch 404 /\.git

    ErrorLog ${APACHE_LOG_DIR}/legentleman-error.log
    CustomLog ${APACHE_LOG_DIR}/legentleman-access.log combined
</VirtualHost>
```

```bash
# Taille des photos envoyées depuis l'admin (15 Mo maximum côté site).
PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
printf "upload_max_filesize = 16M\npost_max_size = 40M\nmemory_limit = 512M\nexpose_php = Off\ndisplay_errors = Off\n" \
  | sudo tee /etc/php/$PHPV/apache2/conf.d/99-legentleman.ini

sudo a2ensite legentleman && sudo a2dissite 000-default
sudo apache2ctl configtest && sudo systemctl reload apache2
```

## 6. Pare-feu et https

```bash
sudo ufw allow OpenSSH && sudo ufw allow 'Apache Full' && sudo ufw enable
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d legentleman.sn -d www.legentleman.sn --redirect
```

## 7. Vérifications

```bash
curl -I https://legentleman.sn/                       # 200
curl -I https://legentleman.sn/config.php             # 403
curl -I https://legentleman.sn/sql/schema.sql         # 403
curl -I https://legentleman.sn/.git/config            # 404
sudo tail -n 50 /var/log/apache2/legentleman-error.log
```

Puis dans le navigateur : passer une commande test, ouvrir `/admin/`, envoyer une photo produit.

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
