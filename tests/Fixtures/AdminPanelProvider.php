<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use ScBudgetWeb\SiteBuilder\SiteBuilderPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('admin')->path('admin')->default()->plugin(SiteBuilderPlugin::make());
    }
}
