<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageLegalPages;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageMedia;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageNavigation;
use ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages\ContactMessageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\MailingLists\MailingListProviderResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;

/**
 * The Website menu's order (agreed 1 Nov 2026): the site's look first, then what's built with it, then what comes in
 * and the fixed pages.
 */
class WebsiteMenuTest extends PageTestCase
{
    public function test_the_website_menu_is_in_order(): void
    {
        $menu = [
            ManageBranding::class,
            PageResource::class,
            ManageMedia::class,
            TemplateResource::class,
            ManageNavigation::class,
            SavedBlockResource::class,
            ContactMessageResource::class,
            MailingListProviderResource::class,
            ManageLegalPages::class,
        ];

        $this->assertSame(range(1, count($menu)), array_map(fn (string $class): ?int => $class::getNavigationSort(), $menu));
    }
}
