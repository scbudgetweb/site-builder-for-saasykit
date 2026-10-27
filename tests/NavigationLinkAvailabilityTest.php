<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageNavigation;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Standard links only where they apply, links an app removes, saved menus that stay the site's own, and an honest
 * Reset to default (Website › Navigation).
 */
class NavigationLinkAvailabilityTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->get('/privacy-policy', fn () => 'Privacy')->name('privacy-policy');
        $router->get('/terms-of-service', fn () => 'Terms')->name('terms-of-service');
        $router->get('/roadmap', fn () => 'Roadmap')->name('roadmap');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
    }

    public function test_reset_to_default_says_exactly_what_it_does_before_replacing_the_menus(): void
    {
        config(['app.description' => 'Book your local hall.']);
        $this->menus()->save([['type' => 'url', 'url' => '/x', 'label' => 'Custom']], ['tagline' => 'Ours', 'columns' => []], ['open_from' => 'right', 'show_logo' => false]);

        $component = Livewire::test(ManageNavigation::class)->mountAction(TestAction::make('resetToDefault'));
        $action = $component->instance()->getMountedAction();
        $description = (string) $action->getModalDescription();

        $this->assertTrue($action->isConfirmationRequired());
        foreach ([
            'This replaces your current header, footer and mobile menu with this site&#039;s standard ones',
            'Header:</strong> Pricing, Blog',
            'Footer:</strong> Blog, Privacy Policy, Terms of Service, with the tagline &quot;Book your local hall.&quot; and the standard copyright line',
            'opens from the top, with the logo, and no tagline',
            'This can&#039;t be undone.',
        ] as $text) {
            $this->assertStringContainsString($text, $description);
        }

        // Nothing changes until it's confirmed.
        $this->assertSame(['Custom'], array_column($this->menus()->header(), 'label'));

        $component->callMountedAction()->assertNotified('The menus are now this site\'s standard ones.');

        $this->assertSame(['Pricing', 'Blog'], array_column($this->menus()->header(), 'label'));
        $this->assertSame(['Blog', 'Privacy Policy', 'Terms of Service'], array_column($this->menus()->footer()['columns'][0]['links'], 'label'));
        $this->assertSame('Book your local hall.', $this->menus()->footer()['tagline']);
        $this->assertSame(NavigationMenus::defaultMobile(), $this->menus()->mobile());
    }

    public function test_links_that_dont_apply_are_left_out_of_the_picker_and_the_standard_menus(): void
    {
        // Roadmap is switched off; Pricing applies because the home page has a pricing section.
        $this->assertArrayNotHasKey('roadmap', $this->menus()->builtinOptions());
        $this->assertArrayHasKey('pricing', $this->menus()->builtinOptions());
        $this->assertSame(['pricing', 'blog'], array_column($this->menus()->standardHeader(), 'link'));

        config(['app.roadmap_enabled' => true]);
        $this->assertArrayHasKey('roadmap', $this->menus()->builtinOptions());
        $this->assertSame(['pricing', 'blog', 'roadmap'], array_column($this->menus()->standardHeader(), 'link'));
    }

    public function test_pricing_applies_only_when_the_home_page_has_a_pricing_section(): void
    {
        $home = $this->page(['title' => 'Home', 'slug' => 'welcome', 'replaces' => 'home', 'blocks' => [self::block('text', ['content' => '<p>Hi</p>'])]]);

        $this->assertArrayNotHasKey('pricing', $this->menus()->builtinOptions());
        $this->assertSame(['blog'], array_column($this->menus()->standardHeader(), 'link'));
        $this->assertSame(['Blog'], array_column($this->menus()->header(), 'label'));

        // A block anchored "pricing", here in a Section's row, gives the link somewhere to go.
        $home->update(['blocks' => [self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [
            self::block('text', ['content' => '<p>Plans</p>', 'appearance' => ['anchor' => 'pricing']]),
        ]]]])]]);

        $this->assertArrayHasKey('pricing', $this->menus()->builtinOptions());

        // A draft doesn't replace the home page, so the built-in home (with its pricing section) counts again.
        $home->update(['status' => PageStatus::Draft, 'blocks' => []]);
        $this->assertArrayHasKey('pricing', $this->menus()->builtinOptions());
    }

    public function test_an_app_can_remove_standard_links_whichever_order_it_runs_in(): void
    {
        $siteBuilder = app(SiteBuilder::class);
        $siteBuilder->withoutLinks(['pricing', 'privacy_policy']);
        // The package registering its links again (as its provider would after the app's) doesn't bring them back.
        $siteBuilder->links(NavigationMenus::standardLinks());

        $this->assertArrayNotHasKey('pricing', $siteBuilder->registeredLinks());
        $this->assertNull($siteBuilder->link('pricing'));
        $this->assertArrayNotHasKey('pricing', $this->menus()->builtinOptions());
        $this->assertSame(['blog'], array_column($this->menus()->standardHeader(), 'link'));
        $this->assertSame(['blog', 'terms_of_service'], array_column($this->menus()->standardFooter()['columns'][0]['links'], 'link'));
    }

    public function test_a_saved_menu_with_a_removed_link_still_loads_and_saves_and_just_hides_it(): void
    {
        $this->menus()->save([
            ['type' => 'builtin', 'link' => 'pricing'],
            ['type' => 'builtin', 'link' => 'blog'],
        ], NavigationMenus::defaultFooter(), []);

        app(SiteBuilder::class)->withoutLinks(['pricing']);

        $this->assertSame(['Blog'], array_column($this->menus()->header(), 'label'));
        $this->assertSame(['Pricing (not available on this site, so hidden)'], [$this->menus()->builtinOptions('pricing')['pricing']]);

        $component = Livewire::test(ManageNavigation::class);
        $firstItem = array_key_first($component->get('data.header'));

        $component
            ->assertFormFieldExists("header.{$firstItem}.link", fn ($field): bool => ($field->getOptions()['pricing'] ?? null) === 'Pricing (not available on this site, so hidden)')
            ->call('save')
            ->assertHasNoFormErrors();

        // Never deleted from the saved data.
        $this->assertSame(['pricing', 'blog'], array_column(app(Settings::class)->get(NavigationMenus::HEADER_KEY), 'link'));
    }

    public function test_saved_menus_never_gain_links_the_site_didnt_add(): void
    {
        $this->menus()->save([['type' => 'builtin', 'link' => 'blog']], NavigationMenus::defaultFooter(), []);

        config(['app.roadmap_enabled' => true]);

        $this->assertSame(['Blog'], array_column($this->menus()->header(), 'label'));
    }

    private function menus(): NavigationMenus
    {
        return app(NavigationMenus::class);
    }
}
