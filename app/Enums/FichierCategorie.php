<?php

declare(strict_types=1);

namespace App\Enums;

enum FichierCategorie: string
{
    case Personnel = 'personnel';
    case Contrats = 'contrats';
    case Diplomes = 'diplomes';
    case Sanctions = 'sanctions';
    case Conges = 'conges';
    case Formations = 'formations';
    case Evaluations = 'evaluations';
    case Promotions = 'promotions';
    case Mutations = 'mutations';

    public function label(): string
    {
        return match ($this) {
            self::Personnel => 'Personnel',
            self::Contrats => 'Contrats et attestations',
            self::Diplomes => 'Diplômes et certificats',
            self::Sanctions => 'Sanctions',
            self::Conges => 'Congés et absences',
            self::Formations => 'Formations',
            self::Evaluations => 'Évaluations',
            self::Promotions => 'Promotions',
            self::Mutations => 'Mutations',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $categorie) {
            $options[$categorie->value] = $categorie->label();
        }

        return $options;
    }
}
