<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Enums\WorkflowStatut;
use Illuminate\Database\Eloquent\Model;

/**
 * Signature interne : mention du validateur, de l'approbateur et de la date, avec une empreinte HMAC
 * (clé de l'application) liant la référence, l'agent et l'approbation. Ce n'est pas une signature
 * qualifiée : elle atteste de l'approbation dans l'ERP et permet de détecter une altération.
 */
class SignatureInterne implements SignatureProviderInterface
{
    public function bloc(Model $enregistrement): string
    {
        $workflow = $enregistrement->workflow;

        if ($workflow === null || $workflow->statut !== WorkflowStatut::Approuve) {
            return '';
        }

        $validateur = e((string) ($workflow->validateur?->name ?? '—'));
        $approbateur = e((string) ($workflow->approbateur?->name ?? '—'));
        $date = e($workflow->updated_at?->format('d/m/Y H:i') ?? '');
        $empreinte = e(substr($this->empreinte($enregistrement), 0, 16));

        return '<div style="margin-top:40px; border-top:1px solid #444; padding-top:8px; font-size:10px;">'
            .'<strong>Approuvé électroniquement</strong> par '.$approbateur.' le '.$date
            .' (validé par '.$validateur.').<br>'
            .'Référence : '.e((string) $enregistrement->getAttribute('reference')).' — Empreinte : '.$empreinte
            .'</div>';
    }

    public function verifier(Model $enregistrement, string $empreinte): bool
    {
        return hash_equals($this->empreinte($enregistrement), $empreinte);
    }

    public function empreinte(Model $enregistrement): string
    {
        $workflow = $enregistrement->workflow;

        return hash_hmac('sha256', implode('|', [
            $enregistrement->getAttribute('reference'),
            $enregistrement->getAttribute('matricule'),
            $enregistrement->getMorphClass(),
            $enregistrement->getKey(),
            $workflow?->approuve_par_id,
        ]), (string) config('app.key'));
    }
}
