@echo off
rem À lancer après chaque déploiement (voir docs/EXPLOITATION.md).
cd /d "%~dp0.."
php artisan down --retry=60
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:optimize
php artisan queue:restart
php artisan up
