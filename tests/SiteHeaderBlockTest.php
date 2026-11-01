<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SiteHeaderBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\TemplateVisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;

/**
 * The Header bar: a whole header in one block (logo, links, account links, a button and the menu button), laid out by
 * a style, with Edit links changing Navigation's header links where you are.
 */
class SiteHeaderBlockTest extends PageTestCase
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

        $router->get('/login', fn () => 'Log in')->name('login');
    }

    private function headerHtml(array $data = []): string
    {
        $slug = 'header-'.md5(json_encode($data).microtime());
        $this->page(['slug' => $slug, 'blocks' => [self::block('site_header', $data)]]);

        return $this->get('/'.$slug)->assertSuccessful()->getContent();
    }

    public function test_it_is_offered_only_in_header_templates_and_never_cached(): void
    {
        $registry = app(BlockRegistry::class);

        $this->assertArrayNotHasKey('site_header', $registry->offered());
        $this->assertArrayHasKey('site_header', $registry->editingTemplate('header')->offered());
        $this->assertArrayNotHasKey('site_header', $registry->editingTemplate('footer')->offered());
        $this->assertArrayHasKey('site_logo', $registry->editingTemplate('header')->offered(), 'The Logo, Menu and Account links blocks stay.');
        $this->assertFalse(SiteHeaderBlock::isCacheable([]));
    }

    public function test_the_standard_header_bar_has_the_logo_links_log_in_and_a_button(): void
    {
        $html = $this->headerHtml();

        $this->assertStringContainsString('class="sb-header-bar bg-sb-header-bg text-sb-header-text border-b border-current/10"', $html);
        $this->assertMatchesRegularExpression('/aria-label="Example Site home">.*Example Site/s', $html);
        $this->assertMatchesRegularExpression('/<nav class="hidden lg:flex items-center gap-8" aria-label="Main">.*Pricing.*Blog/s', $html);
        $this->assertStringContainsString('>Log in</a>', $html);
        $this->assertMatchesRegularExpression('/<a href="http:\/\/localhost#pricing"\s+class="hidden lg:inline-flex[^"]*bg-sb-header-button[^"]*">Get started<\/a>/', $html);
        $this->assertMatchesRegularExpression('/<button type="button"[^>]*class="inline-flex lg:hidden text-sb-header-link sb-menu-button/', $html);
        // The mobile menu ends with Log in and the button too.
        $this->assertSame(2, substr_count($html, '>Get started</a>'));
    }

    public function test_its_parts_and_button_can_be_changed(): void
    {
        $html = $this->headerHtml([
            'show_logo' => false,
            'show_account' => false,
            'cta_label' => 'Book now',
            'cta_url' => 'javascript:alert(1)',
            'cta_style' => '2',
            'cta_on_phones' => true,
            'cta_new_tab' => true,
            'breakpoint' => 'md',
            'height' => 'tall',
            'content_width' => 'full',
            'header_background' => 'none',
            'border' => false,
            'link_color' => '#0a3d2e',
        ]);

        $this->assertStringNotContainsString('aria-label="Example Site home"', $html);
        $this->assertStringNotContainsString('>Log in</a>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertMatchesRegularExpression('/<a href="http:\/\/localhost#pricing"\s+target="_blank" rel="noopener"\s+class="inline-flex items-center whitespace-nowrap site-button site-button-2 px-4 py-2 font-semibold">Book now<\/a>/', $html);
        $this->assertMatchesRegularExpression('/class="sb-header-bar"\s+style="--color-sb-header-link: #0a3d2e;"/', $html, 'No Branding background or line.');
        $this->assertStringContainsString('min-h-20', $html);
        $this->assertStringContainsString('max-w-none', $html);
        $this->assertStringContainsString('hidden md:flex', $html);
    }

    public function test_the_button_shows_to_visitors_not_signed_in_unless_everyone(): void
    {
        $this->actingAs($this->user());

        $this->assertStringNotContainsString('Get started', $this->headerHtml());
        $this->assertStringContainsString('Get started', $this->headerHtml(['cta_for' => 'everyone']));
    }

    public function test_each_style_lays_out_the_header(): void
    {
        app(NavigationMenus::class)->saveHeader([
            ['type' => 'url', 'url' => '/one', 'label' => 'One'],
            ['type' => 'url', 'url' => '/two', 'label' => 'Two'],
            ['type' => 'url', 'url' => '/three', 'label' => 'Three'],
        ]);

        $split = $this->headerHtml(['style' => 'split']);
        $this->assertMatchesRegularExpression('/aria-label="Main">\s*<a href="\/one".*<a href="\/two".*aria-label="Example Site home".*aria-label="More">\s*<a href="\/three"/s', $split);

        $this->assertMatchesRegularExpression('/<div class="grid grid-cols-\[1fr_auto_1fr\][^"]*">\s*<div class="flex">.*aria-label="Example Site home".*<nav class="hidden lg:flex items-center justify-center gap-8"/s', $this->headerHtml(['style' => 'links_centre']));
        $this->assertMatchesRegularExpression('/<div class="flex justify-center">.*aria-label="Example Site home".*<nav class="hidden lg:flex items-center justify-center gap-8 border-t/s', $this->headerHtml(['style' => 'centred_logo']));

        $menuOnly = $this->headerHtml(['style' => 'menu_only']);
        $this->assertStringNotContainsString('aria-label="Main">', substr($menuOnly, 0, (int) strpos($menuOnly, 'role="dialog"')), 'No links in the bar.');
        $this->assertMatchesRegularExpression('/<button type="button"[^>]*class="inline-flex text-sb-header-link sb-menu-button/', $menuOnly, 'The menu button at every size.');
        $this->assertDoesNotMatchRegularExpression('/role="dialog"[^>]*class="[^"]*lg:hidden/s', $menuOnly, 'Nor is the mobile menu hidden on wide screens.');
    }

    public function test_edit_links_saves_the_header_links_from_the_template(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $template = Template::query()->create(['name' => 'Main header', 'type' => 'header', 'blocks' => [self::block('site_header')]]);

        Livewire::test(TemplateVisualEditor::class, ['record' => $template->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $template->blocks[0]['uid']]))
            ->callAction(TestAction::make('editHeaderLinks')->schemaComponent('header_links_actions', 'mountedActionSchema0'), [
                'header' => [['type' => 'url', 'url' => '/book', 'label' => 'Book a room']],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Header links saved');

        $this->assertSame(['Book a room'], array_column(app(NavigationMenus::class)->header(), 'label'));
    }

    public function test_its_own_background_wins_over_the_branding_header_colours(): void
    {
        $html = $this->headerHtml(['appearance' => ['background' => 'custom', 'background_color' => '#4a1d3f']]);

        $this->assertStringContainsString('background-color: #4a1d3f;', $html);
        $this->assertDoesNotMatchRegularExpression('/class="sb-header-bar[^"]*bg-sb-header-bg/', $html, 'The Branding colour no longer covers it.');
        $this->assertStringContainsString('--color-sb-header-link: currentColor;', $html, 'Links follow its (light) text.');

        $ownLinks = $this->headerHtml(['appearance' => ['background' => 'custom', 'background_color' => '#4a1d3f'], 'link_color' => '#ffcc00']);
        $this->assertStringContainsString('--color-sb-header-link: #ffcc00;', $ownLinks);
        $this->assertStringNotContainsString('--color-sb-header-link: currentColor;', $ownLinks);
    }
}
