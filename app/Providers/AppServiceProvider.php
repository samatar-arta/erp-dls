<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\ModuleRh;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\Grade;
use App\Models\JourFerie;
use App\Models\SiteAffectation;
use App\Models\SiteDepartement;
use App\Models\SoldeConge;
use App\Models\Societe;
use App\Models\TypeContrat;
use App\Models\User;
use App\Policies\EmployePolicy;
use App\Services\Signature\SignatureInterne;
use App\Services\Signature\SignatureProviderInterface;
use App\Policies\EnregistrementRhPolicy;
use App\Policies\OrganisationPolicy;
use App\Policies\ReferentielPolicy;
use App\Policies\SanctionPolicy;
use App\Policies\SoldeCongePolicy;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Signature interne par défaut ; un fournisseur externe s'y substitue par simple changement de liaison.
        $this->app->bind(SignatureProviderInterface::class, SignatureInterne::class);
    }

    public function boot(): void
    {
        // Alias stables (document, conge, ...) pour le workflow polymorphe. Mapping non « enforced » :
        // User reste enregistré par son nom de classe (Spatie Permission), pour ne pas altérer les rôles existants.
        Relation::morphMap(ModuleRh::morphMap());

        foreach (ModuleRh::cases() as $module) {
            Gate::policy($module->enregistrementClass(), $module === ModuleRh::Sanction ? SanctionPolicy::class : EnregistrementRhPolicy::class);
        }

        // Types et modèles de chaque module : administrables comme les autres référentiels.
        foreach (ModuleRh::cases() as $module) {
            Gate::policy($module->typeClass(), ReferentielPolicy::class);
            Gate::policy($module->modeleClass(), ReferentielPolicy::class);
        }

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Employe::class, EmployePolicy::class);

        foreach ([Societe::class, SiteAffectation::class, Departement::class, SiteDepartement::class] as $modele) {
            Gate::policy($modele, OrganisationPolicy::class);
        }

        Gate::policy(SoldeConge::class, SoldeCongePolicy::class);

        foreach ([TypeContrat::class, Grade::class, JourFerie::class] as $modele) {
            Gate::policy($modele, ReferentielPolicy::class);
        }
    }
}
