<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModuleRh;
use App\Exceptions\RegleMetierException;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\Mutation;
use App\Models\Promotion;
use App\Models\TypeConge;
use App\Models\TypeDocument;
use App\Rules\ModeleCompatibleAvecType;
use App\Support\HtmlSanitizer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreeModulesRh;
use Tests\Concerns\CreeOrganisation;
use Tests\TestCase;

class ModulesRhTest extends TestCase
{
    use CreeModulesRh, CreeOrganisation, RefreshDatabase;

    private Employe $employe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
        $this->seederModules();
        $this->employe = $this->employeDans($this->sdA1);
    }

    // ---------------------------------------------------------------- seeders

    #[DataProvider('modules')]
    public function test_chaque_module_a_ses_types_et_un_seul_modele_par_defaut_par_type(ModuleRh $module): void
    {
        $types = $module->typeClass()::query()->get();
        $this->assertGreaterThanOrEqual(3, $types->count());

        foreach ($types as $type) {
            $defauts = $module->modeleClass()::query()
                ->where($module->cleType(), $type->id)->where('is_default', true)->where('is_active', true)->count();
            $this->assertSame(1, $defauts, "Type {$type->code} : un modèle par défaut attendu.");
        }
    }

    public function test_types_initiaux_du_cahier_des_charges(): void
    {
        $this->assertEqualsCanonicalizing(
            ['ATTESTATION_TRAVAIL', 'CERTIFICAT_TRAVAIL', 'ATTESTATION_SALAIRE', 'ATTESTATION_CONGE', 'DECISION_ADMINISTRATIVE'],
            TypeDocument::query()->pluck('code')->all(),
        );
        $this->assertSame(6, TypeConge::query()->count());
        $this->assertSame(3, \App\Models\NiveauSanction::query()->count());
        $this->assertSame(4, \App\Models\TypeEvaluation::query()->count());
        $this->assertSame(4, \App\Models\TypePromotion::query()->count());
        $this->assertSame(5, \App\Models\TypeMutation::query()->count());
        $this->assertSame(90, TypeDocument::query()->where('code', 'ATTESTATION_TRAVAIL')->value('delai_renouvellement_jours'));
        $this->assertNull(TypeDocument::query()->where('code', 'DECISION_ADMINISTRATIVE')->value('delai_renouvellement_jours'));
    }

    public function test_le_seeder_est_idempotent(): void
    {
        $avant = TypeDocument::query()->count() + \App\Models\ModelDocument::query()->count();
        $this->seederModules();

        $this->assertSame($avant, TypeDocument::query()->count() + \App\Models\ModelDocument::query()->count());
    }

    public function test_les_modeles_d_evaluation_portent_des_criteres_ponderes(): void
    {
        $modele = \App\Models\ModelEvaluation::query()->firstOrFail();

        $this->assertIsArray($modele->criteres);
        $this->assertSame(['libelle', 'coefficient', 'note_max'], array_keys($modele->criteres[0]));
    }

    // ---------------------------------------------------------------- type / modèle (29.2)

    #[DataProvider('modules')]
    public function test_un_modele_compatible_est_accepte(ModuleRh $module): void
    {
        [$typeA, $modeleA] = $this->deuxTypes($module);

        $enregistrement = $this->creerEnregistrement($module, $this->employe, $typeA, $modeleA);

        $this->assertTrue($enregistrement->exists);
        $this->assertNull($enregistrement->reference, 'Un brouillon ne consomme aucune référence.');
    }

    #[DataProvider('modules')]
    public function test_un_modele_d_un_autre_type_est_refuse(ModuleRh $module): void
    {
        [$typeA, , , $modeleB] = $this->deuxTypes($module);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('ne correspond pas au type');
        $this->creerEnregistrement($module, $this->employe, $typeA, $modeleB);
    }

    #[DataProvider('modules')]
    public function test_un_identifiant_de_modele_force_est_rejete_cote_serveur(ModuleRh $module): void
    {
        [$typeA] = $this->deuxTypes($module);

        $this->expectException(RegleMetierException::class);
        $module->enregistrementClass()::query()->create(
            $this->attributsEnregistrement($module, $this->employe, $typeA->id, 999999),
        );
    }

    #[DataProvider('modules')]
    public function test_changer_le_type_sans_changer_le_modele_est_refuse(ModuleRh $module): void
    {
        [$typeA, $modeleA, $typeB] = $this->deuxTypes($module);
        $enregistrement = $this->creerEnregistrement($module, $this->employe, $typeA, $modeleA);

        $this->expectException(RegleMetierException::class);
        $enregistrement->update([$module->cleType() => $typeB->id]);
    }

    #[DataProvider('modules')]
    public function test_un_modele_inactif_est_refuse(ModuleRh $module): void
    {
        [$typeA, $modeleA] = $this->deuxTypes($module);
        $modeleA->update(['is_active' => false]);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('inactif');
        $this->creerEnregistrement($module, $this->employe, $typeA, $modeleA);
    }

    #[DataProvider('modules')]
    public function test_la_regle_de_validation_filtre_les_modeles_selon_le_type(ModuleRh $module): void
    {
        [$typeA, $modeleA, , $modeleB] = $this->deuxTypes($module);
        $cle = $module->cleModele();

        $valide = fn (int $modeleId) => Validator::make(
            [$cle => $modeleId],
            [$cle => [new ModeleCompatibleAvecType($module, $typeA->id)]],
        )->passes();

        $this->assertTrue($valide($modeleA->id));
        $this->assertFalse($valide($modeleB->id));
        $this->assertFalse($valide(999999));

        $modeleA->update(['is_active' => false]);
        $this->assertFalse($valide($modeleA->id));
    }

    public function test_un_document_sans_modele_n_est_possible_que_si_le_type_ne_l_exige_pas(): void
    {
        $type = TypeDocument::query()->where('code', 'DECISION_ADMINISTRATIVE')->firstOrFail();

        try {
            $this->creerEnregistrement(ModuleRh::Document, $this->employe, $type, null);
            $this->fail('Le type exige un modèle.');
        } catch (RegleMetierException) {
            $type->update(['requires_model' => false]);
            $this->assertTrue($this->creerEnregistrement(ModuleRh::Document, $this->employe, $type->fresh(), null)->exists);
        }
    }

    // ---------------------------------------------------------------- modèles : défaut, version, HTML

    #[DataProvider('modules')]
    public function test_un_nouveau_modele_par_defaut_remplace_le_precedent(ModuleRh $module): void
    {
        [$typeA, $ancien] = $this->deuxTypes($module);
        $classe = $module->modeleClass();

        $nouveau = $classe::query()->create([
            $module->cleType() => $typeA->id, 'titre' => 'Nouveau', 'contenu' => '<p>x</p>', 'is_default' => true,
        ]);

        $this->assertFalse($ancien->fresh()->is_default);
        $this->assertTrue($nouveau->fresh()->is_default);
        $this->assertSame(1, $classe::query()->where($module->cleType(), $typeA->id)->where('is_default', true)->count());
    }

    #[DataProvider('modules')]
    public function test_la_base_interdit_deux_modeles_par_defaut_actifs_pour_un_type(ModuleRh $module): void
    {
        [$typeA, $defaut] = $this->deuxTypes($module);
        $table = $defaut->getTable();

        $this->expectException(QueryException::class);
        DB::table($table)->insert([
            $module->cleType() => $typeA->id, 'titre' => 'Doublon', 'contenu' => 'x',
            'is_default' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[DataProvider('modules')]
    public function test_la_version_augmente_seulement_si_le_modele_est_deja_utilise(ModuleRh $module): void
    {
        [$typeA, $modeleA, , $modeleB] = $this->deuxTypes($module);

        $modeleB->update(['titre' => 'Titre revu (non utilisé)']);
        $this->assertSame(1, $modeleB->fresh()->version);

        $this->creerEnregistrement($module, $this->employe, $typeA, $modeleA);
        $modeleA->update(['titre' => 'Titre revu (utilisé)']);
        $this->assertSame(2, $modeleA->fresh()->version);

        $modeleA->update(['is_active' => true]);
        $this->assertSame(2, $modeleA->fresh()->version, 'Une modification sans effet sur le contenu ne change pas la version.');
    }

    public function test_le_html_des_modeles_est_nettoye_a_l_enregistrement(): void
    {
        $modele = \App\Models\ModelDocument::query()->firstOrFail();
        $modele->update(['contenu' => '<p onclick="x()">Bonjour</p><script>alert(1)</script><a href="javascript:alert(1)">lien</a>']);

        $contenu = $modele->fresh()->contenu;
        $this->assertStringNotContainsString('<script', $contenu);
        $this->assertStringNotContainsString('onclick', $contenu);
        $this->assertStringNotContainsString('javascript:', $contenu);
        $this->assertStringContainsString('Bonjour', $contenu);
    }

    public function test_le_nettoyeur_html_cas_limites(): void
    {
        $this->assertSame('<b>ok</b>', HtmlSanitizer::nettoyer('<b>ok</b>'));
        $this->assertStringNotContainsString('<iframe', HtmlSanitizer::nettoyer('<iframe src="http://x"></iframe>'));
        $this->assertStringNotContainsString('onerror', HtmlSanitizer::nettoyer('<img src=x onerror=alert(1)>'));
        $this->assertStringNotContainsString('<?php', HtmlSanitizer::nettoyer('a<?php system("ls"); ?>b'));
        $this->assertStringContainsString('{{ employe.name }}', HtmlSanitizer::nettoyer('<p>{{ employe.name }}</p>'));
    }

    // ---------------------------------------------------------------- enregistrements : intégrité

    #[DataProvider('modules')]
    public function test_la_reference_est_unique_et_nullable(ModuleRh $module): void
    {
        [$typeA, $modeleA] = $this->deuxTypes($module);
        $table = (new ($module->enregistrementClass()))->getTable();

        $premier = $this->creerEnregistrement($module, $this->employe, $typeA, $modeleA);
        $second = $this->creerEnregistrement($module, $this->employe, $typeA, $modeleA);
        $this->assertNull($premier->reference);
        $this->assertNull($second->reference, 'Plusieurs brouillons sans référence sont autorisés.');

        DB::table($table)->where('id', $premier->id)->update(['reference' => '0001/TEST/2026']);

        $this->expectException(QueryException::class);
        DB::table($table)->where('id', $second->id)->update(['reference' => '0001/TEST/2026']);
    }

    #[DataProvider('modules')]
    public function test_la_reference_n_est_pas_assignable_en_masse(ModuleRh $module): void
    {
        [$typeA, $modeleA] = $this->deuxTypes($module);
        $attributs = $this->attributsEnregistrement($module, $this->employe, $typeA->id, $modeleA->id);

        $enregistrement = $module->enregistrementClass()::query()->create($attributs + [
            'reference' => '0001/PIRATE/2026', 'contenu_genere' => '<p>forgé</p>', 'version_modele' => 99,
        ]);

        $this->assertNull($enregistrement->fresh()->reference);
        $this->assertNull($enregistrement->fresh()->contenu_genere);
        $this->assertNull($enregistrement->fresh()->version_modele);
    }

    #[DataProvider('modules')]
    public function test_le_matricule_doit_exister(ModuleRh $module): void
    {
        [$typeA, $modeleA] = $this->deuxTypes($module);
        $inconnu = new Employe(['matricule' => 'INCONNU']);

        $this->expectException(QueryException::class);
        $this->creerEnregistrement($module, $inconnu, $typeA, $modeleA);
    }

    #[DataProvider('modules')]
    public function test_le_code_d_un_type_est_unique(ModuleRh $module): void
    {
        [$typeA] = $this->deuxTypes($module);

        $this->expectException(QueryException::class);
        $module->typeClass()::query()->create(['code' => $typeA->code, 'name' => 'Doublon']);
    }

    #[DataProvider('modules')]
    public function test_les_relations_de_l_employe_et_de_l_enregistrement(ModuleRh $module): void
    {
        [$typeA, $modeleA] = $this->deuxTypes($module);
        $enregistrement = $this->creerEnregistrement($module, $this->employe, $typeA, $modeleA);

        $relation = match ($module) {
            ModuleRh::Document => 'documents', ModuleRh::Conge => 'conges', ModuleRh::Sanction => 'sanctions',
            ModuleRh::Evaluation => 'evaluations', ModuleRh::Promotion => 'promotions', ModuleRh::Mutation => 'mutations',
        };

        $this->assertTrue($this->employe->{$relation}()->whereKey($enregistrement->getKey())->exists());
        $this->assertTrue($enregistrement->employe->is($this->employe));
        $this->assertTrue($enregistrement->type->is($typeA));
        $this->assertTrue($enregistrement->modele->is($modeleA));
        $this->assertTrue($modeleA->type->is($typeA));
        $this->assertTrue($typeA->modeleParDefaut()->is($modeleA));
    }

    public function test_les_tables_metier_restent_distinctes(): void
    {
        $tables = ['documents', 'conges', 'sanctions', 'evaluations', 'promotions', 'mutations'];
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertCount(6, array_unique(array_map(fn (ModuleRh $m) => (new ($m->typeClass()))->getTable(), ModuleRh::cases())));
    }

    public function test_aucune_trace_de_paylist_contacts_ou_gestionnaire_paie(): void
    {
        foreach (['paies', 'contacts', 'paylists'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "La table {$table} ne doit pas exister.");
        }
        $this->assertFalse(\Spatie\Permission\Models\Role::query()->whereIn('name', ['GESTIONNAIRE_PAIE', 'SUPER_ADMIN'])->exists());
    }

    // ---------------------------------------------------------------- spécificités

    public function test_le_mode_de_calcul_des_conges_est_un_enum(): void
    {
        $annuel = TypeConge::query()->where('code', 'ANNUEL')->firstOrFail();

        $this->assertSame(\App\Enums\ModeCalcul::Calendaire, $annuel->mode_calcul);
        $this->assertSame(\App\Enums\ModeCalcul::Ouvre, TypeConge::query()->where('code', 'EXCEPTIONNEL')->firstOrFail()->mode_calcul);
        $this->assertTrue(TypeConge::query()->where('code', 'MALADIE')->firstOrFail()->requires_justificatif);
    }

    public function test_les_salaires_d_une_promotion_sont_masques_a_la_serialisation(): void
    {
        [$typeA, $modeleA] = $this->deuxTypes(ModuleRh::Promotion);
        $promotion = $this->creerEnregistrement(ModuleRh::Promotion, $this->employe, $typeA, $modeleA);
        $promotion->update(['ancien_salaire' => 100000, 'nouveau_salaire' => 120000]);

        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertArrayNotHasKey('nouveau_salaire', $promotion->fresh()->toArray());
        $this->assertSame('120000.00', $promotion->fresh()->nouveau_salaire);
    }

    public function test_une_mutation_vers_un_departement_d_une_autre_societe_est_refusee_par_la_base(): void
    {
        [$typeA, $modeleA] = $this->deuxTypes(ModuleRh::Mutation);
        $deptB = Departement::query()->where('societe_id', $this->societeB->id)->firstOrFail();

        $this->expectException(QueryException::class);
        Mutation::query()->create(
            $this->attributsEnregistrement(ModuleRh::Mutation, $this->employe, $typeA->id, $modeleA->id) + [
                'nouveau_site_id' => $this->sdA1->site_id,
                'nouveau_departement_id' => $deptB->id,
                'nouvelle_societe_id' => $this->societeA->id,
            ],
        );
    }

    public function test_une_sanction_precedente_se_chaine(): void
    {
        [$typeA, $modeleA] = $this->deuxTypes(ModuleRh::Sanction);
        $premiere = $this->creerEnregistrement(ModuleRh::Sanction, $this->employe, $typeA, $modeleA);
        $seconde = \App\Models\Sanction::query()->create(
            $this->attributsEnregistrement(ModuleRh::Sanction, $this->employe, $typeA->id, $modeleA->id)
            + ['sanction_precedente_id' => $premiere->id],
        );

        $this->assertTrue($seconde->sanctionPrecedente->is($premiere));
    }

    public function test_le_prefixe_de_reference_par_defaut_de_chaque_module(): void
    {
        $this->assertSame('DA_DARH/DLS', ModuleRh::Document->prefixeParDefaut());
        $this->assertSame('DS_DARH/DLS', ModuleRh::Sanction->prefixeParDefaut());
        $this->assertSame('EV_DARH/DLS', ModuleRh::Evaluation->prefixeParDefaut());
        $this->assertSame('PR_DARH/DLS', ModuleRh::Promotion->prefixeParDefaut());
        $this->assertSame('MU_DARH/DLS', ModuleRh::Mutation->prefixeParDefaut());
        $this->assertSame('CG_DARH/DLS', ModuleRh::Conge->prefixeParDefaut());
    }
}
