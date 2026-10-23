<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Employe;
use App\Models\EmployeAffectation;
use App\Models\SiteDepartement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class EmployeService
{
    public function __construct(private readonly PerimetreService $perimetre)
    {
    }

    /**
     * Crée un employé et ouvre sa première ligne d'historique d'affectation.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function creer(array $donnees, User $acteur): Employe
    {
        $this->verifierAffectationAutorisee($acteur, (int) $donnees['societe_id'], (int) $donnees['site_id'], (int) $donnees['departement_id']);

        return DB::transaction(function () use ($donnees, $acteur): Employe {
            $employe = new Employe($donnees);
            $employe->cree_par = $acteur->getKey();
            $employe->save();

            $employe->affectations()->create([
                'societe_id' => $employe->societe_id,
                'site_id' => $employe->site_id,
                'departement_id' => $employe->departement_id,
                'fonction' => $employe->fonction,
                'date_debut' => $employe->date_embauche,
                'source' => 'creation',
                'cree_par' => $acteur->getKey(),
            ]);

            return $employe;
        });
    }

    /**
     * Change l'affectation courante : clôture la ligne ouverte, en ouvre une nouvelle, met à jour l'employé.
     * Ne jamais écraser l'historique. Réutilisé par les mutations et promotions approuvées.
     *
     * @param  array{societe_id: int, site_id: int, departement_id: int, fonction?: ?string}  $nouvelle
     */
    public function changerAffectation(
        Employe $employe,
        array $nouvelle,
        CarbonInterface $dateEffet,
        string $source,
        ?User $acteur = null,
    ): Employe {
        $this->verifierCoupleExiste((int) $nouvelle['societe_id'], (int) $nouvelle['site_id'], (int) $nouvelle['departement_id']);

        return DB::transaction(function () use ($employe, $nouvelle, $dateEffet, $source, $acteur): Employe {
            $employe = Employe::query()->lockForUpdate()->findOrFail($employe->getKey());

            $courante = $employe->affectationCourante()->first();
            if ($courante !== null) {
                if ($dateEffet->lt($courante->date_debut)) {
                    throw new RegleMetierException("La date d'effet précède le début de l'affectation courante.");
                }
                $courante->update(['date_fin' => $dateEffet->toDateString()]);
            }

            $fonction = array_key_exists('fonction', $nouvelle) ? $nouvelle['fonction'] : $employe->fonction;

            EmployeAffectation::query()->create([
                'employe_id' => $employe->getKey(),
                'societe_id' => $nouvelle['societe_id'],
                'site_id' => $nouvelle['site_id'],
                'departement_id' => $nouvelle['departement_id'],
                'fonction' => $fonction,
                'date_debut' => $dateEffet->toDateString(),
                'source' => $source,
                'cree_par' => $acteur?->getKey(),
            ]);

            Employe::$autoriserChangementAffectation = true;
            try {
                $employe->forceFill([
                    'societe_id' => $nouvelle['societe_id'],
                    'site_id' => $nouvelle['site_id'],
                    'departement_id' => $nouvelle['departement_id'],
                    'fonction' => $fonction,
                ])->save();
            } finally {
                Employe::$autoriserChangementAffectation = false;
            }

            return $employe;
        });
    }

    public function verifierAffectationAutorisee(User $acteur, int $societeId, int $siteId, int $departementId): void
    {
        if (! $this->perimetre->peutAffecter($acteur, $siteId, $departementId)) {
            throw new AuthorizationException('Ce site / département est hors de votre périmètre.');
        }

        $this->verifierCoupleExiste($societeId, $siteId, $departementId);
    }

    private function verifierCoupleExiste(int $societeId, int $siteId, int $departementId): void
    {
        $existe = SiteDepartement::query()
            ->where('societe_id', $societeId)
            ->where('site_id', $siteId)
            ->where('departement_id', $departementId)
            ->exists();

        if (! $existe) {
            throw new RegleMetierException("Ce département n'est pas ouvert sur ce site pour cette société.");
        }
    }
}
