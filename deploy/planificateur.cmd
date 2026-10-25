@echo off
rem Lancé chaque minute par le Planificateur de tâches Windows (voir docs/EXPLOITATION.md).
cd /d "%~dp0.."
php artisan schedule:run >> storage\logs\planificateur.log 2>&1
