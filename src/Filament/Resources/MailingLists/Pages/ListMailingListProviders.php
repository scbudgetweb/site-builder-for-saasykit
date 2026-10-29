<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\MailingLists\Pages;

use Filament\Resources\Pages\ListRecords;
use ScBudgetWeb\SiteBuilder\Filament\Resources\MailingLists\MailingListProviderResource;

class ListMailingListProviders extends ListRecords
{
    protected static string $resource = MailingListProviderResource::class;

    public function getSubheading(): ?string
    {
        return __('Connect your mailing list service, and choose the list people join from the website\'s sign-up forms (the Mailing list block). Its look and wording are in Branding › Mailing list.');
    }
}
