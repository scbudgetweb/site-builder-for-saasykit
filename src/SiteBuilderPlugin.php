<?php

namespace ScBudgetWeb\SiteBuilder;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageLegalPages;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageNavigation;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;

/**
 * Adds the site builder to a Filament panel (the admin panel): `->plugin(SiteBuilderPlugin::make())`.
 * Its pages and resources all sit in one navigation group (Website by default).
 */
class SiteBuilderPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'site-builder';
    }

    public function register(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make()
                ->label(fn (): string => app(SiteBuilder::class)->navigationGroup())
                ->icon(config('site-builder.navigation.icon'))
                ->collapsed((bool) config('site-builder.navigation.collapsed', true)),
        ]);

        $panel->resources([
            PageResource::class,
            SavedBlockResource::class,
        ]);

        $panel->pages([
            ManageNavigation::class,
            ManageBranding::class,
            ManageLegalPages::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
