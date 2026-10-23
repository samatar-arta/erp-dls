<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Departement;
use App\Models\Employe;
use App\Models\SiteAffectation;
use App\Models\SiteDepartement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employe>
 *
 * Crée l'employé et sa ligne d'historique ouverte. Utiliser pourSiteDepartement() pour fixer le périmètre.
 */
class EmployeFactory extends Factory
{
    protected $model = Employe::class;

    public function definition(): array
    {
        return [
            'matricule' => 'M'.fake()->unique()->numerify('######'),
            'name' => fake()->name(),
            'fonction' => fake()->jobTitle(),
            'date_naissance' => fake()->dateTimeBetween('-55 years', '-22 years'),
            'date_embauche' => fake()->dateTimeBetween('-8 years', '-1 month'),
            'statut_employe' => 'actif',
            'telephone' => fake()->numerify('77######'),
            'handicap' => false,
            'societe_id' => null,
            'site_id' => null,
            'departement_id' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Employe $employe): void {
            if ($employe->site_id === null) {
                $sd = SiteDepartement::query()->inRandomOrder()->first()
                    ?? $this->nouveauSiteDepartement();
                $employe->forceFill([
                    'societe_id' => $sd->societe_id,
                    'site_id' => $sd->site_id,
                    'departement_id' => $sd->departement_id,
                ]);
            }
        })->afterCreating(function (Employe $employe): void {
            $employe->affectations()->create([
                'societe_id' => $employe->societe_id,
                'site_id' => $employe->site_id,
                'departement_id' => $employe->departement_id,
                'fonction' => $employe->fonction,
                'date_debut' => $employe->date_embauche,
                'source' => 'creation',
            ]);
        });
    }

    public function pourSiteDepartement(SiteDepartement $sd): static
    {
        return $this->state(fn (): array => [
            'societe_id' => $sd->societe_id,
            'site_id' => $sd->site_id,
            'departement_id' => $sd->departement_id,
        ]);
    }

    private function nouveauSiteDepartement(): SiteDepartement
    {
        $departement = Departement::query()->first()
            ?? Departement::query()->create([
                'name' => 'Département '.fake()->unique()->word(),
                'societe_id' => \App\Models\Societe::query()->create(['name' => fake()->company()])->id,
            ]);
        $site = SiteAffectation::query()->create(['name' => 'Site '.fake()->unique()->city()]);

        return SiteDepartement::query()->create([
            'site_id' => $site->id,
            'societe_id' => $departement->societe_id,
            'departement_id' => $departement->id,
        ]);
    }
}
