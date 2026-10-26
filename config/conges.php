<?php

declare(strict_types=1);

return [
    /*
    | Jours de la semaine non ouvrés (numéro ISO : 1 = lundi ... 7 = dimanche).
    | Par défaut : vendredi et samedi (week-end officiel à Djibouti). Liste séparée par des virgules dans CONGES_JOURS_NON_OUVRES.
    */
    'jours_non_ouvres' => array_map('intval', array_filter(explode(',', (string) env('CONGES_JOURS_NON_OUVRES', '5,6')))),
];
