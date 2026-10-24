<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rh;

use App\Enums\ModuleRh;
use App\Services\TemplateRendererService;
use App\Support\VariablesModele;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Administration des modèles HTML d'un module. Le contenu n'est jamais exécuté : seules les variables
 * de la liste blanche sont acceptées, et l'aperçu s'affiche dans un cadre isolé (sandbox).
 */
abstract class ModeleRhResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'Types et modèles';

    abstract public static function module(): ModuleRh;

    /** @return list<\Filament\Schemas\Components\Component> */
    protected static function champsSpecifiques(): array
    {
        return [];
    }

    public static function getModel(): string
    {
        return static::module()->modeleClass();
    }

    public static function getModelLabel(): string
    {
        return 'Modèle de '.mb_strtolower(static::module()->libelleSingulier());
    }

    public static function getPluralModelLabel(): string
    {
        return 'Modèles — '.static::module()->label();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedDocumentDuplicate;
    }

    public static function form(Schema $schema): Schema
    {
        $module = static::module();

        return $schema->components([
            Select::make($module->cleType())->label('Type')
                ->options(fn (): array => $module->typeClass()::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->required()
                ->disabled(fn (string $operation): bool => $operation !== 'create')
                ->dehydrated(fn (string $operation): bool => $operation === 'create'),
            TextInput::make('titre')->label('Titre')->required()->maxLength(255),
            Textarea::make('contenu')->label('Contenu HTML')->required()->rows(18)->columnSpanFull()
                ->extraInputAttributes(['style' => 'font-family: monospace;'])
                ->helperText('Variables autorisées : '.implode(', ', array_map(static fn (string $v): string => '{{ '.$v.' }}', VariablesModele::noms($module))))
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($module): void {
                        $inconnues = app(TemplateRendererService::class)->variablesInconnues($module, (string) $value);
                        if ($inconnues !== []) {
                            $fail('Variable(s) non autorisée(s) : '.implode(', ', $inconnues).'.');
                        }
                    },
                ]),
            ...static::champsSpecifiques(),
            TextInput::make('version')->label('Version')->disabled()->dehydrated(false)->visibleOn('edit')
                ->helperText('Incrémentée automatiquement quand un modèle déjà utilisé est modifié.'),
            Toggle::make('is_default')->label('Modèle par défaut du type')->helperText('Un seul modèle par défaut actif par type.'),
            Toggle::make('is_active')->label('Actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $module = static::module();

        return $table
            ->columns([
                TextColumn::make('titre')->label('Titre')->searchable(),
                TextColumn::make('type.name')->label('Type'),
                TextColumn::make('version')->label('Version'),
                IconColumn::make('is_default')->label('Défaut')->boolean(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->filters([
                SelectFilter::make($module->cleType())->label('Type')
                    ->options(fn (): array => $module->typeClass()::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([static::actionApercu(), EditAction::make()]);
    }

    public static function actionApercu(): Action
    {
        return Action::make('apercu')
            ->label('Aperçu')
            ->icon('heroicon-o-eye')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->modalWidth('5xl')
            ->modalContent(function ($record): HtmlString {
                $html = app(TemplateRendererService::class)->apercu(static::module(), (string) $record->contenu);

                // Cadre isolé : aucun script ni accès au site, même si le contenu était malveillant.
                return new HtmlString('<iframe sandbox="" srcdoc="'.e($html).'" style="width:100%;height:60vh;border:1px solid #ddd;background:#fff"></iframe>');
            });
    }

    public static function getPages(): array
    {
        $espace = (new \ReflectionClass(static::class))->getNamespaceName().'\\Pages\\';
        $base = Str::beforeLast(class_basename(static::class), 'Resource');

        return [
            'index' => ($espace.'List'.$base.'s')::route('/'),
            'create' => ($espace.'Create'.$base)::route('/create'),
            'edit' => ($espace.'Edit'.$base)::route('/{record}/edit'),
        ];
    }
}
