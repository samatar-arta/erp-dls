<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModuleRh;
use App\Enums\WorkflowStatut;
use App\Exceptions\RegleMetierException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Génère le PDF d'un enregistrement approuvé. Idempotent :
 *  - la référence n'est jamais touchée ;
 *  - un PDF déjà généré (fichier présent, empreinte valide) n'est jamais régénéré ;
 *  - le contenu HTML est figé à la première génération (contenu_genere, version_modele) : un nouvel essai
 *    après échec produit le même document, même si le modèle a changé entre-temps.
 */
class GenerationPdfService
{
    public const DISQUE = 'private';

    public function __construct(private readonly TemplateRendererService $rendu)
    {
    }

    /**
     * Marque l'enregistrement « en attente de génération » (appelé dans la transaction d'approbation).
     */
    public function planifier(Model $enregistrement): void
    {
        if ($enregistrement->getAttribute(ModuleRh::pourEnregistrement($enregistrement)->cleModele()) === null) {
            return; // type sans modèle : rien à générer
        }

        $enregistrement->forceFill(['statut_generation' => 'en_attente'])->save();
    }

    /**
     * @return bool vrai si un PDF a été produit par cet appel, faux s'il existait déjà
     */
    public function generer(Model $enregistrement): bool
    {
        $module = ModuleRh::pourEnregistrement($enregistrement);

        try {
            return DB::transaction(function () use ($enregistrement, $module): bool {
                $enregistrement = $enregistrement->newQuery()->with('workflow')->lockForUpdate()->findOrFail($enregistrement->getKey());

                if ($enregistrement->workflow?->statut !== WorkflowStatut::Approuve || $enregistrement->reference === null) {
                    throw new RegleMetierException('Le PDF ne se génère que pour un enregistrement approuvé et référencé.');
                }

                if ($this->pdfValide($enregistrement)) {
                    return false;
                }

                $modele = $enregistrement->getAttribute($module->cleModele()) === null
                    ? null
                    : $module->modeleClass()::query()->find($enregistrement->getAttribute($module->cleModele()));

                if ($modele === null) {
                    throw new RegleMetierException('Aucun modèle n\'est rattaché à cet enregistrement.');
                }

                // Contenu figé à la première génération.
                if (blank($enregistrement->contenu_genere)) {
                    $enregistrement->forceFill([
                        'contenu_genere' => $this->rendu->rendre($enregistrement, (string) $modele->contenu),
                        'version_modele' => $modele->version,
                    ])->save();
                }

                $chemin = $this->chemin($module, $enregistrement);
                $binaire = Pdf::loadHTML($enregistrement->contenu_genere)->setPaper('a4')->output();
                Storage::disk(self::DISQUE)->put($chemin, $binaire, 'private');

                $enregistrement->forceFill([
                    'fichier_path' => $chemin,
                    'empreinte_pdf' => hash('sha256', $binaire),
                    'statut_generation' => 'genere',
                    'genere_le' => Carbon::now(),
                ])->save();

                return true;
            });
        } catch (Throwable $e) {
            if (! $e instanceof RegleMetierException) {
                // Hors transaction : l'échec reste visible et la génération peut être relancée.
                $enregistrement->newQuery()->whereKey($enregistrement->getKey())->update(['statut_generation' => 'echec']);
            }

            throw $e;
        }
    }

    /**
     * Le fichier existe et son empreinte correspond : intégrité de l'archive.
     */
    public function pdfValide(Model $enregistrement): bool
    {
        $chemin = $enregistrement->getAttribute('fichier_path');
        $empreinte = $enregistrement->getAttribute('empreinte_pdf');

        if ($chemin === null || $empreinte === null || $enregistrement->getAttribute('statut_generation') !== 'genere') {
            return false;
        }

        $disque = Storage::disk(self::DISQUE);

        return $disque->exists($chemin) && hash_equals($empreinte, hash('sha256', (string) $disque->get($chemin)));
    }

    /** Chemin déterministe : même enregistrement, même fichier. */
    public function chemin(ModuleRh $module, Model $enregistrement): string
    {
        $slug = str_replace(['/', '\\'], '-', (string) $enregistrement->reference);

        return sprintf('documents-generes/%s/%s.pdf', $module->alias(), $slug);
    }
}
