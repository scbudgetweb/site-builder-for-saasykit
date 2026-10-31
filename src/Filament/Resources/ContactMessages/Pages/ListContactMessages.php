<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages\Pages;

use Filament\Resources\Pages\ListRecords;
use ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages\ContactMessageResource;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    public function getSubheading(): ?string
    {
        $days = (int) config('site-builder.contact.keep_days', 365);

        return $days > 0
            ? __('What visitors sent through your contact forms. Messages are deleted after :days days.', ['days' => $days])
            : __('What visitors sent through your contact forms.');
    }
}
