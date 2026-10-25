<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\File;
use ScBudgetWeb\SiteBuilder\Branding\Branding;

class BrandingPanelsTest extends TestCase
{
    public function test_a_panel_keeps_its_own_colour_and_font_until_branding_sets_one(): void
    {
        $panel = $this->registerPanel();

        $this->assertSame(Color::Emerald, $panel->getColors()['primary']);
        $this->assertSame('Figtree', $panel->getFontFamily());
    }

    public function test_a_branding_colour_replaces_only_that_panels_primary(): void
    {
        $this->app->make(Branding::class)->save([Branding::panelColorKey('dashboard') => '#2e6a4c']);

        $panel = $this->registerPanel();

        $this->assertNotSame(Color::Emerald, $panel->getColors()['primary']);
        $this->assertSame(Color::Gray, $panel->getColors()['gray']);
    }

    public function test_a_dashboard_font_is_self_hosted(): void
    {
        File::copyDirectory(__DIR__.'/../resources/fonts', public_path(Branding::PACKAGE_FONTS_DIRECTORY));
        $this->app->make(Branding::class)->save([Branding::APP_FONT => 'inter']);

        $panel = $this->registerPanel();

        $this->assertSame('Inter', $panel->getFontFamily());
        $this->assertStringContainsString('vendor/site-builder/fonts/inter/font.css', (string) $panel->getFontUrl());

        File::deleteDirectory(public_path('vendor/site-builder'));
    }

    private function registerPanel(): Panel
    {
        $this->app->forgetInstance(PanelRegistry::class);

        $this->app->resolving(PanelRegistry::class, fn (PanelRegistry $registry) => $registry->register(
            Panel::make()->id('dashboard')->path('dashboard')->colors(['primary' => Color::Emerald, 'gray' => Color::Gray])->font('Figtree'),
        ));

        return Filament::getPanel('dashboard');
    }
}
