<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages\ContactMessageResource;
use ScBudgetWeb\SiteBuilder\Models\ContactMessage;

/**
 * One message. Opening it marks it read.
 */
class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $message = $this->getRecord();

        if ($message instanceof ContactMessage && ! $message->isRead()) {
            $message->update(['read_at' => now()]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label(__('Reply by email'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->url(fn (ContactMessage $record): string => 'mailto:'.$record->email.'?subject='.rawurlencode(__('Re: :subject', ['subject' => $record->subject ?: __('your message')]))),
            DeleteAction::make(),
        ];
    }
}
