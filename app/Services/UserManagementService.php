<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Exceptions\RegleMetierException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Gestion des rôles, affectations et activation des comptes, avec garde-fous.
 */
class UserManagementService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /**
     * @param  list<string>  $roles
     */
    public function mettreAJourRoles(User $acteur, User $cible, array $roles): void
    {
        $this->interdireAutoModification($acteur, $cible, 'ses propres rôles');

        $roles = array_values(array_unique($roles));
        $invalides = array_diff($roles, RoleName::valeurs());
        if ($invalides !== []) {
            throw new RegleMetierException('Rôle inconnu : '.implode(', ', $invalides));
        }

        DB::transaction(function () use ($acteur, $cible, $roles): void {
            // Verrou pour sérialiser les retraits concurrents de rôles globaux.
            User::query()->whereIn('id', $this->idsGlobauxActifs())->lockForUpdate()->get();

            $perdAccesGlobal = $cible->is_active
                && $cible->hasGlobalAccess()
                && array_intersect($roles, RoleName::globauxValeurs()) === [];

            if ($perdAccesGlobal) {
                $this->verifierNonDernierAdmin($cible);
            }

            $avant = $cible->roles()->pluck('name')->sort()->values()->all();
            $cible->syncRoles($roles);
            $apres = collect($roles)->sort()->values()->all();

            if ($avant !== $apres) {
                $this->audit->journaliser('utilisateur.roles_modifies', $cible, "Rôles de {$cible->name} modifiés.", ['avant' => $avant, 'apres' => $apres], $acteur);
            }
        });
    }

    /**
     * @param  list<int>  $siteDepartementIds
     */
    public function mettreAJourAffectations(User $acteur, User $cible, array $siteDepartementIds): void
    {
        $this->interdireAutoModification($acteur, $cible, 'ses propres affectations');

        $avant = $cible->siteDepartements()->pluck('site_departements.id')->sort()->values()->all();
        $cible->siteDepartements()->sync($siteDepartementIds);
        $apres = collect($siteDepartementIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();

        if ($avant !== $apres) {
            $this->audit->journaliser('utilisateur.perimetre_modifie', $cible, "Périmètre de {$cible->name} modifié.", ['avant' => $avant, 'apres' => $apres], $acteur);
        }
    }

    public function definirActif(User $acteur, User $cible, bool $actif): void
    {
        if (! $actif && $acteur->is($cible)) {
            throw new RegleMetierException('Vous ne pouvez pas désactiver votre propre compte.');
        }

        DB::transaction(function () use ($acteur, $cible, $actif): void {
            if (! $actif) {
                User::query()->whereIn('id', $this->idsGlobauxActifs())->lockForUpdate()->get();
            }

            $cible->is_active = $actif;
            $cible->save();

            $this->audit->journaliser($actif ? 'utilisateur.active' : 'utilisateur.desactive', $cible, "Compte de {$cible->name} ".($actif ? 'activé' : 'désactivé').'.', [], $acteur);
        });
    }

    /**
     * Lève une exception si $user est le dernier compte actif à accès global.
     */
    public function verifierNonDernierAdmin(User $user): void
    {
        if (! $user->exists || ! $user->getOriginal('is_active') || ! $user->hasGlobalAccess()) {
            return;
        }

        $autres = User::query()
            ->whereKeyNot($user->getKey())
            ->where('is_active', true)
            ->whereIn('id', $this->idsGlobaux())
            ->count();

        if ($autres === 0) {
            throw new RegleMetierException(
                'Opération refusée : il doit rester au moins un compte ADMIN ou RH_MANAGER actif.'
            );
        }
    }

    private function interdireAutoModification(User $acteur, User $cible, string $objet): void
    {
        if ($acteur->is($cible)) {
            throw new AuthorizationException("Vous ne pouvez pas modifier {$objet}.");
        }
    }

    /**
     * @return list<int>
     */
    private function idsGlobaux(): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('roles.name', RoleName::globauxValeurs())
            ->pluck('model_has_roles.model_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function idsGlobauxActifs(): array
    {
        return User::query()->where('is_active', true)->whereIn('id', $this->idsGlobaux())->pluck('id')->all();
    }
}
