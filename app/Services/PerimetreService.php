<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employe;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Calcul des périmètres accessibles (couples site/département affectés à l'utilisateur).
 * Les utilisateurs à accès global ne sont jamais restreints.
 */
class PerimetreService
{
    public function aAccesGlobal(User $user): bool
    {
        return $user->is_active && $user->hasGlobalAccess();
    }

    /**
     * Restreint une requête aux enregistrements dont (site, département) est dans le périmètre.
     */
    public function appliquer(
        Builder $query,
        User $user,
        string $colonneSite = 'site_id',
        string $colonneDepartement = 'departement_id',
    ): Builder {
        if ($this->aAccesGlobal($user)) {
            return $query;
        }

        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        $table = $query->getModel()->getTable();

        return $query->whereExists(function ($sub) use ($user, $table, $colonneSite, $colonneDepartement): void {
            $sub->select(DB::raw(1))
                ->from('site_departements as sd')
                ->join('site_departement_user as sdu', 'sdu.site_departement_id', '=', 'sd.id')
                ->where('sdu.user_id', $user->getKey())
                ->whereColumn('sd.site_id', "{$table}.{$colonneSite}")
                ->whereColumn('sd.departement_id', "{$table}.{$colonneDepartement}");
        });
    }

    /**
     * Restreint une requête sur un enregistrement métier (document, congé...) au périmètre de
     * l'employé concerné (relation « employe »).
     */
    public function appliquerViaEmploye(Builder $query, User $user): Builder
    {
        if ($this->aAccesGlobal($user)) {
            return $query;
        }

        return $query->whereHas('employe', fn (Builder $employes): Builder => $this->appliquer($employes, $user));
    }

    public function peutAffecter(User $user, int $siteId, int $departementId): bool
    {
        if ($this->aAccesGlobal($user)) {
            return true;
        }

        if (! $user->is_active) {
            return false;
        }

        return $user->siteDepartements()
            ->where('site_departements.site_id', $siteId)
            ->where('site_departements.departement_id', $departementId)
            ->exists();
    }

    public function peutAccederEmploye(User $user, Employe $employe): bool
    {
        return $this->peutAffecter($user, (int) $employe->site_id, (int) $employe->departement_id);
    }
}
