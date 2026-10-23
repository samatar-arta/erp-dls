<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employes;

use App\Filament\Resources\Employes\Pages\CreateEmploye;
use App\Filament\Resources\Employes\Pages\EditEmploye;
use App\Filament\Resources\Employes\Pages\ListEmployes;
use App\Filament\Resources\Employes\Pages\ViewEmploye;
use App\Filament\Resources\Employes\RelationManagers\AffectationsRelationManager;
use App\Filament\Resources\Employes\RelationManagers\FichiersRelationManager;
use App\Models\Employe;
use App\Models\SiteDepartement;
use App\Models\TypeContrat;
use App\Models\User;
use App\Services\PerimetreService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EmployeResource extends Resource
{
    protected static ?string $model = Employe::class;

    protected static ?string $modelLabel = 'Agent';

    protected static ?string $pluralModelLabel = 'Agents';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Gestion RH';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public const STATUTS = ['actif' => 'Actif', 'suspendu' => 'Suspendu', 'sorti' => 'Sorti'];

    public static function getEloquentQuery(): Builder
    {
        $requete = parent::getEloquentQuery()->with(['societe', 'site', 'departement']);
        $utilisateur = auth()->user();

        return $utilisateur instanceof User
            ? app(PerimetreService::class)->appliquer($requete, $utilisateur)
            : $requete->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité')->columns(2)->schema([
                TextInput::make('matricule')->label('Matricule')->required()->maxLength(30)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (string $operation): bool => $operation !== 'create')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                TextInput::make('name')->label('Nom complet')->required()->maxLength(255),
                DatePicker::make('date_naissance')->label('Date de naissance')->maxDate(now()),
                TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(40),
                TextInput::make('num_CNI')->label('N° CNI')->maxLength(40),
                TextInput::make('num_CNSS')->label('N° CNSS')->maxLength(40),
                TextInput::make('adresse')->label('Adresse')->maxLength(255),
                TextInput::make('situation_familiale')->label('Situation familiale')->maxLength(255),
                Toggle::make('handicap')->label('Situation de handicap'),
            ]),
            Section::make('Emploi')->columns(2)->schema([
                Select::make('site_departement_id')
                    ->label('Affectation (société / site / département)')
                    ->options(fn (): array => static::couplesAccessibles())
                    ->searchable()
                    ->required()
                    ->visibleOn('create')
                    ->helperText('Ensuite, l\'affectation ne change que par une mutation ou une promotion approuvée.')
                    ->columnSpanFull(),
                TextInput::make('fonction')->label('Fonction')->maxLength(255)
                    ->disabled(fn (string $operation): bool => $operation !== 'create')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                DatePicker::make('date_embauche')->label('Date d\'embauche')->required(),
                Select::make('type_contrat_id')->label('Type de contrat')
                    ->options(fn (): array => TypeContrat::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
                TextInput::make('categorie_employe')->label('Catégorie d\'employé')->maxLength(255),
                Select::make('statut_employe')->label('Statut')->options(self::STATUTS)->default('actif')->required(),
                DatePicker::make('date_sortie')->label('Date de sortie'),
                TextInput::make('motif_sortie')->label('Motif de sortie')->maxLength(255),
            ]),
            Section::make('Compléments')->columns(2)->collapsed()->schema([
                TextInput::make('niveau_etude')->label('Niveau d\'étude')->maxLength(255),
                TextInput::make('permis_conduite')->label('Permis de conduire')->maxLength(255),
                TextInput::make('categorie')->label('Catégorie')->maxLength(255),
                Textarea::make('formation_certificat')->label('Formations et certificats')->columnSpanFull(),
                TextInput::make('personne_contacter')->label('Personne à contacter')->maxLength(255),
                TextInput::make('lien_parental')->label('Lien de parenté')->maxLength(255),
                TextInput::make('telephone_parental')->label('Téléphone de la personne à contacter')->tel()->maxLength(40),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité')->columns(3)->schema([
                TextEntry::make('matricule')->label('Matricule'),
                TextEntry::make('name')->label('Nom complet'),
                TextEntry::make('statut_employe')->label('Statut')->badge(),
                TextEntry::make('date_naissance')->label('Date de naissance')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('age')->label('Âge')->suffix(' ans')->placeholder('—'),
                TextEntry::make('telephone')->label('Téléphone')->placeholder('—'),
            ]),
            Section::make('Emploi')->columns(3)->schema([
                TextEntry::make('societe.name')->label('Société'),
                TextEntry::make('site.name')->label('Site'),
                TextEntry::make('departement.name')->label('Département'),
                TextEntry::make('fonction')->label('Fonction')->placeholder('—'),
                TextEntry::make('date_embauche')->label('Embauché le')->date('d/m/Y'),
                TextEntry::make('anciennete')->label('Ancienneté')->suffix(' mois')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricule')->label('Matricule')->searchable()->sortable(),
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('fonction')->label('Fonction')->searchable()->placeholder('—'),
                TextColumn::make('societe.name')->label('Société'),
                TextColumn::make('site.name')->label('Site'),
                TextColumn::make('departement.name')->label('Département'),
                TextColumn::make('statut_employe')->label('Statut')->badge(),
            ])
            ->filters([
                SelectFilter::make('statut_employe')->label('Statut')->options(self::STATUTS),
                SelectFilter::make('site_id')->label('Site')->relationship('site', 'name'),
                SelectFilter::make('societe_id')->label('Société')->relationship('societe', 'name'),
            ])
            ->defaultSort('name')
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [AffectationsRelationManager::class, FichiersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployes::route('/'),
            'create' => CreateEmploye::route('/create'),
            'view' => ViewEmploye::route('/{record}'),
            'edit' => EditEmploye::route('/{record}/edit'),
        ];
    }

    /**
     * Couples société / site / département que l'utilisateur peut attribuer.
     *
     * @return array<int, string>
     */
    public static function couplesAccessibles(): array
    {
        $utilisateur = auth()->user();
        if (! $utilisateur instanceof User) {
            return [];
        }

        $requete = SiteDepartement::query()->with(['site', 'societe', 'departement']);
        if (! app(PerimetreService::class)->aAccesGlobal($utilisateur)) {
            $requete->whereIn('id', $utilisateur->siteDepartements()->pluck('site_departements.id'));
        }

        return $requete->get()
            ->mapWithKeys(fn (SiteDepartement $sd): array => [$sd->id => $sd->libelle()])
            ->sort()
            ->all();
    }
}
