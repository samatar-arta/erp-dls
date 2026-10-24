<?php

declare(strict_types=1);

use App\Http\Controllers\EmployeFichierController;
use App\Http\Controllers\PdfEnregistrementController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::middleware('auth')->group(function (): void {
    Route::get('employes/{employe}/fichiers/{fichier}', [EmployeFichierController::class, 'telecharger'])
        ->name('employes.fichiers.telecharger');

    Route::get('rh/{module}/{id}/pdf', [PdfEnregistrementController::class, 'telecharger'])
        ->whereNumber('id')
        ->name('enregistrements.pdf');
});
