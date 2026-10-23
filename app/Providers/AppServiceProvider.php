<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Departement;
use App\Models\Employe;
use App\Models\Grade;
use App\Models\SiteAffectation;
use App\Models\SiteDepartement;
use App\Models\Societe;
use App\Models\TypeContrat;
use App\Models\User;
use App\Policies\EmployePolicy;
use App\Policies\OrganisationPolicy;
use App\Policies\ReferentielPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Employe::class, EmployePolicy::class);

        foreach ([Societe::class, SiteAffectation::class, Departement::class, SiteDepartement::class] as $modele) {
            Gate::policy($modele, OrganisationPolicy::class);
        }

        foreach ([TypeContrat::class, Grade::class] as $modele) {
            Gate::policy($modele, ReferentielPolicy::class);
        }
    }
}
