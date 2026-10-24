<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ModuleRh;

/**
 * Liste blanche des variables autorisées dans les modèles HTML : « nom dans le modèle » => chemin de lecture
 * sur l'enregistrement. Rien d'autre n'est jamais lu (pas de salaires, pas d'identifiants personnels sensibles,
 * pas d'appel de méthode). Variables spéciales : date_du_jour et signature (voir TemplateRendererService).
 */
final class VariablesModele
{
    public const DATE_DU_JOUR = 'date_du_jour';

    public const SIGNATURE = 'signature';

    /**
     * @return array<string, string>
     */
    public static function pour(ModuleRh $module): array
    {
        $alias = $module->alias();

        $communes = [
            'employe.name' => 'employe.name',
            'employe.matricule' => 'employe.matricule',
            'employe.fonction' => 'employe.fonction',
            'employe.date_embauche' => 'employe.date_embauche',
            'employe.date_naissance' => 'employe.date_naissance',
            'employe.societe.name' => 'employe.societe.name',
            'employe.site.name' => 'employe.site.name',
            'employe.departement.name' => 'employe.departement.name',
            "{$alias}.reference" => 'reference',
        ];

        $specifiques = match ($module) {
            ModuleRh::Document => ['date_production' => 'date_production', 'commentaire' => 'commentaire'],
            ModuleRh::Conge => ['date_debut' => 'date_debut', 'date_fin' => 'date_fin', 'nbre_jours' => 'nbre_jours', 'motif' => 'motif'],
            ModuleRh::Sanction => ['date_sanction' => 'date_sanction', 'description' => 'description', 'motif' => 'motif', 'niveau' => 'niveau.name'],
            ModuleRh::Evaluation => ['date_evaluation' => 'date_evaluation', 'note' => 'note', 'appreciation' => 'appreciation', 'objectifs' => 'objectifs'],
            ModuleRh::Promotion => ['ancienne_fonction' => 'ancienne_fonction', 'nouvelle_fonction' => 'nouvelle_fonction', 'nouveau_grade' => 'nouveauGrade.name', 'date_effet' => 'date_effet', 'motif' => 'motif'],
            ModuleRh::Mutation => [
                'ancienne_fonction' => 'ancienne_fonction', 'nouvelle_fonction' => 'nouvelle_fonction',
                'ancien_site' => 'ancienSite.name', 'nouveau_site' => 'nouveauSite.name',
                'ancien_departement' => 'ancienDepartement.name', 'nouveau_departement' => 'nouveauDepartement.name',
                'ancienne_societe' => 'ancienneSociete.name', 'nouvelle_societe' => 'nouvelleSociete.name',
                'date_effet' => 'date_effet', 'motif' => 'motif',
            ],
        };

        foreach ($specifiques as $nom => $chemin) {
            $communes["{$alias}.{$nom}"] = $chemin;
        }

        return $communes;
    }

    /**
     * Noms de toutes les variables acceptées pour le module (y compris les spéciales).
     *
     * @return list<string>
     */
    public static function noms(ModuleRh $module): array
    {
        return [...array_keys(self::pour($module)), self::DATE_DU_JOUR, self::SIGNATURE];
    }
}
