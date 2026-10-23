# Avancement

## Phase en cours : Phase 1 terminée — en attente de validation avant la Phase 2

### Phase 0 (terminée, commit a330a6d)
Projet Laravel 12, Filament v5, Spatie Permission 6, DomPDF, MariaDB, bases `erp_rh_dls` et `erp_rh_dls_test`.
Incident : tables `mysql.db` / `mysql.tables_priv` réparées (sauvegarde dans `C:/xampp/mysql/data_backup_mysql_20261023_190619`) ; `mysql.db` a perdu 3 lignes de droits par base.

### Phase 1 — fait
- Migrations : societes, sites_affectation, departements, site_departements (FK composite société/département), is_active sur users, site_departement_user, types_contrats, grades, employes, employe_affectations (une seule ligne ouverte garantie par colonne virtuelle unique), employe_fichiers.
- `User::hasGlobalAccess()` (ADMIN = RH_MANAGER, règle unique), `PerimetreService` (scope `dansPerimetre`), `EmployeService` (création + changement d'affectation avec historique), `UserManagementService` (rôles, affectations, activation, dernier admin).
- Policies : Employe, User, Organisation, Referentiel. Compte désactivé = aucun droit.
- Permissions centralisées dans `App\Support\Permissions` ; ADMIN et RH_MANAGER reçoivent le même jeu.
- Dossier numérique : disque privé, route de téléchargement authentifiée + Policy.
- Commande `php artisan dls:creer-admin` (mot de passe 12+ caractères, complexe).
- Seeders : rôles/permissions, sociétés, départements DLS, types de contrats ; `DemoSeeder` (local uniquement, mot de passe via `DEMO_PASSWORD`).
- Filament : Sociétés, Sites, Départements, Site-départements, Types de contrats, Grades, Utilisateurs (rôles et périmètre via le service).
- Tests : 47 verts (périmètre, équivalence ADMIN/RH_MANAGER, auto-modification, dernier admin, contraintes SQL, téléchargement privé, commande, interface Filament).

### Reste hors Phase 1
- Test 29.1 « RH_SENIOR valide / ADMIN approuve / ADMIN n'approuve pas son propre dossier » : dépend du workflow (Phase 3).
- Ressource Filament Employés complète (onglets, dossier numérique) : Phase 4.
- Journalisation d'audit des changements de rôles : Phase 9 (AuditService).

## Hypothèses / écarts
- Serveur de dev = MariaDB 10.4.32 (pas MySQL 8) ; driver `mariadb`.
- spatie/laravel-permission ^7 exige PHP 8.3+ → série 6 retenue (PHP 8.2).
- Jeton GitHub invalide retiré de la config Composer globale (accord utilisateur).
- Permissions des rôles non globaux (hypothèse) : RH_SENIOR/RH_OFFICER = lecture + création/modification d'employés + gestion des fichiers ; RH_JUNIOR = idem sans gestion des fichiers. Seuls ADMIN/RH_MANAGER ont suppression, utilisateurs, organisation, référentiels, paramètres.
- Grades : référentiel vide (le référentiel réel de DLS n'est pas fourni).
- Suppression d'enregistrements d'organisation non exposée dans l'interface (désactivation via `is_active`).
- Les modifications de société/site/département/fonction d'un employé sont bloquées hors `EmployeService::changerAffectation`.
