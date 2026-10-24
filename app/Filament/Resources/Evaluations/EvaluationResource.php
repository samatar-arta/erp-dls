<?php

declare(strict_types=1);

namespace App\Filament\Resources\Evaluations;

use App\Enums\ModuleRh;
use App\Filament\Resources\Evaluations\Pages\CreateEvaluation;
use App\Filament\Resources\Evaluations\Pages\EditEvaluation;
use App\Filament\Resources\Evaluations\Pages\ListEvaluations;
use App\Filament\Resources\Evaluations\Pages\ViewEvaluation;
use App\Filament\Resources\ModulesRh\ModuleRhResource;
use App\Models\ModelEvaluation;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class EvaluationResource extends ModuleRhResource
{
    protected static ?int $navigationSort = 4;

    public static function module(): ModuleRh
    {
        return ModuleRh::Evaluation;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedClipboardDocumentCheck;
    }

    protected static function champsSpecifiques(): array
    {
        return [
            Select::make('evaluateur_id')->label('Évaluateur')->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->default(fn () => auth()->id())->searchable(),
            DatePicker::make('date_evaluation')->label('Date de l\'évaluation')->required()->default(now()),
            DatePicker::make('periode_debut')->label('Début de période'),
            DatePicker::make('periode_fin')->label('Fin de période')->afterOrEqual('periode_debut'),
            Repeater::make('notes_criteres')->label('Notes par critère')
                ->helperText('Chaque critère est noté sur son barème ; la note finale pondérée (sur 20) est calculée à l\'enregistrement.')
                ->schema([
                    TextInput::make('libelle')->label('Critère')->readOnly()->dehydrated(),
                    TextInput::make('info')->label('Barème')->readOnly()->dehydrated(false),
                    TextInput::make('note')->label('Note')->numeric()->required()->minValue(0)->step(0.5),
                ])
                ->columns(3)->columnSpanFull()
                ->addable(false)->deletable(false)->reorderable(false)
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => static::criteresDuModele($get('model_evaluation_id')) !== [] || ! empty($get('notes_criteres')))
                ->afterStateHydrated(function (Repeater $composant, mixed $state): void {
                    // En modification, on réaffiche le barème figé avec les notes déjà saisies.
                    $composant->state(collect($state ?? [])->map(fn (array $c): array => $c + ['info' => static::bareme($c)])->values()->all());
                }),
            Textarea::make('appreciation')->label('Appréciation')->maxLength(5000)->columnSpanFull(),
            Textarea::make('objectifs')->label('Objectifs')->maxLength(5000)->columnSpanFull(),
        ];
    }

    protected static function apresChoixModele(Set $set, mixed $modeleId): void
    {
        $set('notes_criteres', collect(static::criteresDuModele($modeleId))
            ->map(fn (array $c): array => ['libelle' => $c['libelle'], 'info' => static::bareme($c), 'note' => null])
            ->values()->all());
    }

    /** @return list<array<string, mixed>> */
    private static function criteresDuModele(mixed $modeleId): array
    {
        return $modeleId === null ? [] : array_values((array) ModelEvaluation::query()->find($modeleId)?->criteres);
    }

    /** @param  array<string, mixed>  $critere */
    private static function bareme(array $critere): string
    {
        return sprintf('coef. %s, sur %s', $critere['coefficient'] ?? 1, $critere['note_max'] ?? 20);
    }

    protected static function entreesSupplementaires(): array
    {
        return [
            TextEntry::make('notes_criteres')->label('Notes par critère')->columnSpanFull()->listWithLineBreaks()
                ->state(fn ($record): array => collect($record->notes_criteres ?? [])
                    ->map(fn (array $c): string => sprintf('%s : %s / %s (coef. %s)', $c['libelle'], $c['note'], $c['note_max'], $c['coefficient']))
                    ->all())
                ->placeholder('—'),
        ];
    }

    protected static function affichage(): array
    {
        return [
            'evaluateur.name' => 'Évaluateur',
            'note' => 'Note finale pondérée (sur 20)',
            'date_evaluation' => 'Date',
            'periode_debut' => 'Début de période',
            'periode_fin' => 'Fin de période',
            'appreciation' => 'Appréciation',
            'objectifs' => 'Objectifs',
        ];
    }
}
