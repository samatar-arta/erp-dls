<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\RoleName;
use App\Models\SiteDepartement;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $roleOptions = [];
        foreach (RoleName::cases() as $role) {
            $roleOptions[$role->value] = $role->value.' — '.$role->label();
        }

        // Rôles et affectations : verrouillés sur son propre compte (aussi contrôlé par le service).
        $estSoiMeme = static fn (?User $record): bool => $record !== null && $record->is(auth()->user());

        return $schema
            ->components([
                TextInput::make('name')->label('Nom complet')->required()->maxLength(255),
                TextInput::make('email')->label('Adresse e-mail')->email()->required()->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->rule(Password::min(12)->mixedCase()->numbers()->symbols())
                    ->helperText('12 caractères minimum, avec majuscules, minuscules, chiffres et symboles. Laisser vide pour ne pas changer.'),
                Toggle::make('is_active')
                    ->label('Compte actif')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $estSoiMeme($record)),
                Select::make('roles')
                    ->label('Rôles')
                    ->options($roleOptions)
                    ->multiple()
                    ->required()
                    ->disabled(fn (?User $record): bool => $estSoiMeme($record))
                    ->helperText('ADMIN et RH_MANAGER ont les mêmes droits et accèdent à tous les dossiers.'),
                Select::make('site_departement_ids')
                    ->label('Périmètre (société / site / département)')
                    ->options(fn (): array => SiteDepartement::query()
                        ->with(['site', 'societe', 'departement'])
                        ->get()
                        ->mapWithKeys(fn (SiteDepartement $sd): array => [$sd->id => $sd->libelle()])
                        ->sort()
                        ->all())
                    ->multiple()
                    ->searchable()
                    ->disabled(fn (?User $record): bool => $estSoiMeme($record))
                    ->helperText('Sans effet pour ADMIN et RH_MANAGER (accès global).')
                    ->columnSpanFull(),
            ]);
    }
}
