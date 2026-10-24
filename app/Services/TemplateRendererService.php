<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModuleRh;
use App\Services\Signature\SignatureProviderInterface;
use App\Support\HtmlSanitizer;
use App\Support\VariablesModele;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Remplace les variables {{ nom }} d'un modèle par les valeurs de l'enregistrement.
 *
 * Sécurité : le modèle n'est jamais compilé (ni Blade ni PHP). Seules les variables de la liste blanche
 * (VariablesModele) sont lues ; chaque valeur est échappée en HTML. Une variable inconnue donne une
 * valeur vide et est refusée à l'enregistrement du modèle.
 */
class TemplateRendererService
{
    private const MOTIF = '/\{\{\s*([A-Za-z_][A-Za-z0-9_.]*)\s*\}\}/';

    public function __construct(private readonly SignatureProviderInterface $signature)
    {
    }

    /**
     * HTML final d'un enregistrement. Le bloc de signature est ajouté à la fin si le modèle ne le place pas lui-même.
     */
    public function rendre(Model $enregistrement, string $contenu, ?Carbon $date = null): string
    {
        $module = ModuleRh::pourEnregistrement($enregistrement);
        $variables = VariablesModele::pour($module);
        $date ??= Carbon::now();
        $aSignature = false;

        $html = (string) preg_replace_callback(self::MOTIF, function (array $m) use ($enregistrement, $module, $variables, $date, &$aSignature): string {
            $nom = $m[1];

            if ($nom === VariablesModele::SIGNATURE) {
                $aSignature = true;

                return $this->signature->bloc($enregistrement);
            }

            if ($nom === VariablesModele::DATE_DU_JOUR) {
                return e($date->format('d/m/Y'));
            }

            if (! isset($variables[$nom])) {
                return '';
            }

            return e($this->formater(data_get($enregistrement, $variables[$nom]), $nom === $module->alias().'.reference'));
        }, $contenu);

        if (! $aSignature) {
            $html .= $this->signature->bloc($enregistrement);
        }

        return $this->envelopper(HtmlSanitizer::nettoyer($html));
    }

    /**
     * Aperçu d'un modèle : chaque variable est remplacée par son nom entre crochets.
     */
    public function apercu(ModuleRh $module, string $contenu): string
    {
        $html = (string) preg_replace_callback(self::MOTIF, static function (array $m) use ($module): string {
            return in_array($m[1], VariablesModele::noms($module), true)
                ? '<span style="background:#fef3c7;">['.e($m[1]).']</span>'
                : '<span style="background:#fecaca;">[variable inconnue : '.e($m[1]).']</span>';
        }, $contenu);

        return $this->envelopper(HtmlSanitizer::nettoyer($html));
    }

    /**
     * Variables d'un contenu qui ne figurent pas dans la liste blanche du module.
     *
     * @return list<string>
     */
    public function variablesInconnues(ModuleRh $module, string $contenu): array
    {
        preg_match_all(self::MOTIF, $contenu, $trouvees);

        return array_values(array_unique(array_diff($trouvees[1], VariablesModele::noms($module))));
    }

    private function formater(mixed $valeur, bool $estReference): string
    {
        if ($valeur === null || $valeur === '') {
            return $estReference ? '(référence en attente)' : '';
        }

        if ($valeur instanceof DateTimeInterface) {
            return $valeur->format('d/m/Y');
        }

        if (is_numeric($valeur) && str_contains((string) $valeur, '.')) {
            return rtrim(rtrim((string) $valeur, '0'), '.');
        }

        return (string) $valeur;
    }

    private function envelopper(string $corps): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;}</style></head><body>'.$corps.'</body></html>';
    }
}
