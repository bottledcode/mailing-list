FROM dunglas/frankenphp:1.3.4-bookworm

RUN install-php-extensions @composer

COPY composer.json composer.lock /app/

RUN composer install --no-dev --no-scripts

COPY . /app

RUN composer dump-autoload --optimize
