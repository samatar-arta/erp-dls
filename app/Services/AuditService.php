<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employe;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Écriture du journal d'audit. Jamais de mot de passe, de pièce d'identité, de téléphone, d'adresse ni de
 * salaire : les clés sensibles sont retirées des données, et les appelants ne mettent que des identifiants
 * et des noms de champs (pas les valeurs) dans la description.
 */
class AuditService
{
    /** @var list<string> */
    private const CLES_SENSIBLES = [
        'password', 'mot_de_passe', 'remember_token', 'token', 'secret',
        'num_cni', 'num_cnss', 'telephone', 'adresse', 'salaire', 'ancien_salaire', 'nouveau_salaire',
        'contenu', 'contenu_genere',
    ];

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function journaliser(string $action, ?Model $cible = null, string $description = '', array $donnees = [], ?User $acteur = null): AuditLog
    {
        $acteur ??= auth()->user() instanceof User ? auth()->user() : null;
        $requete = app()->runningInConsole() ? null : request();

        $log = new AuditLog;
        $log->forceFill([
            'user_id' => $acteur?->getKey(),
            'acteur' => $acteur?->name,
            'action' => $action,
            'cible_type' => $cible !== null ? $this->typeCible($cible) : null,
            'cible_id' => $cible !== null ? $this->idCible($cible) : null,
            'description' => Str::limit($description !== '' ? $description : $action, 500, ''),
            'donnees' => $donnees === [] ? null : $this->assainir($donnees),
            'adresse_ip' => $requete?->ip(),
            'user_agent' => $requete !== null ? Str::limit((string) $requete->userAgent(), 255, '') : null,
        ])->save();

        return $log;
    }

    private function typeCible(Model $cible): string
    {
        $alias = $cible->getMorphClass();

        return class_exists($alias) ? Str::snake(class_basename($alias)) : $alias;
    }

    private function idCible(Model $cible): string
    {
        // Les agents sont identifiés par leur matricule.
        return (string) ($cible instanceof Employe ? $cible->matricule : $cible->getKey());
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function assainir(array $donnees): array
    {
        $propre = [];

        foreach ($donnees as $cle => $valeur) {
            if (is_string($cle) && in_array(Str::lower($cle), self::CLES_SENSIBLES, true)) {
                continue;
            }

            $propre[$cle] = is_array($valeur) ? $this->assainir($valeur) : $valeur;
        }

        return $propre;
    }
}
