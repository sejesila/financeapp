#!/bin/bash
set -e

cd /home/farmpedia-finance/htdocs/finance.farmpedia.org  # Add this line

echo "Deployment started ..."
(php artisan down) || true
git pull origin main
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan clear-compiled
npm run build
php artisan migrate --force
php artisan up
echo "Deployment finished!"
