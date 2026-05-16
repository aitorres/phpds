FROM composer:latest AS deps

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install \
	--no-interaction \
	--no-progress \
	--prefer-dist \
	--no-dev \
	--optimize-autoloader

FROM php:8.5-cli-alpine AS app

WORKDIR /var/www

ENV APP_ENV=production
ENV PORT=8080

COPY --from=deps /app/vendor ./vendor
COPY . .

RUN mkdir -p /var/www/logs /var/www/var/cache \
	&& chown -R www-data:www-data /var/www/logs /var/www/var

USER www-data

EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t public"]
