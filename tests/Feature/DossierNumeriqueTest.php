<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FichierCategorie;
use App\Enums\RoleName;
use App\Models\EmployeFichier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreeOrganisation;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DossierNumeriqueTest extends TestCase
{
    use CreeOrganisation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerOrganisation();
        Storage::fake('private');
    }

    private function fichierPour(\App\Models\Employe $employe): EmployeFichier
    {
        Storage::disk('private')->put("employes/{$employe->id}/cv.txt", 'contenu confidentiel');

        return EmployeFichier::query()->create([
            'employe_id' => $employe->id,
            'categorie' => FichierCategorie::Personnel,
            'nom_original' => 'cv.txt',
            'path' => "employes/{$employe->id}/cv.txt",
            'mime' => 'text/plain',
            'taille' => 20,
        ]);
    }

    public function test_telechargement_autorise_dans_le_perimetre(): void
    {
        $employe = $this->employeDans($this->sdA1);
        $fichier = $this->fichierPour($employe);
        $user = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);

        $this->actingAs($user)
            ->get(route('employes.fichiers.telecharger', [$employe, $fichier]))
            ->assertOk()
            ->assertDownload('cv.txt');

        // Le téléchargement est tracé (agent identifié par matricule), sans le nom ni le chemin du fichier.
        $log = \App\Models\AuditLog::query()->where('action', 'employe.fichier_telecharge')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($employe->matricule, $log->cible_id);
        $this->assertStringNotContainsString('cv.txt', json_encode($log->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_telechargement_refuse_hors_perimetre(): void
    {
        $employe = $this->employeDans($this->sdA2);
        $fichier = $this->fichierPour($employe);
        $user = $this->utilisateur(RoleName::RhOfficer, [$this->sdA1]);

        $this->actingAs($user)
            ->get(route('employes.fichiers.telecharger', [$employe, $fichier]))
            ->assertForbidden();
    }

    public function test_telechargement_refuse_sans_permission_de_lecture(): void
    {
        $employe = $this->employeDans($this->sdA1);
        $fichier = $this->fichierPour($employe);
        $user = $this->utilisateur(RoleName::RhJunior, [$this->sdA1]);
        Role::findByName('RH_JUNIOR')->revokePermissionTo('employes.fichiers.view');
        $user = $user->fresh();

        $this->actingAs($user)
            ->get(route('employes.fichiers.telecharger', [$employe, $fichier]))
            ->assertForbidden();
    }

    public function test_fichier_d_un_autre_employe_introuvable(): void
    {
        $employe = $this->employeDans($this->sdA1);
        $autre = $this->employeDans($this->sdA1);
        $fichier = $this->fichierPour($autre);
        $admin = $this->utilisateur(RoleName::Admin);

        $this->actingAs($admin)
            ->get(route('employes.fichiers.telecharger', [$employe, $fichier]))
            ->assertNotFound();
    }

    public function test_admin_et_rh_manager_telechargent_partout(): void
    {
        $employe = $this->employeDans($this->sdB1);
        $fichier = $this->fichierPour($employe);

        foreach (RoleName::globaux() as $role) {
            $this->actingAs($this->utilisateur($role))
                ->get(route('employes.fichiers.telecharger', [$employe, $fichier]))
                ->assertOk();
        }
    }

    public function test_invite_redirige_vers_la_connexion(): void
    {
        $employe = $this->employeDans($this->sdA1);
        $fichier = $this->fichierPour($employe);

        $this->get(route('employes.fichiers.telecharger', [$employe, $fichier]))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_le_disque_prive_n_est_pas_public(): void
    {
        $this->assertSame('private', config('filesystems.disks.private.visibility'));
        $this->assertFalse(config('filesystems.disks.private.serve'));
        $this->assertStringNotContainsString('public', (string) config('filesystems.disks.private.root'));
    }
}
