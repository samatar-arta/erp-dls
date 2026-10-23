<?php

declare(strict_types=1);

namespace App\Filament\Resources\SiteDepartements\Schemas;

use App\Models\Departement;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class SiteDepartementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('site_id')
                    ->label("Site d'affectation")
                    ->relationship('site', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('societe_id')
                    ->label('Société')
                    ->relationship('societe', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (callable $set): mixed => $set('departement_id', null)),
                Select::make('departement_id')
                    ->label('Département')
                    ->options(fn (callable $get): array => Departement::query()
                        ->where('societe_id', $get('societe_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, callable $get): Unique => $rule->where('site_id', $get('site_id')),
                    )
                    ->rules([
                        // Contrôle serveur : le département doit appartenir à la société choisie.
                        fn (callable $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            $appartient = Departement::query()
                                ->whereKey($value)
                                ->where('societe_id', $get('societe_id'))
                                ->exists();
                            if (! $appartient) {
                                $fail('Ce département n\'appartient pas à la société sélectionnée.');
                            }
                        },
                    ]),
            ]);
    }
}
