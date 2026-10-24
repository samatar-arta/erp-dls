<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModulesRh;

use App\Enums\ModuleRh;
use App\Enums\WorkflowStatut;
use App\Models\Employe;
use App\Models\User;
use App\Rules\ModeleCompatibleAvecType;
use App\Services\PerimetreService;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Socle des six ressources métier : formulaire dynamique (le type filtre les modèles), tableau,
 * fiche avec frise du workflow. Filament = interface seulement : toute la logique est dans les services.
 */
abstract class ModuleRhResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'Gestion RH';

    abstract public static function module(): ModuleRh;

    /**
     * Champs propres au module (après agent / type / modèle).
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    abstract protected static function champsSpecifiques(): array;

    /**
     * Entrées affichées sur la fiche : nom (notation pointée) => libellé.
     *
     * @return array<string, string>
     */
    abstract protected static function affichage(): array;

    public static function getModel(): string
    {
        return static::module()->enregistrementClass();
    }

    public static function getModelLabel(): string
    {
        return static::module()->libelleSingulier();
    }

    public static function getPluralModelLabel(): string
    {
        return static::module()->label();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedDocumentText;
    }

    public static function getEloquentQuery(): Builder
    {
        $requete = parent::getEloquentQuery()->with(['employe', 'type', 'workflow']);
        $utilisateur = auth()->user();

        return $utilisateur instanceof User
            ? app(PerimetreService::class)->appliquerViaEmploye($requete, $utilisateur)
            : $requete->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        $module = static::module();
        $cleType = $module->cleType();
        $cleModele = $module->cleModele();

        return $schema->components([
            Section::make('Dossier')->columns(2)->schema([
                Select::make('matricule')
                    ->label('Agent')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $recherche): array => static::employes($recherche))
                    ->getOptionLabelUsing(fn (string $value): ?string => Employe::query()->where('matricule', $value)->value('name'))
                    ->disabled(fn (string $operation): bool => $operation !== 'create')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                Select::make($cleType)
                    ->label('Type')
                    ->options(fn (): array => $module->typeClass()::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state) use ($module, $cleModele, $cleType): void {
                        // Le modèle par défaut du type est présélectionné ; jamais un modèle d'un autre type.
                        $set($cleModele, $state === null ? null : $module->modeleClass()::query()
                            ->where($cleType, $state)->where('is_active', true)->orderByDesc('is_default')->value('id'));
                    }),
                Select::make($cleModele)
                    ->label('Modèle')
                    ->options(fn (Get $get): array => $get($cleType) === null ? [] : $module->modeleClass()::query()
                        ->where($cleType, $get($cleType))->where('is_active', true)->orderByDesc('is_default')->pluck('titre', 'id')->all())
                    ->required(fn (Get $get): bool => static::modeleRequis($module, $get($cleType)))
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($module, $get, $cleType): void {
                            if ($value !== null) {
                                (new ModeleCompatibleAvecType($module, $get($cleType)))->validate($attribute, $value, $fail);
                            }
                        },
                    ])
                    ->helperText('Seuls les modèles actifs du type choisi sont proposés.'),
            ]),
            Section::make('Détails')->columns(2)->schema(static::champsSpecifiques()),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $entrees = [];
        foreach (static::affichage() as $nom => $libelle) {
            $entrees[] = TextEntry::make($nom)->label($libelle)->placeholder('—');
        }

        return $schema->components([
            Section::make('Suivi')->columns(3)->schema([
                TextEntry::make('reference')->label('Référence')->placeholder('Attribuée à l\'approbation finale'),
                TextEntry::make('workflow.statut')->label('Statut')->badge()
                    ->formatStateUsing(fn (?WorkflowStatut $state): ?string => $state?->label())
                    ->color(fn (?WorkflowStatut $state): string => static::couleurStatut($state)),
                TextEntry::make('statut_generation')->label('PDF')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                        'genere' => 'Généré', 'en_attente' => 'En cours de génération', 'echec' => 'Échec (relancer)', default => $state,
                    }),
                TextEntry::make('workflow.echeance_at')->label('Échéance')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('employe.name')->label('Agent'),
                TextEntry::make('employe.matricule')->label('Matricule'),
                TextEntry::make('type.name')->label('Type'),
            ]),
            Section::make('Détails')->columns(2)->schema($entrees),
            Section::make('Historique du workflow')->schema([
                RepeatableEntry::make('workflow.historiques')->label('')->columns(5)->schema([
                    TextEntry::make('created_at')->label('Date')->dateTime('d/m/Y H:i'),
                    TextEntry::make('action')->label('Action')->formatStateUsing(fn ($state): string => $state->label()),
                    TextEntry::make('vers_statut')->label('Nouveau statut')->formatStateUsing(fn ($state): string => $state->label()),
                    TextEntry::make('user.name')->label('Par')->placeholder('Système'),
                    TextEntry::make('commentaire')->label('Commentaire')->placeholder('—'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $module = static::module();
        $cleType = $module->cleType();

        return $table
            ->columns([
                TextColumn::make('reference')->label('Référence')->searchable()->placeholder('—')->sortable(),
                TextColumn::make('matricule')->label('Matricule')->searchable()->sortable(),
                TextColumn::make('employe.name')->label('Agent')->searchable(),
                TextColumn::make('type.name')->label('Type'),
                TextColumn::make('workflow.statut')->label('Statut')->badge()
                    ->formatStateUsing(fn (?WorkflowStatut $state): ?string => $state?->label())
                    ->color(fn (?WorkflowStatut $state): string => static::couleurStatut($state)),
                TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')
                    ->options(collect(WorkflowStatut::cases())->mapWithKeys(fn (WorkflowStatut $s): array => [$s->value => $s->label()])->all())
                    ->query(fn (Builder $q, array $data): Builder => $q->when(
                        $data['value'] ?? null,
                        fn (Builder $q, string $statut): Builder => $q->whereHas('workflow', fn (Builder $w): Builder => $w->where('statut', $statut)),
                    )),
                SelectFilter::make($cleType)->label('Type')
                    ->options(fn (): array => $module->typeClass()::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        // Pages concrètes du module, par convention : <Namespace de la ressource>\Pages\{List<Pluriel>,Create<Singulier>,...}.
        $espace = (new \ReflectionClass(static::class))->getNamespaceName();
        $pluriel = class_basename($espace);
        $singulier = static::module()->name;
        $page = static fn (string $prefixe, string $nom): string => $espace.'\\Pages\\'.$prefixe.$nom;

        return [
            'index' => $page('List', $pluriel)::route('/'),
            'create' => $page('Create', $singulier)::route('/create'),
            'view' => $page('View', $singulier)::route('/{record}'),
            'edit' => $page('Edit', $singulier)::route('/{record}/edit'),
        ];
    }

    public static function couleurStatut(?WorkflowStatut $statut): string
    {
        return match ($statut) {
            WorkflowStatut::Approuve, WorkflowStatut::Valide => 'success',
            WorkflowStatut::Rejete, WorkflowStatut::Expire => 'danger',
            WorkflowStatut::DocumentSoumis, WorkflowStatut::EnAttenteValidation, WorkflowStatut::EnAttenteApprobation => 'warning',
            default => 'gray',
        };
    }

    /**
     * Agents proposés : uniquement ceux du périmètre de l'utilisateur.
     *
     * @return array<string, string>
     */
    protected static function employes(string $recherche): array
    {
        $utilisateur = auth()->user();
        if (! $utilisateur instanceof User) {
            return [];
        }

        return app(PerimetreService::class)
            ->appliquer(Employe::query(), $utilisateur)
            ->where(fn (Builder $q): Builder => $q->where('name', 'like', "%{$recherche}%")->orWhere('matricule', 'like', "%{$recherche}%"))
            ->orderBy('name')->limit(50)->get()
            ->mapWithKeys(fn (Employe $e): array => [$e->matricule => "{$e->matricule} — {$e->name}"])
            ->all();
    }

    private static function modeleRequis(ModuleRh $module, mixed $typeId): bool
    {
        if ($typeId === null) {
            return true;
        }

        return $module->typeClass()::query()->whereKey($typeId)->first()?->getAttribute('requires_model') !== false;
    }
}
