<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use ScBudgetWeb\SiteBuilder\Facades\SiteBuilder;
use ScBudgetWeb\SiteBuilder\SiteBuilderPlugin;

class SiteBuilderPluginTest extends TestCase
{
    public function test_it_adds_the_website_group_after_the_panels_own_groups(): void
    {
        $panel = Panel::make()
            ->id('admin')
            ->navigationGroups([NavigationGroup::make()->label('Settings')])
            ->plugin(SiteBuilderPlugin::make());

        $labels = array_map(fn (NavigationGroup $group): string => $group->getLabel(), $panel->getNavigationGroups());

        $this->assertSame(['Settings', 'Website'], array_values($labels));
        $this->assertSame('site-builder', SiteBuilderPlugin::make()->getId());
    }

    public function test_the_group_name_comes_from_config(): void
    {
        config(['site-builder.navigation.group' => 'Content']);

        $panel = Panel::make()->id('admin')->plugin(SiteBuilderPlugin::make());
        $group = array_values($panel->getNavigationGroups())[0];

        $this->assertSame('Content', $group->getLabel());
        $this->assertSame('Content', SiteBuilder::navigationGroup());
    }

    public function test_upload_paths_come_from_config(): void
    {
        $this->assertSame('public', SiteBuilder::uploadDisk());
        $this->assertSame('site-builder/pages', SiteBuilder::uploadDirectory('pages'));
        $this->assertSame('site-builder', SiteBuilder::uploadDirectory());
    }
}
