<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Employe;
use App\Models\EmployeFichier;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeFichierController extends Controller
{
    /**
     * Téléchargement authentifié et autorisé d'un fichier du dossier numérique (disque privé).
     */
    public function telecharger(Employe $employe, EmployeFichier $fichier): StreamedResponse
    {
        Gate::authorize('viewFichiers', $employe);

        abort_unless(
            $fichier->employe_id === $employe->getKey() && Storage::disk('private')->exists($fichier->path),
            404,
        );

        return Storage::disk('private')->download($fichier->path, $fichier->nom_original);
    }
}
