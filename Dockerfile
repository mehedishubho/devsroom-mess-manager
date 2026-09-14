# syntax=docker/dockerfile:1
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.4-fpm-alpine
RUN apk add --no-cache nginx mariadb-client unzip supervisor \
 && apk add --no-cache --virtual .build-deps libzip-dev icu-dev freetype-dev libjpeg-turbo-dev libpng-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath intl opcache \
 && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN { \
      echo "memory_limit=512M"; \
      echo "upload_max_filesize=20M"; \
      echo "post_max_size=20M"; \
      echo "opcache.enable=1"; \
      echo "opcache.validate_timestamps=0"; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

RUN mkdir -p storage/app/public storage/app/backups \
      storage/framework/cache/data storage/framework/sessions \
      storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY <<'EOF' /etc/nginx/http.d/default.conf
server {
    listen 80;
    index index.php;
    root /var/www/html/public;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
EOF

COPY <<'EOF' /etc/supervisord.conf
[supervisord]
nodaemon=true
logfile=/dev/null
logfile_maxbytes=0
[program:php-fpm]
command=php-fpm
[program:nginx]
command=nginx -g "daemon off;"
EOF

COPY <<'EOF' /entrypoint.sh
#!/bin/sh
set -e
mkdir -p storage/app/public storage/app/backups \
         storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
  php artisan storage:link || true
  if [ "${OPTIMIZE:-true}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
  fi
fi
exec "$@"
EOF
RUN chmod +x /entrypoint.sh

ENV RUN_MIGRATIONS=false OPTIMIZE=true
EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
