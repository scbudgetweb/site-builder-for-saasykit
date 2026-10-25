<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;

class DashboardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('dashboard')->path('dashboard');
    }
}
