<?php

declare(strict_types=1);

namespace App\Filament\Resources\SoldesConges;

use App\Filament\Resources\SoldesConges\Pages\ManageSoldesConges;
use App\Models\SoldeConge;
use App\Models\User;
use App\Services\PerimetreService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Soldes de congés : lecture dans le périmètre de l'utilisateur. Les soldes sont créés au premier débit ;
 * seuls les droits acquis et le report sont ajustables (referentiels.manage), les jours pris jamais.
 */
class SoldeCongeResource extends Resource
{
    protected static ?string $model = SoldeConge::class;

    protected static ?string $modelLabel = 'Solde de congé';

    protected static ?string $pluralModelLabel = 'Soldes de congés';

    protected static string|UnitEnum|null $navigationGroup = 'Gestion RH';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    public static function getEloquentQuery(): Builder
    {
        $requete = parent::getEloquentQuery()->with(['employe', 'type']);
        $utilisateur = auth()->user();

        return $utilisateur instanceof User
            ? app(PerimetreService::class)->appliquerViaEmploye($requete, $utilisateur)
            : $requete->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('jours_acquis')->label('Jours acquis')->numeric()->required()->minValue(0)->maxValue(365)->step(0.5),
            TextInput::make('jours_report')->label('Report')->numeric()->required()->minValue(0)->maxValue(365)->step(0.5),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricule')->label('Matricule')->searchable()->sortable(),
                TextColumn::make('employe.name')->label('Agent')->searchable(),
                TextColumn::make('type.name')->label('Type'),
                TextColumn::make('annee')->label('Année')->sortable(),
                TextColumn::make('jours_acquis')->label('Acquis'),
                TextColumn::make('jours_report')->label('Report'),
                TextColumn::make('jours_pris')->label('Pris'),
                TextColumn::make('restant')->label('Restant')->state(fn (SoldeConge $solde): float => $solde->restant()),
            ])
            ->filters([
                SelectFilter::make('annee')->label('Année')
                    ->options(fn (): array => SoldeConge::query()->distinct()->orderByDesc('annee')->pluck('annee', 'annee')->all()),
            ])
            ->defaultSort('annee', 'desc')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSoldesConges::route('/')];
    }
}
