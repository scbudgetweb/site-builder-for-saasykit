<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use RuntimeException;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageNavigation;
use ScBudgetWeb\SiteBuilder\Navigation\Link;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * An app's own standard menus (SiteBuilder::defaultMenus()): used while nothing is saved and by Reset to default.
 */
class DefaultMenusTest extends PageTestCase
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
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());

        app(SiteBuilder::class)->links([
            Link::make('whats_on', 'What\'s on', fn (): string => url('/whats-on')),
            Link::make('cookie_settings', 'Cookie settings', fn (): string => '#cookie-settings'),
            Link::make('members', 'Members', fn (): string => url('/members'))->availableWhen(fn (): bool => false),
        ]);
    }

    public function test_the_apps_standard_menus_show_while_nothing_is_saved(): void
    {
        $this->useAppDefaults();

        $this->assertSame(['What\'s on', 'Visit', 'Blog'], array_column($this->menus()->header(), 'label'));
        $this->assertSame(['Find us', 'Opening times'], array_column($this->menus()->header()[1]['children'], 'label'));

        $footer = $this->menus()->footer();
        $this->assertSame('Your village hall', $footer['tagline']);
        $this->assertSame('Example Site, est. '.date('Y'), $footer['copyright']);
        $this->assertSame(['About', 'Legal'], array_column($footer['columns'], 'heading'));
        $this->assertSame(['Privacy Policy', 'Cookie settings'], array_column($footer['columns'][1]['links'], 'label'));
        $this->assertSame(['open_from' => 'right', 'show_logo' => false, 'tagline' => 'Book a room'], $this->menus()->mobile());
    }

    public function test_reset_lists_the_apps_standard_menus_and_saves_them(): void
    {
        $this->useAppDefaults();
        $this->menus()->save([['type' => 'url', 'url' => '/x', 'label' => 'Custom']], ['columns' => []], []);

        $component = Livewire::test(ManageNavigation::class)->mountAction(TestAction::make('resetToDefault'));
        $description = (string) $component->instance()->getMountedAction()->getModalDescription();

        foreach ([
            'Header:</strong> What&#039;s on, Visit (Find us, Opening times), Blog',
            'Footer:</strong> Blog, What&#039;s on, Privacy Policy, Cookie settings, with the tagline &quot;Your village hall&quot; and the copyright line &quot;{site_name}, est. {year}&quot;',
            'opens from the right, without the logo, and the tagline &quot;Book a room&quot;',
            'This can&#039;t be undone.',
        ] as $text) {
            $this->assertStringContainsString($text, $description);
        }
        $this->assertStringNotContainsString('Members', $description);

        $component->callMountedAction();

        $this->assertTrue(app(Settings::class)->has(NavigationMenus::HEADER_KEY));
        $this->assertSame(['What\'s on', 'Visit', 'Blog'], array_column($this->menus()->header(), 'label'));
        $this->assertSame('Cookie settings', $this->menus()->footer()['columns'][1]['links'][1]['label']);
        $this->assertSame('right', $this->menus()->mobile()['open_from']);
    }

    public function test_a_menu_left_null_keeps_the_packages_standard_one(): void
    {
        app(SiteBuilder::class)->defaultMenus(header: [['type' => 'builtin', 'link' => 'whats_on']]);

        $this->assertSame(['What\'s on'], array_column($this->menus()->header(), 'label'));
        $this->assertSame(['Blog', 'Privacy Policy', 'Terms of Service'], array_column($this->menus()->footer()['columns'][0]['links'], 'label'));
        $this->assertSame(NavigationMenus::defaultMobile(), $this->menus()->mobile());

        // A later call that leaves the header null doesn't undo it.
        app(SiteBuilder::class)->defaultMenus(mobile: ['open_from' => 'right']);
        $this->assertSame(['What\'s on'], array_column($this->menus()->header(), 'label'));
        $this->assertSame(['open_from' => 'right', 'show_logo' => true, 'tagline' => ''], $this->menus()->mobile());
    }

    public function test_links_that_dont_apply_are_left_out_of_the_apps_defaults(): void
    {
        app(SiteBuilder::class)->withoutLinks(['blog']);
        app(SiteBuilder::class)->defaultMenus(
            header: [
                ['type' => 'builtin', 'link' => 'members'],
                ['type' => 'none', 'label' => 'Only members', 'children' => [['type' => 'builtin', 'link' => 'members']]],
                ['type' => 'none', 'label' => 'More', 'menu' => 'mega', 'columns' => [
                    ['heading' => 'Members', 'links' => [['type' => 'builtin', 'link' => 'members']]],
                    ['heading' => 'Events', 'links' => [['type' => 'builtin', 'link' => 'members'], ['type' => 'builtin', 'link' => 'whats_on']]],
                ]],
                ['type' => 'builtin', 'link' => 'blog'],
            ],
            footer: ['columns' => [['heading' => 'Members', 'links' => [['type' => 'builtin', 'link' => 'members']]]]],
        );

        $header = $this->menus()->standardHeader();

        $this->assertSame(['More'], array_column($header, 'label'));
        $this->assertSame(['Events'], array_column($header[0]['columns'], 'heading'));
        $this->assertSame(['whats_on'], array_column($header[0]['columns'][0]['links'], 'link'));
        $this->assertSame([], $this->menus()->standardFooter()['columns']);
        $this->assertSame(['More (What\'s on)'], $this->menus()->standardMenuLabels()['header']);
    }

    public function test_bad_defaults_are_checked_like_saved_menus_and_cant_break_the_page(): void
    {
        app(SiteBuilder::class)->defaultMenus(
            header: array_fill(0, 9, ['type' => 'url', 'url' => '/x', 'label' => 'X', 'extra' => 'dropped']),
            footer: fn (): string => 'not a footer',
            mobile: fn (): array => throw new RuntimeException('Broken default'),
        );

        $header = $this->menus()->standardHeader();
        $this->assertCount(NavigationMenus::MAX_HEADER_ITEMS, $header);
        $this->assertArrayNotHasKey('extra', $header[0]);
        // The footer and mobile menu fall back to the package's standard ones.
        $this->assertSame(['Blog', 'Privacy Policy', 'Terms of Service'], array_column($this->menus()->footer()['columns'][0]['links'], 'label'));
        $this->assertSame(NavigationMenus::defaultMobile(), $this->menus()->mobile());

        app(SiteBuilder::class)->defaultMenus(header: [['type' => 'url', 'url' => 'javascript:alert(1)', 'label' => 'Bad'], 'nonsense', ['type' => 'builtin', 'link' => 'whats_on']]);
        $this->assertSame(['What\'s on'], array_column($this->menus()->header(), 'label'));
        $this->get('/blog')->assertSuccessful();
    }

    public function test_closures_are_read_each_time_so_labels_follow_the_language(): void
    {
        $label = 'Visit us';
        app(SiteBuilder::class)->defaultMenus(header: function () use (&$label): array {
            return [['type' => 'url', 'url' => '/visit', 'label' => $label]];
        });

        $this->assertSame(['Visit us'], array_column($this->menus()->header(), 'label'));

        $label = 'Rendez-nous visite';
        $this->assertSame(['Rendez-nous visite'], array_column($this->menus()->header(), 'label'));
    }

    public function test_saved_menus_win_over_the_apps_defaults(): void
    {
        $this->menus()->save([['type' => 'builtin', 'link' => 'blog']], ['tagline' => 'Ours', 'columns' => []], ['open_from' => 'top']);
        $this->useAppDefaults();

        $this->assertSame(['Blog'], array_column($this->menus()->header(), 'label'));
        $this->assertSame('Ours', $this->menus()->footer()['tagline']);
        $this->assertSame('top', $this->menus()->mobile()['open_from']);
    }

    private function useAppDefaults(): void
    {
        app(SiteBuilder::class)->defaultMenus(
            header: fn (): array => [
                ['type' => 'builtin', 'link' => 'whats_on', 'label' => null, 'new_tab' => false],
                ['type' => 'none', 'label' => 'Visit', 'menu' => 'dropdown', 'children' => [
                    ['type' => 'url', 'url' => '/find-us', 'label' => 'Find us'],
                    ['type' => 'url', 'url' => '/opening-times', 'label' => 'Opening times'],
                    ['type' => 'builtin', 'link' => 'members'],
                ]],
                ['type' => 'builtin', 'link' => 'members'],
                ['type' => 'builtin', 'link' => 'blog'],
            ],
            footer: fn (): array => [
                'tagline' => 'Your village hall',
                'copyright' => '{site_name}, est. {year}',
                'columns' => [
                    ['heading' => 'About', 'links' => [['type' => 'builtin', 'link' => 'blog'], ['type' => 'builtin', 'link' => 'whats_on']]],
                    ['heading' => 'Legal', 'links' => [['type' => 'builtin', 'link' => 'privacy_policy'], ['type' => 'builtin', 'link' => 'cookie_settings'], ['type' => 'builtin', 'link' => 'members']]],
                ],
            ],
            mobile: ['open_from' => 'right', 'show_logo' => false, 'tagline' => 'Book a room'],
        );
    }

    private function menus(): NavigationMenus
    {
        return app(NavigationMenus::class);
    }
}
