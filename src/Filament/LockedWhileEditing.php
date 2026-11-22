<?php

namespace ScBudgetWeb\SiteBuilder\Filament;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use ScBudgetWeb\SiteBuilder\Pages\EditLocks;

/**
 * A list's Visual builder or Edit button while someone else is editing that row (EditLocks): grey, with an i icon
 * and "<name> is editing this page" on hover; clicking it says the same rather than opening the editor. It stays
 * hoverable, as a disabled button wouldn't show its tooltip in every browser.
 */
class LockedWhileEditing
{
    /**
     * @param  Closure(Model): string  $url  where the button goes when the row is free
     */
    public static function apply(Action $action, Closure $url, Heroicon $icon, ?string $tooltip = null): Action
    {
        $holder = fn (Model $record): ?array => app(EditLocks::class)->heldByOther($record, auth()->user());
        $message = fn (Model $record): ?string => ($editing = $holder($record)) === null ? null : EditLocks::message($record, $editing['name']);

        return $action
            ->icon(fn (Model $record): Heroicon => $holder($record) === null ? $icon : Heroicon::OutlinedInformationCircle)
            ->color(fn (Model $record): ?string => $holder($record) === null ? null : 'gray')
            ->tooltip(fn (Model $record): ?string => $message($record) ?? $tooltip)
            ->url(fn (Model $record): ?string => $holder($record) === null ? $url($record) : null)
            ->action(function (Model $record) use ($message): void {
                Notification::make()
                    ->title((string) $message($record))
                    ->body(__('It opens once they close it.'))
                    ->warning()
                    ->send();
            });
    }
}
