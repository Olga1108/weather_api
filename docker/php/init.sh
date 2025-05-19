#!/bin/sh
set -e

cd /var/www/symfony

echo "Running composer install..."
composer install

echo "Creating dev database..."
php bin/console doctrine:database:create || true

echo "Creating test database..."
php bin/console doctrine:database:create --env=test || true

echo "Running dev migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "Running test migrations..."
php bin/console doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration

echo "Running PHPUnit tests..."
php vendor/bin/phpunit

echo "Init script completed." 