<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModulesRh\Pages;

use App\Enums\WorkflowAction;
use App\Exceptions\RegleMetierException;
use App\Jobs\GenererPdfJob;
use App\Services\WorkflowService;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Fiche d'un enregistrement : un bouton par transition possible. La visibilité vient de
 * WorkflowService::peutExecuter, mais le contrôle réel est refait à l'exécution (le bouton n'est qu'un confort).
 */
abstract class ViewEnregistrementRh extends ViewRecord
{
    protected function getHeaderActions(): array
    {
        $actions = [
            EditAction::make(),
            Action::make('pdf')
                ->label('Télécharger le PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $this->getRecord()->statut_generation === 'genere')
                ->url(fn (): string => route('enregistrements.pdf', [static::getResource()::module()->value, $this->getRecord()->getKey()])),
            Action::make('regenerer_pdf')
                ->label('Relancer la génération du PDF')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => in_array($this->getRecord()->statut_generation, ['en_attente', 'echec'], true)
                    && (bool) auth()->user()?->can(Permissions::WORKFLOW_APPROUVER))
                ->requiresConfirmation()
                ->action(function (): void {
                    // Idempotent : la référence et le contenu déjà figé ne changent pas.
                    GenererPdfJob::dispatch(static::getResource()::module()->value, (int) $this->getRecord()->getKey());
                    Notification::make()->title('Génération du PDF relancée')->success()->send();
                }),
        ];

        foreach (WorkflowAction::cases() as $transition) {
            if ($transition->estSysteme()) {
                continue;
            }

            $actions[] = Action::make('workflow_'.$transition->value)
                ->label($transition->label())
                ->color(match ($transition) {
                    WorkflowAction::Approuver, WorkflowAction::Valider => 'success',
                    WorkflowAction::Rejeter => 'danger',
                    default => 'primary',
                })
                ->visible(fn (): bool => app(WorkflowService::class)->peutExecuter($transition, $this->getRecord(), auth()->user()))
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('commentaire')
                        ->label($transition->exigeCommentaire() ? 'Motif (obligatoire)' : 'Commentaire (facultatif)')
                        ->required($transition->exigeCommentaire())
                        ->maxLength(1000),
                ])
                ->action(function (array $data) use ($transition): void {
                    try {
                        app(WorkflowService::class)->executer($transition, $this->getRecord(), auth()->user(), $data['commentaire'] ?? null);
                    } catch (RegleMetierException|AuthorizationException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    $this->record->refresh()->load('workflow.historiques');
                    Notification::make()->title($transition->label().' : effectué')->success()->send();
                });
        }

        return $actions;
    }
}
