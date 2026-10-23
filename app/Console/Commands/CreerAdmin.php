<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class CreerAdmin extends Command
{
    protected $signature = 'dls:creer-admin';

    protected $description = "Crée un compte ADMIN de façon interactive (nom, e-mail, mot de passe).";

    public function handle(): int
    {
        $nom = (string) $this->ask('Nom complet');
        $email = (string) $this->ask('Adresse e-mail');
        $motDePasse = (string) $this->secret('Mot de passe (12 caractères min., majuscules, minuscules, chiffres, symboles)');
        $confirmation = (string) $this->secret('Confirmer le mot de passe');

        $validateur = Validator::make(
            ['name' => $nom, 'email' => $email, 'password' => $motDePasse, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            ],
        );

        if ($validateur->fails()) {
            foreach ($validateur->errors()->all() as $erreur) {
                $this->error($erreur);
            }

            return self::FAILURE;
        }

        if (! Role::query()->where('name', RoleName::Admin->value)->exists()) {
            $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        }

        $utilisateur = User::query()->create([
            'name' => $nom,
            'email' => $email,
            'password' => $motDePasse,
            'is_active' => true,
        ]);
        $utilisateur->assignRole(RoleName::Admin->value);

        $this->info("Compte ADMIN créé : {$email}");

        return self::SUCCESS;
    }
}
