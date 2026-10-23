<?php

declare(strict_types=1);

return [
    /*
    | Délai de traitement d'une étape (soumission, validation, approbation), en heures.
    | Au-delà, l'enregistrement passe à EXPIRE (commande planifiée, Phase 8).
    */
    'delai_heures' => (int) env('WORKFLOW_DELAI_HEURES', 48),

    /*
    | Renforcement optionnel : la personne qui approuve doit être différente de celle qui a validé.
    | La règle de base (nul ne valide ni n'approuve ce qu'il a créé ou soumis) est toujours active,
    | pour tous les rôles, et n'est pas configurable.
    */
    'approbateur_different_validateur' => (bool) env('WORKFLOW_APPROBATEUR_DIFFERENT', false),
];
