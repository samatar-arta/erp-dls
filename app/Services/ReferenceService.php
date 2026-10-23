<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModuleRh;
use App\Exceptions\RegleMetierException;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seul producteur de références (ex. 0001/DA_DARH/DLS/2026).
 *
 * Un compteur par module et par année, commun à tous les types du module. Le numéro n'est jamais
 * dérivé de l'identifiant technique. La séquence est verrouillée (SELECT ... FOR UPDATE) dans la
 * transaction de l'appelant : si celle-ci est annulée, le numéro est rendu (pas de trou).
 */
class ReferenceService
{
    public const FORMAT_PAR_DEFAUT = '{numero:4}/{prefixe}/{annee}';

    /**
     * Attribue le prochain numéro du module pour l'année de $date (par défaut : maintenant).
     * L'horloge est injectable via $date ou Carbon::setTestNow().
     */
    public function attribuer(ModuleRh $module, ?CarbonInterface $date = null): string
    {
        $annee = (int) ($date ?? Carbon::now())->year;

        return DB::transaction(function () use ($module, $annee): string {
            $maintenant = Carbon::now();

            // Une seule instruction atomique crée la séquence ou l'incrémente. Elle prend directement un
            // verrou exclusif (pas de verrou partagé à promouvoir), ce qui évite les interblocages que
            // provoquerait « INSERT IGNORE puis SELECT ... FOR UPDATE » sous forte concurrence.
            DB::statement(
                'INSERT INTO reference_sequences (module, annee, dernier_numero, created_at, updated_at)
                 VALUES (?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE dernier_numero = dernier_numero + 1, updated_at = VALUES(updated_at)',
                [$module->value, $annee, $maintenant, $maintenant],
            );

            // Lecture verrouillante (dernière valeur validée) : le verrou est déjà détenu, aucune attente.
            $numero = (int) DB::table('reference_sequences')
                ->where('module', $module->value)
                ->where('annee', $annee)
                ->lockForUpdate()
                ->value('dernier_numero');

            return $this->formater($module, $numero, $annee);
        });
    }

    public function formater(ModuleRh $module, int $numero, int $annee): string
    {
        ['prefixe' => $prefixe, 'format' => $format] = $this->configuration($module);

        $resultat = preg_replace_callback(
            '/\{numero:(\d+)\}/',
            static fn (array $m): string => str_pad((string) $numero, (int) $m[1], '0', STR_PAD_LEFT),
            $format,
        );

        return str_replace(['{prefixe}', '{annee}'], [$prefixe, (string) $annee], (string) $resultat);
    }

    /**
     * @return array{prefixe: string, format: string}
     */
    public function configuration(ModuleRh $module): array
    {
        $ligne = DB::table('reference_modules')->where('module', $module->value)->first();

        return [
            'prefixe' => $ligne->prefixe ?? $module->prefixeParDefaut(),
            'format' => $ligne->format ?? self::FORMAT_PAR_DEFAUT,
        ];
    }

    /**
     * Modifie le préfixe / le format pour les prochaines références. Les références déjà attribuées
     * ne changent jamais.
     */
    public function configurer(ModuleRh $module, string $prefixe, string $format = self::FORMAT_PAR_DEFAUT): void
    {
        if (preg_match('#^[A-Za-z0-9_/\-]{1,40}$#', $prefixe) !== 1) {
            throw new RegleMetierException('Préfixe invalide : 40 caractères maximum (lettres, chiffres, _ / -).');
        }

        if (preg_match('/\{numero:([1-8])\}/', $format) !== 1
            || ! str_contains($format, '{annee}')
            || ! str_contains($format, '{prefixe}')) {
            throw new RegleMetierException('Format invalide : {numero:N} (N de 1 à 8), {prefixe} et {annee} sont obligatoires.');
        }

        $maintenant = Carbon::now();

        DB::table('reference_modules')->updateOrInsert(
            ['module' => $module->value],
            ['prefixe' => $prefixe, 'format' => $format, 'updated_at' => $maintenant, 'created_at' => $maintenant],
        );
    }
}
