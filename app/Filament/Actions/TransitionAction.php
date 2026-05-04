<?php

namespace App\Filament\Actions;

use App\Models\Concerns\HasVerificationWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Reusable Filament action that drives the standardized verification workflow.
 * Usage:
 *   TransitionAction::make('transition')->record($record)
 */
class TransitionAction
{
    public static function make(string $name = 'transition'): Action
    {
        return Action::make($name)
            ->label('Transition status')
            ->icon(Heroicon::ArrowPath)
            ->color('primary')
            ->schema(fn (Model $record) => [
                Select::make('to')
                    ->label('Move to')
                    ->required()
                    ->options(self::availableTransitions($record)),
                Textarea::make('remarks')
                    ->placeholder('Required when rejecting / suspending')
                    ->rows(3),
            ])
            ->action(function (Model $record, array $data): void {
                try {
                    /** @var HasVerificationWorkflow $record */
                    $record->transitionStatus(
                        toStatus: $data['to'],
                        verifierId: auth()->id(),
                        remarks: $data['remarks'] ?? null,
                    );
                    Notification::make()
                        ->title('Status updated')
                        ->body("Now: {$data['to']}")
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Transition failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /** @return array<string, string> */
    private static function availableTransitions(Model $record): array
    {
        $current = $record->status instanceof \BackedEnum
            ? $record->status->value
            : (string) ($record->status ?? '');
        $allowed = method_exists($record, 'statusTransitions')
            ? ($record::statusTransitions()[$current] ?? [])
            : [];

        return array_combine($allowed, $allowed) ?: [];
    }
}
