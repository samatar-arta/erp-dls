<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Connexions, déconnexions et échecs. Le mot de passe n'est jamais lu ; l'adresse e-mail saisie est
 * conservée pour repérer les tentatives sur un compte. Une panne du journal ne doit pas bloquer la connexion.
 */
class JournaliserAuthentification
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function handle(Login|Logout|Failed $evenement): void
    {
        try {
            match (true) {
                $evenement instanceof Login && $evenement->user instanceof User => $this->audit->journaliser(
                    'auth.connexion', $evenement->user, "Connexion de {$evenement->user->name}.", [], $evenement->user,
                ),
                $evenement instanceof Logout && $evenement->user instanceof User => $this->audit->journaliser(
                    'auth.deconnexion', $evenement->user, "Déconnexion de {$evenement->user->name}.", [], $evenement->user,
                ),
                $evenement instanceof Failed => $this->audit->journaliser(
                    'auth.echec', $evenement->user instanceof User ? $evenement->user : null, 'Échec de connexion.',
                    ['email' => (string) ($evenement->credentials['email'] ?? '')],
                    $evenement->user instanceof User ? $evenement->user : null,
                ),
                default => null,
            };
        } catch (Throwable $e) {
            Log::error('Journal d\'audit indisponible : '.$e->getMessage());
        }
    }
}
