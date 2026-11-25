#!/bin/sh
# Installation initiale (une seule fois). Les secrets sont générés ici et restent sur le serveur.
set -eu
cd /opt/legentleman
[ -d app/.git ] || git clone -q https://github.com/geniweb52-ship-it/legentleman.git app
if [ ! -f .env ]; then
  umask 077
  printf 'MYSQL_ROOT_PASSWORD=%s\nMYSQL_USER=gentleman\nMYSQL_PASSWORD=%s\n' "$(openssl rand -hex 24)" "$(openssl rand -hex 24)" > .env
fi
. ./.env
if [ ! -f app/config.php ]; then
  sed -e "s|const DB_HOST = '127.0.0.1';|const DB_HOST = 'db';|" \
      -e "s|const DB_USER = 'root';|const DB_USER = 'gentleman';|" \
      -e "s|const DB_PASS = '';|const DB_PASS = '$MYSQL_PASSWORD';|" \
      -e "s|const BASE_URL = '/gentleman';|const BASE_URL = '';|" \
      -e "s|const SITE_URL = '';|const SITE_URL = 'https://legentleman.shop';|" \
      -e "s|A_REMPLACER_par_une_longue_cle_aleatoire|$(openssl rand -hex 24)|" \
      app/config.example.php > app/config.php
fi
sudo chown 33:33 app/config.php && sudo chmod 640 app/config.php
# Photos envoyées depuis l'admin : écriture pour le site (www-data, uid 33) et pour git pull (ubuntu).
sudo chown -R "$(id -un)":33 app/assets/uploads
sudo find app/assets/uploads -type d -exec chmod 2775 {} \;
sudo find app/assets/uploads -type f -exec chmod 664 {} \;
docker network inspect legentleman_edge >/dev/null 2>&1 || docker network create legentleman_edge >/dev/null
