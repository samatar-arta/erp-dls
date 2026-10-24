<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ModeCalcul;
use App\Models\ModelConge;
use App\Models\ModelDocument;
use App\Models\ModelEvaluation;
use App\Models\ModelMutation;
use App\Models\ModelPromotion;
use App\Models\ModelSanction;
use App\Models\NiveauSanction;
use App\Models\TypeConge;
use App\Models\TypeDocument;
use App\Models\TypeEvaluation;
use App\Models\TypeMutation;
use App\Models\TypePromotion;
use App\Models\TypeSanction;
use Illuminate\Database\Seeder;

/**
 * Types et modèles HTML par défaut des six modules. Idempotent.
 *
 * Variables utilisées dans les gabarits : liste blanche de TemplateRendererService (Phase 5),
 * qui inclut aussi <module>.reference pour chaque module.
 */
class ModulesRhSeeder extends Seeder
{
    public function run(): void
    {
        $this->documents();
        $this->conges();
        $this->sanctions();
        $this->evaluations();
        $this->promotions();
        $this->mutations();
    }

    private function documents(): void
    {
        $types = [
            ['ATTESTATION_TRAVAIL', 'Attestation de travail', 90, "atteste que {{ employe.name }}, matricule {{ employe.matricule }}, est employé(e) au sein de {{ employe.societe.name }} (département {{ employe.departement.name }}) en qualité de {{ employe.fonction }} depuis le {{ employe.date_embauche }}.<br>La présente attestation est délivrée pour servir et valoir ce que de droit."],
            ['CERTIFICAT_TRAVAIL', 'Certificat de travail', 90, "certifie que {{ employe.name }}, matricule {{ employe.matricule }}, a été employé(e) au sein de {{ employe.societe.name }} en qualité de {{ employe.fonction }}, depuis le {{ employe.date_embauche }}.<br>Le présent certificat est délivré pour servir et valoir ce que de droit."],
            ['ATTESTATION_SALAIRE', 'Attestation de salaire', 90, "atteste que {{ employe.name }}, matricule {{ employe.matricule }}, occupe la fonction de {{ employe.fonction }} au sein de {{ employe.societe.name }} depuis le {{ employe.date_embauche }} et perçoit une rémunération régulière.<br>La présente attestation est délivrée à la demande de l'intéressé(e)."],
            ['ATTESTATION_CONGE', 'Attestation de congé', null, "atteste que {{ employe.name }}, matricule {{ employe.matricule }}, {{ employe.fonction }}, bénéficie d'un congé conformément à la réglementation en vigueur.<br>La présente attestation est délivrée pour servir et valoir ce que de droit."],
            ['DECISION_ADMINISTRATIVE', 'Décision administrative', null, "décide, à l'égard de {{ employe.name }}, matricule {{ employe.matricule }}, {{ employe.fonction }} ({{ employe.departement.name }}), des dispositions suivantes : ........................................"],
        ];

        foreach ($types as [$code, $nom, $delai, $corps]) {
            $type = TypeDocument::query()->firstOrCreate(['code' => $code], [
                'name' => $nom,
                'requires_model' => true,
                'requires_workflow' => true,
                'delai_renouvellement_jours' => $delai,
            ]);
            $this->modele(ModelDocument::class, 'type_document_id', $type->id, $nom, $this->gabarit($nom, '{{ document.reference }}', '{{ document.date_production }}', $corps));
        }
    }

    private function conges(): void
    {
        $types = [
            ['ANNUEL', 'Congé annuel', '#16a34a', 30, false, ModeCalcul::Calendaire],
            ['MALADIE', 'Congé maladie', '#dc2626', 0, true, ModeCalcul::Calendaire],
            ['EXCEPTIONNEL', 'Congé exceptionnel', '#f59e0b', 0, false, ModeCalcul::Ouvre],
            ['SANS_SOLDE', 'Congé sans solde', '#6b7280', 0, false, ModeCalcul::Calendaire],
            ['MATERNITE', 'Congé de maternité', '#db2777', 0, true, ModeCalcul::Calendaire],
            ['PATERNITE', 'Congé de paternité', '#2563eb', 0, true, ModeCalcul::Calendaire],
        ];

        foreach ($types as [$code, $nom, $couleur, $jours, $justificatif, $mode]) {
            $type = TypeConge::query()->firstOrCreate(['code' => $code], [
                'name' => $nom,
                'color' => $couleur,
                'default_days_per_year' => $jours,
                'requires_justificatif' => $justificatif,
                'requires_workflow' => true,
                'mode_calcul' => $mode,
            ]);
            $corps = "accorde à {{ employe.name }}, matricule {{ employe.matricule }}, {{ employe.fonction }}, un {$nom} du {{ conge.date_debut }} au {{ conge.date_fin }}, soit {{ conge.nbre_jours }} jour(s).";
            $this->modele(ModelConge::class, 'type_conge_id', $type->id, $nom, $this->gabarit($nom, '{{ conge.reference }}', '{{ date_du_jour }}', $corps));
        }
    }

