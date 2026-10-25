<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\Blade;
use ScBudgetWeb\SiteBuilder\Navigation\Link;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\SiteBuilderServiceProvider;

/**
 * The header, footer and mobile menus (Website › Navigation, HB-95), and how the theme shows them.
 */
class NavigationMenusTest extends PageTestCase
{
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->get('/privacy-policy', fn () => 'Privacy')->name('privacy-policy');
        $router->get('/terms-of-service', fn () => 'Terms')->name('terms-of-service');
    }

    public function test_with_nothing_saved_the_menus_are_the_themes_standard_links(): void
    {
        // Roadmap is off and its route doesn't exist here, so it's skipped.
        $this->assertSame([['Pricing', 'http://localhost#pricing'], ['Blog', 'http://localhost/blog']], $this->labelsAndUrls($this->menus()->header()));

        config(['app.description' => 'Book your local hall.']);
        $footer = $this->menus()->footer();

        $this->assertSame('Book your local hall.', $footer['tagline']);
        $this->assertSame([['Blog', 'http://localhost/blog'], ['Privacy Policy', 'http://localhost/privacy-policy'], ['Terms of Service', 'http://localhost/terms-of-service']], $this->labelsAndUrls($footer['columns'][0]['links']));
        $this->assertSame('© '.date('Y').' Example Site. All rights reserved.', $footer['copyright']);
        $this->assertSame(['open_from' => 'top', 'show_logo' => true, 'tagline' => ''], $this->menus()->mobile());
    }

    public function test_built_in_links_follow_their_condition_and_apps_can_add_or_replace_them(): void
    {
        app(SiteBuilder::class)->links([
            Link::make('halls', 'Find a hall', fn (): string => url('/halls'))->description('the hall search'),
            Link::make('blog', 'News', fn (): string => url('/news')),
            Link::make('hidden', 'Hidden', fn (): string => url('/hidden'))->availableWhen(fn (): bool => false),
        ]);

        $this->save([self::builtin('halls'), self::builtin('blog'), self::builtin('hidden'), self::builtin('roadmap'), self::builtin('no_such_link')]);

        $this->assertSame([['Find a hall', 'http://localhost/halls'], ['News', 'http://localhost/news']], $this->labelsAndUrls($this->menus()->header()));
        $this->assertSame('Find a hall (the hall search)', $this->menus()->builtinOptions()['halls']);
    }

    public function test_a_page_link_follows_the_page_and_disappears_when_it_is_not_published(): void
    {
        $page = $this->page(['title' => 'About us', 'slug' => 'about-us']);
        $this->save([['type' => 'page', 'page_id' => $page->id], ['type' => 'page', 'page_id' => $page->id, 'label' => 'Our story']]);

        $this->assertSame([['About us', 'http://localhost/about-us'], ['Our story', 'http://localhost/about-us']], $this->labelsAndUrls($this->menus()->header()));

        $page->update(['slug' => 'who-we-are']);
        $this->assertSame('http://localhost/who-we-are', $this->menus()->header()[0]['url']);

        $page->update(['replaces' => 'home']);
        $this->assertSame('http://localhost', $this->menus()->header()[0]['url']);

        $page->update(['status' => PageStatus::Draft]);
        $this->assertSame([], $this->menus()->header());

        $page->update(['status' => PageStatus::Published]);
        $page->delete();
        $this->assertSame([], $this->menus()->header());
    }

    public function test_unsafe_or_incomplete_links_are_dropped(): void
    {
        $this->save([
            ['type' => 'url', 'url' => 'javascript:alert(1)', 'label' => 'Bad'],
            ['type' => 'url', 'url' => 'https://example.com', 'label' => ''],
            ['type' => 'url', 'url' => 'mailto:hello@example.com', 'label' => 'Email us', 'new_tab' => true],
            ['type' => 'none', 'label' => 'Empty dropdown'],
            ['type' => 'nonsense', 'label' => 'Unknown'],
        ]);

        $header = $this->menus()->header();

        $this->assertSame([['Email us', 'mailto:hello@example.com']], $this->labelsAndUrls($header));
        $this->assertTrue($header[0]['new_tab']);
    }

    public function test_dropdowns_and_mega_menus_resolve_their_links(): void
    {
        $this->save([
            ['type' => 'none', 'label' => 'About', 'children' => [self::builtin('blog'), ['type' => 'url', 'url' => '/contact', 'label' => 'Contact'], ['type' => 'url', 'url' => 'javascript:x', 'label' => 'Bad']]],
            ['type' => 'none', 'label' => 'Rooms', 'menu' => 'mega', 'columns' => [
                ['heading' => 'Spaces', 'links' => [['type' => 'url', 'url' => '/main-hall', 'label' => 'Main hall', 'description' => 'Seats 120', 'icon' => 'bx:regular/bx-home'], ['type' => 'url', 'url' => '/kitchen', 'label' => 'Kitchen', 'icon' => 'bx:regular/no-such-icon']]],
                ['heading' => 'Nothing usable', 'links' => [['type' => 'url', 'url' => 'javascript:x', 'label' => 'Bad']]],
            ], 'promo' => ['heading' => 'Book today', 'text' => 'From £15 an hour', 'button_label' => 'Book', 'button_url' => 'javascript:x']],
            ['type' => 'none', 'label' => 'Empty mega', 'menu' => 'mega', 'columns' => []],
        ]);

        $header = $this->menus()->header();

        $this->assertCount(2, $header);
        $this->assertSame([['Blog', 'http://localhost/blog'], ['Contact', '/contact']], $this->labelsAndUrls($header[0]['children']));
        $this->assertNull($header[0]['url']);

        $mega = $header[1]['mega'];
        $this->assertCount(1, $mega['columns']);
        $this->assertSame(['Seats 120', 'bx:regular/bx-home'], [$mega['columns'][0]['links'][0]['description'], $mega['columns'][0]['links'][0]['icon']]);
        $this->assertNull($mega['columns'][0]['links'][1]['icon']);
        $this->assertSame(['Book today', null, null], [$mega['promo']['heading'], $mega['promo']['button_label'], $mega['promo']['button_url']]);
    }

    public function test_saving_keeps_only_known_fields_and_limits_and_reset_brings_the_defaults_back(): void
    {
        $items = array_fill(0, 8, ['type' => 'url', 'url' => '/x', 'label' => 'X', 'extra' => 'dropped']);
        $this->menus()->save($items, ['tagline' => ' Hello ', 'copyright' => '{site_name} {year}', 'columns' => array_fill(0, 6, ['heading' => 'H', 'links' => [self::builtin('blog')]])], ['open_from' => 'sideways', 'show_logo' => false, 'tagline' => 'Hi']);

        $saved = app(Settings::class)->get(NavigationMenus::HEADER_KEY);
        $this->assertCount(NavigationMenus::MAX_HEADER_ITEMS, $saved);
        $this->assertArrayNotHasKey('extra', $saved[0]);
        $this->assertCount(NavigationMenus::MAX_FOOTER_COLUMNS, $this->menus()->footer()['columns']);
        $this->assertSame(['Hello', 'Example Site '.date('Y')], [$this->menus()->footer()['tagline'], $this->menus()->footer()['copyright']]);
        $this->assertSame(['open_from' => 'top', 'show_logo' => false, 'tagline' => 'Hi'], $this->menus()->mobile());

        $this->menus()->reset();

        $this->assertSame(NavigationMenus::defaultHeader(), $this->menus()->headerItems());
        $this->assertTrue($this->menus()->mobile()['show_logo']);
    }

    public function test_settings_of_the_wrong_shape_read_as_the_defaults(): void
    {
        app(Settings::class)->set([NavigationMenus::HEADER_KEY => 'nonsense', NavigationMenus::FOOTER_KEY => 'nonsense', NavigationMenus::MOBILE_KEY => 'nonsense']);

        $this->assertSame(NavigationMenus::defaultHeader(), $this->menus()->headerItems());
        $this->assertSame('Blog', $this->menus()->footer()['columns'][0]['links'][0]['label']);
        $this->assertSame('top', $this->menus()->mobile()['open_from']);
    }

    public function test_the_current_page_is_marked(): void
    {
        $this->get('/blog');

        $this->assertTrue($this->menus()->isCurrent('/blog'));
        $this->assertTrue($this->menus()->isCurrent('http://localhost/blog'));
        $this->assertFalse($this->menus()->isCurrent('/blog#latest'));
        $this->assertFalse($this->menus()->isCurrent('mailto:a@example.com'));
        $this->assertFalse($this->menus()->isCurrent(null));
    }

    public function test_the_theme_shows_the_desktop_links_dropdowns_mega_menus_and_footer(): void
    {
        $this->app['view']->getFinder()->prependLocation(SiteBuilderServiceProvider::themePath());

        $this->menus()->save(
            [
                self::builtin('blog'),
                ['type' => 'none', 'label' => 'About', 'children' => [['type' => 'url', 'url' => '/contact', 'label' => 'Contact us', 'new_tab' => true]]],
                ['type' => 'none', 'label' => 'Rooms', 'menu' => 'mega', 'columns' => [['heading' => 'Spaces', 'links' => [['type' => 'url', 'url' => '/main-hall', 'label' => 'Main hall', 'description' => 'Seats 120']]]], 'promo' => ['heading' => 'Book today']],
            ],
            ['tagline' => 'Your village hall', 'copyright' => '© {year} {site_name}', 'columns' => [['heading' => 'Visit', 'links' => [['type' => 'url', 'url' => '/find-us', 'label' => 'Find us']]]]],
            [],
        );

        $links = Blade::render('<x-layouts.app.navigation-links />');
        $this->assertStringContainsString('href="http://localhost/blog"', $links);
        $this->assertStringContainsString('Contact us', $links);
        $this->assertStringContainsString('target="_blank" rel="noopener"', $links);
        $this->assertMatchesRegularExpression('/<button[^>]*aria-expanded="false"[^>]*>\s*About/', $links);
        $this->assertStringContainsString('Seats 120', $links);
        $this->assertStringContainsString('Book today', $links);

        $footer = Blade::render('<x-layouts.app.footer />');
        $this->assertStringContainsString('Your village hall', $footer);
        $this->assertStringContainsString('>Visit</h2>', $footer);
        $this->assertStringContainsString('href="/find-us"', $footer);
        $this->assertStringContainsString('© '.date('Y').' Example Site', $footer);
    }

    private function menus(): NavigationMenus
    {
        return app(NavigationMenus::class);
    }

    /**
     * @param  array<int, array<string, mixed>>  $header
     */
    private function save(array $header): void
    {
        $this->menus()->save($header, NavigationMenus::defaultFooter(), []);
    }

    /**
     * @return array<string, mixed>
     */
    private static function builtin(string $key): array
    {
        return ['type' => 'builtin', 'link' => $key];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{0: string, 1: ?string}>
     */
    private function labelsAndUrls(array $items): array
    {
        return array_map(fn (array $item): array => [$item['label'], $item['url']], $items);
    }
}
