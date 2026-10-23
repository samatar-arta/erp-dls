# Avancement

## Phase en cours : Phase 3 terminée — en attente de validation avant la Phase 4

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

### Phase 2 — fait
- 6 familles indépendantes (documents, congés, sanctions, évaluations, promotions, mutations) : tables `types_*`, `model_*` et enregistrements, plus `niveaux_sanctions`. Migrations 11 à 16 générées puis relues.
- `ModuleRh` (enum) : classes type/modèle/enregistrement, colonnes, alias de morph map, préfixe de référence par défaut.
- Compatibilité type/modèle : garde-fou serveur dans les enregistrements (`VerifieCompatibiliteModele`) + règle de validation réutilisable `ModeleCompatibleAvecType`. Modèle inactif refusé.
- Modèles HTML (`EstUnModele`) : contenu nettoyé à l'enregistrement (`HtmlSanitizer`), un seul modèle par défaut actif par type (contrainte SQL + bascule automatique), version incrémentée si un modèle déjà utilisé est modifié.
- `reference` unique et nullable ; `reference`, `contenu_genere`, `version_modele`, `fichier_path`, `statut_generation` non assignables en masse (réservés aux services).
- Seeders : 5 types de documents (1 modèle chacun), 6 types de congés, 3 types de sanctions + 3 niveaux, 4 types d'évaluations (critères pondérés), 4 de promotions, 5 de mutations.
- Tests : 150 verts au total (103 pour les modules).

### Hypothèses Phase 2
- Promotions : `ancien_grade_id` / `nouveau_grade_id` (clés vers `grades`) à la place de `ancien_grade` / `nouveau_grade`.
- Mutations : les champs `nouveau_*` sont nullables (null = inchangé) ; les `ancien_*` sont capturés à la création.
- Congés : `user_id` = utilisateur ayant saisi la demande ; types de congés (jours/an, mode de calcul, justificatif) fixés à titre indicatif : annuel 30 j calendaires ; maladie, maternité, paternité avec justificatif ; exceptionnel en jours ouvrés.
- Colonne `statut_generation` (en_attente / genere / echec) ajoutée à chaque table métier pour la Phase 5.
- La liste blanche de variables du `TemplateRendererService` (Phase 5) devra inclure `<module>.reference` pour chaque module : les gabarits seedés utilisent `{{ conge.reference }}`, `{{ sanction.reference }}`, etc.
- `jours_feries` et `soldes_conges` seront créés en Phase 6.
- Pas encore de Policy ni de ressource Filament pour ces modules (Phase 4).

### Phase 3 — fait (références et workflow)
- `ReferenceService` : une seule instruction atomique `INSERT ... ON DUPLICATE KEY UPDATE` crée/incrémente la séquence (module, année), puis lecture verrouillante. Numéro rendu si la transaction est annulée (pas de trou). Format `{numero:N}/{prefixe}/{annee}`, préfixe et format configurables (`reference_modules`, `ReferencesSeeder`), horloge injectable (`$date` ou `Carbon::setTestNow`).
- Workflow centralisé : tables `workflows` (une ligne par enregistrement, unique type+id) et `workflow_histories` (ajout seul). Enums `WorkflowStatut` et `WorkflowAction` (matrice des transitions). Trait `HasWorkflow` sur les six modèles (workflow BROUILLON créé avec l'enregistrement, référence immuable, suppression limitée aux brouillons sans référence).
- Circuit : BROUILLON → DOCUMENT_SOUMIS (soumettre) → EN_ATTENTE_VALIDATION (prendre en charge) → VALIDE (valider) → EN_ATTENTE_APPROBATION (transmettre) → APPROUVE (approuver, c'est là que la référence est attribuée). Rejet (commentaire obligatoire) depuis toute étape en cours ; REJETE/EXPIRE → BROUILLON (rouvrir) ; EXPIRE par le système.
- `WorkflowService` : verrou sur la ligne workflow puis revérification du statut, contrôle de l'acteur (actif, permission, périmètre, séparation), historique dans la même transaction, `expirerEchus()` prêt pour le planificateur (Phase 8), `peutExecuter()` pour l'interface (Phase 4).
- Permissions `workflow.soumettre|valider|approuver` : ADMIN/RH_MANAGER = les trois ; RH_SENIOR = soumettre + valider ; RH_OFFICER et RH_JUNIOR = soumettre.
- Morph map `document|conge|sanction|evaluation|promotion|mutation`.
- Tests : 76 nouveaux (ReferencesTest, WorkflowTest), dont concurrence réelle (6 processus PHP) et les cas 29.1 reportés (SENIOR valide, ADMIN approuve, ADMIN/RH_MANAGER ne traitent pas leur propre dossier).

### Hypothèses Phase 3
- Morph map non « enforced » (`Relation::morphMap`) : User reste enregistré par son nom de classe pour ne pas casser les rôles Spatie déjà présents. Écart assumé par rapport à `enforceMorphMap`.
- Séparation des responsabilités : l'auteur (`cree_par_id`, utilisateur connecté à la création) et le soumetteur ne peuvent ni prendre en charge, ni valider, ni transmettre, ni approuver, ni rejeter, tous rôles confondus. Option `workflow.approbateur_different_validateur` (défaut : désactivée) pour exiger en plus un approbateur différent du validateur.
- Délai de traitement : 48 h par étape (`config/workflow.php`, `WORKFLOW_DELAI_HEURES`), renouvelé à chaque étape ; l'expiration effective est planifiée en Phase 8.
- Rejeter une approbation exige `workflow.approuver` ; rejeter les étapes précédentes exige `workflow.valider`.
- Rouvrir (REJETE/EXPIRE → BROUILLON) exige `workflow.soumettre` ; l'auteur d'origine reste exclu de la validation/approbation du nouveau cycle.
- Incident corrigé : première version (INSERT IGNORE puis SELECT FOR UPDATE) provoquait des interblocages MariaDB (1213) sous concurrence, détectés par le test multi-processus.
- Les enregistrements sans utilisateur connecté à la création (seeders, console) ont `cree_par_id` nul.
- Le test de concurrence purge la séquence EVALUATION par un processus dédié (les enfants valident hors de la transaction du test).

### Reste hors Phase 1
- Test 29.1 (SENIOR valide, ADMIN approuve, ADMIN n'approuve pas son propre dossier) : fait en Phase 3 (WorkflowTest).
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
