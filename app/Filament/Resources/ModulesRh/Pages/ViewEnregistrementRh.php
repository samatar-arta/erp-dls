<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModulesRh\Pages;

use App\Enums\WorkflowAction;
use App\Exceptions\RegleMetierException;
use App\Services\WorkflowService;
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
        $actions = [EditAction::make()];

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
