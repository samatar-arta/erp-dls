# ERP RH DLS (Djibouti Labour Service)

Cahier des charges complet : `PROMPT-ERP-RH-DLS.md` (à la racine ; à relire en cas de doute). Avancement : `docs/AVANCEMENT.md`.

## Stack
Laravel 12 · PHP 8.2 (poste dev) · Filament v5 (panneau `admin`) · Livewire · MariaDB 10.4 (XAMPP) · spatie/laravel-permission 6 · barryvdh/laravel-dompdf 3 · PHPUnit · queue `database`.

## Commandes
- `php artisan migrate` · `php artisan test` · `php artisan serve`
- Tests : base `erp_rh_dls_test` (voir `phpunit.xml`). Ne jamais lancer `migrate:fresh` sur `erp_rh_dls` sans confirmation.
- Composer : utiliser Bash pour les contraintes `^x.y` (PowerShell les altère).

## Conventions
`declare(strict_types=1);`, types de retour explicites, logique métier dans `app/Services`, Filament = interface seulement, UI en français, fuseau `Africa/Djibouti`, disque privé `private` (jamais `public`) pour les fichiers.

## Règles non négociables
1. Workflow centralisé (polymorphe) ; tables métier distinctes.
2. Types et modèles propres à chaque module (6 modules).
3. Compteur de références par module et par année, via `ReferenceService` (verrou), attribué à l'approbation finale uniquement.
4. ADMIN = RH_MANAGER : mêmes droits, accès global, règle unique `User::hasGlobalAccess()`.
5. Séparation des responsabilités, immutabilité des références et historiques pour tous les rôles.
6. Aucun module Paylist, aucune table `contacts`, aucun rôle GESTIONNAIRE_PAIE, aucun SUPER_ADMIN.
Méthode : une phase à la fois, compte rendu, validation de l'utilisateur, commit.
