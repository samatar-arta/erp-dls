<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ModuleRh;
use App\Services\GenerationPdfService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfEnregistrementController extends Controller
{
    /**
     * Téléchargement authentifié du PDF archivé : droit de lecture du module + périmètre (policy `view`),
     * fichier sur le disque privé, intégrité contrôlée par l'empreinte.
     */
    public function telecharger(string $module, int $id, GenerationPdfService $service): StreamedResponse
    {
        $moduleRh = ModuleRh::tryFrom(strtoupper($module));
        abort_if($moduleRh === null, 404);

        $enregistrement = $moduleRh->enregistrementClass()::query()->findOrFail($id);

        Gate::authorize('view', $enregistrement);

        abort_unless($service->pdfValide($enregistrement), 404, 'PDF non disponible.');

        $nom = str_replace(['/', '\\'], '-', (string) $enregistrement->reference).'.pdf';

        return Storage::disk(GenerationPdfService::DISQUE)->download(
            $enregistrement->fichier_path,
            $nom,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
