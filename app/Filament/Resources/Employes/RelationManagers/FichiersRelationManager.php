<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employes\RelationManagers;

use App\Enums\FichierCategorie;
use App\Models\Employe;
use App\Models\EmployeFichier;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Dossier numérique : fichiers sur le disque privé uniquement, téléchargement par route autorisée.
 */
class FichiersRelationManager extends RelationManager
{
    protected static string $relationship = 'fichiers';

    protected static ?string $title = 'Dossier numérique';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewFichiers', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('categorie')->label('Catégorie')->options(FichierCategorie::options())->required(),
            FileUpload::make('path')
                ->label('Fichier')
                ->disk('private')
                ->directory(fn (): string => 'dossiers/'.$this->getOwnerRecord()->getKey())
                ->visibility('private')
                ->maxSize(10240)
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                ->storeFileNamesIn('nom_original')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('categorie')->label('Catégorie')->formatStateUsing(fn (FichierCategorie $state): string => $state->label())->badge(),
                TextColumn::make('nom_original')->label('Nom du fichier')->searchable(),
                TextColumn::make('taille')->label('Taille')->formatStateUsing(fn (?int $state): string => $state === null ? '—' : number_format($state / 1024, 0, ',', ' ').' Ko'),
                TextColumn::make('deposePar.name')->label('Déposé par')->placeholder('—'),
                TextColumn::make('created_at')->label('Déposé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter un fichier')
                    ->visible(fn (): bool => Gate::allows('manageFichiers', $this->getOwnerRecord()))
                    ->using(function (array $data): Model {
                        /** @var Employe $employe */
                        $employe = $this->getOwnerRecord();
                        Gate::authorize('manageFichiers', $employe);
                        $disque = Storage::disk('private');

                        return $employe->fichiers()->create([
                            'categorie' => $data['categorie'],
                            'nom_original' => $data['nom_original'] ?? basename((string) $data['path']),
                            'path' => $data['path'],
                            'mime' => $disque->mimeType($data['path']) ?: null,
                            'taille' => $disque->size($data['path']),
                            'depose_par' => auth()->id(),
                        ]);
                    }),
            ])
            ->recordActions([
                Action::make('telecharger')
                    ->label('Télécharger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (EmployeFichier $record): string => route('employes.fichiers.telecharger', [$this->getOwnerRecord(), $record])),
                DeleteAction::make()
                    ->visible(fn (): bool => Gate::allows('manageFichiers', $this->getOwnerRecord()))
                    ->before(function (EmployeFichier $record): void {
                        Gate::authorize('manageFichiers', $this->getOwnerRecord());
                        Storage::disk('private')->delete($record->path);
                    }),
            ]);
    }
}
