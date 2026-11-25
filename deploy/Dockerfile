FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libjpeg-dev libpng-dev libwebp-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install pdo_mysql gd exif opcache \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers remoteip \
 && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 && printf 'ServerTokens Prod\nServerSignature Off\n# Derrière Caddy : https et IP réelle du visiteur\nSetEnvIf X-Forwarded-Proto "^https$" HTTPS=on\nRemoteIPHeader X-Forwarded-For\nRemoteIPInternalProxy 172.16.0.0/12\n' > /etc/apache2/conf-enabled/legentleman.conf \
 && printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\nupload_max_filesize=16M\npost_max_size=40M\nmemory_limit=512M\ndate.timezone=Africa/Dakar\nsession.cookie_httponly=1\n' > /usr/local/etc/php/conf.d/legentleman.ini