    private function sanctions(): void
    {
        $types = [
            ['ABSENCE_INJUSTIFIEE', 'Absence injustifiée', false],
            ['FAUTE_PROFESSIONNELLE', 'Faute professionnelle', false],
            ['FAUTE_INFRACTION', 'Faute / infraction', true],
        ];

        foreach ($types as [$code, $nom, $conseil]) {
            $type = TypeSanction::query()->firstOrCreate(['code' => $code], [
                'name' => $nom,
                'requires_conseil_discipline' => $conseil,
                'requires_workflow' => true,
            ]);
            $corps = "notifie à {{ employe.name }}, matricule {{ employe.matricule }}, {{ employe.fonction }} ({{ employe.departement.name }}), une sanction pour le motif « {$nom} » en date du {{ sanction.date_sanction }}.<br>{{ sanction.description }}";
            $this->modele(ModelSanction::class, 'type_sanction_id', $type->id, $nom, $this->gabarit('Notification de sanction — '.$nom, '{{ sanction.reference }}', '{{ date_du_jour }}', $corps));
        }

        foreach ([['AVERTISSEMENT', 'Avertissement', 1], ['BLAME', 'Blâme', 2], ['MISE_A_PIED', 'Mise à pied', 3]] as [$code, $nom, $ordre]) {
            NiveauSanction::query()->firstOrCreate(['code' => $code], ['name' => $nom, 'ordre' => $ordre]);
        }
    }

    private function evaluations(): void
    {
        $criteres = [
            ['libelle' => 'Qualité du travail', 'coefficient' => 3, 'note_max' => 20],
            ['libelle' => 'Respect des délais et ponctualité', 'coefficient' => 2, 'note_max' => 20],
            ['libelle' => 'Esprit d\'équipe', 'coefficient' => 2, 'note_max' => 20],
            ['libelle' => 'Initiative et autonomie', 'coefficient' => 1, 'note_max' => 20],
        ];

        $types = [
            ['ANNUELLE', 'Évaluation annuelle'],
            ['PERIODE_ESSAI', 'Évaluation de période d\'essai'],
            ['PERFORMANCE', 'Évaluation de performance'],
            ['EXCEPTIONNELLE', 'Évaluation exceptionnelle'],
        ];

        foreach ($types as [$code, $nom]) {
            $type = TypeEvaluation::query()->firstOrCreate(['code' => $code], ['name' => $nom]);
            $corps = "Évaluation de {{ employe.name }}, matricule {{ employe.matricule }}, {{ employe.fonction }} ({{ employe.departement.name }}), réalisée le {{ evaluation.date_evaluation }}.<br>Note finale pondérée : {{ evaluation.note }} / 20.<br>Détail : {{ evaluation.criteres }}";
            $this->modele(ModelEvaluation::class, 'type_evaluation_id', $type->id, $nom, $this->gabarit($nom, '{{ evaluation.reference }}', '{{ date_du_jour }}', $corps), ['criteres' => $criteres]);
        }
    }

    private function promotions(): void
    {
        foreach ([['GRADE', 'Promotion de grade'], ['FONCTION', 'Promotion de fonction'], ['SALARIALE', 'Promotion salariale'], ['GRADE_FONCTION', 'Promotion de grade et de fonction']] as [$code, $nom]) {
            $type = TypePromotion::query()->firstOrCreate(['code' => $code], ['name' => $nom]);
            $corps = "décide la promotion de {{ employe.name }}, matricule {{ employe.matricule }}, de la fonction de {{ promotion.ancienne_fonction }} à celle de {{ promotion.nouvelle_fonction }}.";
            $this->modele(ModelPromotion::class, 'type_promotion_id', $type->id, $nom, $this->gabarit($nom, '{{ promotion.reference }}', '{{ date_du_jour }}', $corps));
        }
    }

    private function mutations(): void
    {
        foreach ([['SITE', 'Mutation de site'], ['DEPARTEMENT', 'Mutation de département'], ['FONCTION', 'Mutation de fonction'], ['SOCIETE', 'Mutation de société'], ['SITE_DEPARTEMENT', 'Mutation de site et de département']] as [$code, $nom]) {
            $type = TypeMutation::query()->firstOrCreate(['code' => $code], ['name' => $nom]);
            $corps = "décide la mutation de {{ employe.name }}, matricule {{ employe.matricule }}, de la fonction de {{ mutation.ancienne_fonction }} à celle de {{ mutation.nouvelle_fonction }}.";
            $this->modele(ModelMutation::class, 'type_mutation_id', $type->id, $nom, $this->gabarit($nom, '{{ mutation.reference }}', '{{ date_du_jour }}', $corps));
        }
    }

    /**
     * Crée le modèle par défaut du type s'il n'en existe aucun.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $classe
     * @param  array<string, mixed>  $extra
     */
    private function modele(string $classe, string $cleType, int $typeId, string $titre, string $contenu, array $extra = []): void
    {
        if ($classe::query()->where($cleType, $typeId)->exists()) {
            return;
        }

        $classe::query()->create([
            $cleType => $typeId,
            'titre' => $titre,
            'contenu' => $contenu,
            'is_default' => true,
            'is_active' => true,
            ...$extra,
        ]);
    }

    private function gabarit(string $titre, string $reference, string $date, string $corps): string
    {
        return <<<HTML
<div style="font-family: DejaVu Sans, sans-serif; font-size: 12px;">
  <h2 style="text-align:center;">DLS — Djibouti Labour Service</h2>
  <p>Réf. : {$reference}<br>Djibouti, le {$date}</p>
  <h3 style="text-align:center; text-transform:uppercase;">{$titre}</h3>
  <p>La Direction des Ressources Humaines {$corps}</p>
</div>
HTML;
    }
}
