<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\ModelEvaluation;

/**
 * Notation d'une évaluation : notes par critère (critères définis par le modèle) et note finale pondérée
 * ramenée sur 20. Le résultat est toujours calculé par le serveur, jamais saisi.
 */
class EvaluationService
{
    public const NOTE_MAXIMALE = 20;

    /**
     * @param  mixed  $saisie  liste de lignes ['libelle' => ..., 'note' => ...]
     * @return array{notes_criteres: ?list<array{libelle: string, coefficient: float, note_max: float, note: float}>, note: ?float}
     */
    public function noter(?ModelEvaluation $modele, mixed $saisie): array
    {
        $criteres = array_values((array) ($modele?->criteres ?? []));

        // Un modèle sans critères n'a pas de note chiffrée.
        if ($criteres === []) {
            return ['notes_criteres' => null, 'note' => null];
        }

        $notes = [];
        foreach ((array) $saisie as $ligne) {
            $notes[(string) ($ligne['libelle'] ?? '')] = $ligne['note'] ?? null;
        }

        $connus = array_map(static fn (array $c): string => (string) $c['libelle'], $criteres);
        $inconnus = array_diff(array_keys($notes), $connus);
        if ($inconnus !== []) {
            throw new RegleMetierException('Critère inconnu pour ce modèle : '.implode(', ', $inconnus).'.');
        }

        $detail = [];
        $somme = 0.0;
        $coefficients = 0.0;

        foreach ($criteres as $critere) {
            $libelle = (string) $critere['libelle'];
            $max = (float) ($critere['note_max'] ?? self::NOTE_MAXIMALE);
            $coefficient = (float) ($critere['coefficient'] ?? 1);
            $note = $notes[$libelle] ?? null;

            if ($note === null || $note === '' || ! is_numeric($note)) {
                throw new RegleMetierException("La note du critère « {$libelle} » est obligatoire.");
            }

            $note = (float) $note;
            if ($note < 0 || $note > $max) {
                throw new RegleMetierException("La note du critère « {$libelle} » doit être comprise entre 0 et ".$this->nombre($max).'.');
            }
            if ($max <= 0 || $coefficient <= 0) {
                throw new RegleMetierException("Le critère « {$libelle} » du modèle est mal configuré (note maximale et coefficient > 0).");
            }

            $detail[] = ['libelle' => $libelle, 'coefficient' => $coefficient, 'note_max' => $max, 'note' => $note];
            $somme += ($note / $max) * self::NOTE_MAXIMALE * $coefficient;
            $coefficients += $coefficient;
        }

        return ['notes_criteres' => $detail, 'note' => round($somme / $coefficients, 2)];
    }

    private function nombre(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 2, ',', ''), '0'), ',');
    }
}
