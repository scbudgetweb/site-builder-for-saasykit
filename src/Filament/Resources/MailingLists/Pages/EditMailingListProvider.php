<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\MailingLists\Pages;

use Filament\Resources\Pages\EditRecord;
use ScBudgetWeb\SiteBuilder\Filament\Resources\MailingLists\MailingListProviderResource;
use ScBudgetWeb\SiteBuilder\MailingLists\MailingListService;
use ScBudgetWeb\SiteBuilder\Models\MailingListProvider;

class EditMailingListProvider extends EditRecord
{
    protected static string $resource = MailingListProviderResource::class;

    /**
     * Keeps the chosen list's name for the list page, and forgets the cached lists when the key changes (it may be
     * another account).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $mailingLists = app(MailingListService::class);
        $provider = $this->getRecord();

        if (! $provider instanceof MailingListProvider) {
            return $data;
        }

        if (filled($data['api_key'] ?? null)) {
            $mailingLists->forgetLists($provider);
        }

        if (array_key_exists('list_id', $data)) {
            $data['list_name'] = filled($data['list_id'])
                ? ($mailingLists->lists($provider)[$data['list_id']] ?? $provider->list_name)
                : null;
        }

        return $data;
    }
}
