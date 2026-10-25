@echo off
rem Worker de file (génération des PDF). Redémarre le processus s'il s'arrête (voir docs/EXPLOITATION.md).
cd /d "%~dp0.."
:boucle
php artisan queue:work --tries=3 --max-time=3600 --sleep=3 >> storage\logs\worker.log 2>&1
timeout /t 5 /nobreak >nul
goto boucle
