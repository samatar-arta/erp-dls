# Avancement

## Phase en cours : Phase 6 terminée — en attente de validation avant la Phase 7

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

### Phase 4 — fait (interfaces Filament métier)
- Six ressources (Documents, Congés, Sanctions, Évaluations, Promotions, Mutations) sur un socle commun `ModuleRhResource` : formulaire dynamique (le type filtre les modèles actifs et présélectionne le modèle par défaut, règle `ModeleCompatibleAvecType` en contrôle serveur), tableau filtrable (statut, type), fiche avec statut, échéance et frise de l'historique du workflow. Pages communes dans `ModulesRh/Pages`, pages concrètes générées (convention de nommage).
- Boutons du workflow sur la fiche (un par transition, visibles via `WorkflowService::peutExecuter`, motif obligatoire pour le rejet). Le contrôle réel est refait par le service à l'exécution.
- Ressource Agents : liste/fiche/création/édition, limitées au périmètre ; création via `EmployeService::creer` (couple société/site/département) ; l'édition ne touche jamais l'affectation ; relation managers « Historique des affectations » (lecture seule) et « Dossier numérique » (disque privé, téléchargement par la route autorisée).
- `EnregistrementRhService` : création/modification des brouillons (droits, périmètre, situation actuelle lue sur l'agent, salaires ignorés sans permission, agent sorti refusé sauf pour les documents, mutation sans changement refusée, nouvelle affectation contrôlée contre le périmètre).
- Policies : `EnregistrementRhPolicy` (périmètre de l'agent, modification limitée aux brouillons) et `SanctionPolicy` (accès renforcé). `WorkflowService` exige maintenant aussi la policy `view` du module.
- Nouvelles permissions : `rh.view|create|update`, `sanctions.view|manage` (ADMIN, RH_MANAGER, RH_SENIOR), `salaires.view` (ADMIN, RH_MANAGER).
- Tests : 36 nouveaux (InterfaceModulesRhTest, EnregistrementRhServiceTest).

### Hypothèses Phase 4
- Hors périmètre : l'administration des types et des modèles HTML (12 écrans) est reportée à la Phase 5, avec l'éditeur de modèles et l'aperçu qui dépendent du moteur de rendu.
- Congés : le nombre de jours est calculé provisoirement en jours calendaires dans le formulaire ; calcul définitif (ouvrés, fériés, solde, chevauchement) en Phase 6.
- Évaluations : la saisie des notes par critère et le score pondéré arrivent en Phase 7.
- Promotions : le grade actuel n'existe pas sur l'agent (référentiel vide) ; seule la fonction actuelle est capturée.
- Les promotions et mutations approuvées ne modifient pas encore l'affectation de l'agent (Phase 7).
- Les salaires de promotion ne sont visibles/saisissables qu'avec `salaires.view`.
- Les relations « onglets par module » sur la fiche agent ne sont pas des relation managers : on retrouve les enregistrements d'un agent par la recherche (matricule) de chaque module.

### Phase 5 — fait (documents, PDF, signature)
- `TemplateRendererService` : remplacement des seules variables de la liste blanche `VariablesModele` (par module, incluant `<module>.reference`, sans aucune donnée sensible : ni salaires, ni CNI/CNSS, ni téléphone/adresse), valeurs échappées, modèle jamais compilé (ni Blade ni PHP), sortie repassée au `HtmlSanitizer`. Aperçu avec noms de variables entre crochets. Variable inconnue = vide au rendu et refus à l'enregistrement du modèle (`EstUnModele`).
- `GenerationPdfService` + `GenererPdfJob` (en file `database`, unique par enregistrement, 3 essais) : PDF DomPDF sur le disque privé, chemin déterministe, contenu HTML figé à la première génération (`contenu_genere`, `version_modele`), empreinte SHA-256 (`empreinte_pdf`) et date (`genere_le`). Idempotent : un PDF valide n'est jamais régénéré, la référence n'est jamais touchée ; un fichier absent ou altéré est régénéré à l'identique depuis le contenu figé.
- Déclenchement à l'approbation (`statut_generation = en_attente`, job envoyé après validation de la transaction). Bouton « Relancer la génération » (permission d'approbation) et « Télécharger le PDF » sur la fiche.
- Téléchargement `GET rh/{module}/{id}/pdf` : authentifié, policy `view` du module (périmètre, sanctions renforcées), contrôle d'intégrité par empreinte.
- Signature : `SignatureProviderInterface` + `SignatureInterne` (mention approbateur/validateur/date, empreinte HMAC liée à la référence, à l'agent et à l'approbation, vérifiable). Bloc ajouté automatiquement si le modèle ne place pas `{{ signature }}`.
- Délai de renouvellement des documents : `EnregistrementRhService` refuse un nouveau document du même type pour le même agent dans la fenêtre du délai du type (90 jours pour attestation de travail, certificat de travail, attestation de salaire) ; les documents rejetés ou expirés ne comptent pas.
- Administration : 6 écrans « Types » et 6 écrans « Modèles » (groupe « Types et modèles »), lecture pour le personnel RH, écriture réservée (`referentiels.manage`). Éditeur HTML avec liste des variables, validation, aperçu en cadre isolé (`sandbox`), critères d'évaluation (répéteur), version affichée, un seul modèle par défaut par type.
- Tests : 41 nouveaux (`PdfEtModelesTest`).

### Hypothèses Phase 5
- La signature interne n'est pas une signature électronique qualifiée ; l'interface permet de brancher un prestataire plus tard.
- Le worker de file doit tourner en exploitation (`php artisan queue:work`) ; documenté en Phase 10. Sans worker, le PDF reste « en attente » et peut être relancé.
- Les tests utilisent `QUEUE_CONNECTION=database` (jobs jamais exécutés) et un disque `private` factice.
- Le champ `date_du_jour` des modèles est figé au moment de la génération.
- Types sans modèle (`requires_model = false`) : aucun PDF.
- Le code d'un type n'est plus modifiable après création (identifiant stable).

### Phase 6 — fait (congés : jours, fériés, soldes) — en attente de validation
- `CongeService` : calcul des jours par type (`mode_calcul` : calendaire = tous les jours inclus ; ouvré = hors jours non ouvrés et jours fériés), ventilé par année civile ; contrôle des chevauchements ; contrôle et débit du solde.
- `EnregistrementRhService` : `nbre_jours` n'est plus saisi, il est recalculé par le serveur à la création et à chaque modification d'un brouillon (le champ de l'écran est en lecture seule, avec aperçu).
- Tables `jours_feries` (date unique, libellé, récurrent, actif) et `soldes_conges` (agent, type, année : acquis, report, pris ; unique). `SoldeConge` : seuls acquis et report sont assignables ; `jours_pris` n'évolue que par l'approbation.
- Solde : vérifié à la création/modification (solde restant moins les jours déjà engagés dans le circuit), à la soumission, puis à l'approbation sous verrou (INSERT … ON DUPLICATE KEY UPDATE puis lecture verrouillante, dans la transaction du workflow). Un solde insuffisant à l'approbation annule l'approbation entière (pas de référence, pas de PDF). Débit uniquement à l'approbation ; un rejet ne débite rien.
- Écrans : « Jours fériés » (groupe Référentiels, écriture `referentiels.manage`) et « Soldes de congés » (groupe Gestion RH, lecture dans le périmètre, ajustement acquis/report réservé à `referentiels.manage`, donc ADMIN et RH_MANAGER). `SoldeCongePolicy` : création et suppression interdites.
- `JoursFeriesSeeder` : fêtes à date fixe (1er janvier, 1er mai, 27 juin), récurrentes.
- Tests : 26 nouveaux (`CongesTest`).

### Hypothèses Phase 6 (le cahier des charges n'était pas disponible : à valider)
- Jours non ouvrés = vendredi seulement (`config/conges.php`, variable `CONGES_JOURS_NON_OUVRES`, numéros ISO ; « 5,6 » pour vendredi + samedi).
- Jours fériés exclus du décompte seulement pour les types en jours ouvrés ; en jours calendaires, ils comptent.
- Fêtes religieuses (dates mobiles) non pré-remplies : à saisir chaque année dans l'écran « Jours fériés ». Noël et autres fêtes non incluses faute de liste officielle fournie.
- Seuls les types avec un droit annuel > 0 (aujourd'hui : congé annuel, 30 jours) sont soumis au solde ; les autres ne génèrent aucune ligne de solde.
- Droit annuel complet attribué à la première utilisation de l'année (pas de prorata à l'embauche, pas d'acquisition mensuelle) ; report des années précédentes saisi à la main (pas de report automatique).
- Congé à cheval sur deux années : débit ventilé par année, chaque année contrôlée sur son propre solde.
- Demi-journées non gérées (les jours sont entiers).
- Chevauchement : tout congé non rejeté et non expiré du même agent, tous types confondus, bloque la période.
- Pas de réintégration du solde après approbation (le circuit n'a pas d'annulation d'un congé approuvé).
- Pas de test de concurrence multi-processus sur le débit du solde (le verrou suit le schéma validé de `ReferenceService`).
- Migration `2026_10_28_000019` non encore appliquée sur la base de développement `erp_rh_dls` (tests : base `erp_rh_dls_test`).

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
