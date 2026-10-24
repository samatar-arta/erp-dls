<?php

declare(strict_types=1);

namespace App\Services\Signature;

use Illuminate\Database\Eloquent\Model;

/**
 * Point d'extension de la signature. L'implémentation interne appose un bloc de validation
 * électronique ; un fournisseur externe (certificat, parapheur) pourra la remplacer sans toucher au reste.
 */
interface SignatureProviderInterface
{
    /**
     * Bloc HTML de signature d'un enregistrement approuvé (déjà échappé, prêt à insérer).
     */
    public function bloc(Model $enregistrement): string;

    /**
     * Vrai si l'empreinte correspond bien à l'enregistrement approuvé.
     */
    public function verifier(Model $enregistrement, string $empreinte): bool;
}
