<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreerAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_un_admin_avec_mot_de_passe_robuste(): void
    {
        $this->artisan('dls:creer-admin')
            ->expectsQuestion('Nom complet', 'Admin DLS')
            ->expectsQuestion('Adresse e-mail', 'admin@dls.test')
            ->expectsQuestion('Mot de passe (12 caractères min., majuscules, minuscules, chiffres, symboles)', 'Str0ng!Passw0rd#')
            ->expectsQuestion('Confirmer le mot de passe', 'Str0ng!Passw0rd#')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@dls.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('ADMIN'));
        $this->assertTrue($admin->hasGlobalAccess());
        $this->assertNotSame('Str0ng!Passw0rd#', $admin->password);
    }

    public function test_refuse_un_mot_de_passe_faible(): void
    {
        $this->artisan('dls:creer-admin')
            ->expectsQuestion('Nom complet', 'Admin DLS')
            ->expectsQuestion('Adresse e-mail', 'admin@dls.test')
            ->expectsQuestion('Mot de passe (12 caractères min., majuscules, minuscules, chiffres, symboles)', 'faible')
            ->expectsQuestion('Confirmer le mot de passe', 'faible')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
