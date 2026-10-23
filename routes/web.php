<?php

declare(strict_types=1);

use App\Http\Controllers\EmployeFichierController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::middleware('auth')->group(function (): void {
    Route::get('employes/{employe}/fichiers/{fichier}', [EmployeFichierController::class, 'telecharger'])
        ->name('employes.fichiers.telecharger');
});
