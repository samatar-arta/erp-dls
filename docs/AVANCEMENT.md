# Avancement

## Phase en cours : Phase 0 terminée — en attente de validation avant la Phase 1

### Fait
- Prérequis vérifiés : PHP 8.2.12 (toutes extensions requises), Composer 2.9.7, Node 22, Git.
- Laravel 12.70 ; Filament v5, spatie/laravel-permission ^6, barryvdh/laravel-dompdf ^3 installés.
- Panneau Filament `admin` (`/admin/login` répond 200). Locale `fr`, fuseau `Africa/Djibouti`, disque `private`, queue `database`.
- Bases `erp_rh_dls` et `erp_rh_dls_test`, utilisateur `erp_dls@localhost` avec droits sur ces deux bases ; mot de passe dans `.env` (non versionné).
- `php artisan migrate` OK ; `php artisan test` : 2 tests verts.

### Incident MariaDB
- Tables `mysql.db` et `mysql.tables_priv` corrompues (Aria). Sauvegarde dans `C:/xampp/mysql/data_backup_mysql_20261023_190619`, puis `REPAIR TABLE` : `mysql.db` a perdu ses 3 lignes de droits par base (à ré-accorder si une autre application échoue avec une erreur d'accès).

## Hypothèses / écarts
- Serveur de dev = MariaDB 10.4.32 (pas MySQL 8) ; driver `mariadb`.
- spatie/laravel-permission ^7 exige PHP 8.3+ → série 6 retenue (PHP 8.2).
- Jeton GitHub invalide retiré de la config Composer globale (accord utilisateur).
