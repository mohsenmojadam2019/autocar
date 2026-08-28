FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json vite.config.js ./
RUN npm install --no-audit --no-fund
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.4-fpm-alpine
RUN apk add --no-cache nginx supervisor icu-dev libzip-dev libpng-dev freetype-dev jpeg-dev oniguruma-dev mysql-client curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql intl zip gd opcache bcmath pcntl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
EXPOSE 80
CMD ["/usr/bin/supervisord","-c","/etc/supervisord.conf"]
