# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# ERP RH DLS (Djibouti Labour Service)

Cahier des charges complet : `PROMPT-ERP-RH-DLS.md` (à la racine ; à relire en cas de doute). Avancement, hypothèses à valider par phase : `docs/AVANCEMENT.md`. Le `README.md` est celui, générique, de Laravel.

## Stack
Laravel 12 · PHP 8.2 (poste dev) · Filament v5 (panneau `admin`) · Livewire · MariaDB 10.4 (XAMPP, driver `mariadb`) · spatie/laravel-permission 6 (la série 7 exige PHP 8.3) · barryvdh/laravel-dompdf 3 · PHPUnit · queue `database`.

## Commandes
- `php artisan migrate` · `php artisan serve` · `composer dev` (serve + queue + pail + vite)
- Tests : `php artisan test` ; un fichier : `php artisan test tests/Feature/WorkflowTest.php` ; un test : `php artisan test --filter=nomDuTest`.
- Base de tests `erp_rh_dls_test` (voir `phpunit.xml`). Ne jamais lancer `migrate:fresh` sur `erp_rh_dls` sans confirmation. Les nouvelles migrations ne sont pas appliquées automatiquement sur la base de dev.
- Commandes métier : `dls:expirer-workflows` (horaire), `dls:appliquer-effets` (quotidien 00:05), `CreerAdmin`. Planification dans `routes/console.php` ; en exploitation `schedule:run` chaque minute + `queue:work` (génération PDF en job).
- Composer : utiliser Bash pour les contraintes `^x.y` (PowerShell les altère).

## Architecture
- **Six modules RH** (`App\Enums\ModuleRh` : congé, document, évaluation, mutation, promotion, sanction). L'enum fait le lien module → classes enregistrement / type / modèle ; `AppServiceProvider` en déduit policies et enregistrements. Chaque module a son enregistrement (`Conge`…), son type (`TypeConge`…) et son modèle HTML (`ModelConge`…, trait `EstUnModele` : liste blanche de variables via `App\Support\VariablesModele`, nettoyage HTML, un seul défaut actif, version incrémentée).
- **Workflow centralisé** : table polymorphe `workflows` + `workflow_histories`, trait `HasWorkflow` sur les enregistrements. `WorkflowService::executer()` est l'unique point de changement de statut (matrice dans `WorkflowAction`/`WorkflowStatut`, verrou sur la ligne, contrôle acteur/périmètre/séparation des responsabilités, historique + audit dans la même transaction). L'approbation finale attribue la référence (`ReferenceService`), applique les effets (`EffetsRhService` : mutation/promotion → `EmployeService::changerAffectation` ; congé → débit du solde via `CongeService`) puis lance `GenererPdfJob`. Une impossibilité à l'approbation annule tout.
- **Accès** : `User::hasGlobalAccess()` (ADMIN, RH_MANAGER) est la seule vérification de rôle global ; les autres rôles sont limités par `PerimetreService` (société/site/département). Permissions listées dans `App\Support\Permissions`, policies dans `app/Policies`.
- **Audit** : `AuditService::journaliser()` écrit dans `audit_logs` (ajout seul, données sensibles filtrées) à l'intérieur des transactions métier ; listener `JournaliserAuthentification` pour connexions/échecs.
- **PDF** : `TemplateRendererService` (modèle + variables) → `GenerationPdfService` (DomPDF) → disque privé ; signature interne derrière `SignatureProviderInterface`.
- **Employés** : l'affectation (société/site/département/fonction) ne se modifie que par `EmployeService::changerAffectation` (historique `EmployeAffectation`) ; dossier numérique via `EmployeFichier` + contrôleurs de téléchargement.
- Les ressources Filament (`app/Filament/Resources`) ne contiennent que de l'interface ; les règles échouent par `RegleMetierException`.
- Tests : `tests/Concerns/CreeModulesRh` et `CreeOrganisation` préparent les jeux de données ; `tests/Support/attribuer_reference.php` est un helper.

## Conventions
`declare(strict_types=1);`, types de retour explicites, logique métier dans `app/Services`, Filament = interface seulement, UI et messages en français, fuseau `Africa/Djibouti`, disque privé `private` (jamais `public`) pour les fichiers.

## Règles non négociables
1. Workflow centralisé (polymorphe) ; tables métier distinctes.
2. Types et modèles propres à chaque module (6 modules).
3. Compteur de références par module et par année, via `ReferenceService` (verrou), attribué à l'approbation finale uniquement.
4. ADMIN = RH_MANAGER : mêmes droits, accès global, règle unique `User::hasGlobalAccess()`.
5. Séparation des responsabilités, immutabilité des références et historiques pour tous les rôles.
6. Aucun module Paylist, aucune table `contacts`, aucun rôle GESTIONNAIRE_PAIE, aucun SUPER_ADMIN.

Méthode : une phase à la fois, compte rendu (mis à jour dans `docs/AVANCEMENT.md`), validation de l'utilisateur, commit.
