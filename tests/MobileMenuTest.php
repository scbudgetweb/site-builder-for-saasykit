<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\Storage;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Media\MediaLibrary;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;
use ScBudgetWeb\SiteBuilder\Settings\Settings;

/**
 * Website › Navigation › Mobile menu: how it opens, its links, background, what's at the bottom and the menu button,
 * the same for the standard header, the Menu block and the Header bar (here, through the Menu block).
 */
class MobileMenuTest extends PageTestCase
{
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->get('/login', fn () => 'Log in')->name('login');
    }

    /**
     * @param  array<string, mixed>  $mobile
     * @param  array<string, mixed>  $block
     */
    private function menuHtml(array $mobile = [], array $block = []): string
    {
        $menus = app(NavigationMenus::class);
        $menus->save($menus->headerItems(), $menus->footerSettings(), [...NavigationMenus::defaultMobile(), ...$mobile]);
        $slug = 'menu-'.md5(json_encode([$mobile, $block]).microtime());
        $this->page(['slug' => $slug, 'blocks' => [self::block('site_menu', $block)]]);

        return $this->get('/'.$slug)->assertSuccessful()->getContent();
    }

    public function test_the_standard_menu_drops_down_with_sliding_links_and_lines(): void
    {
        $html = $this->menuHtml();

        $this->assertMatchesRegularExpression('/class="sb-mobile-menu-panel absolute flex flex-col bg-sb-header-bg text-sb-header-text inset-0 sb-mm-animate"/', $html);
        $this->assertStringContainsString('x-transition:enter-start="-translate-y-full"', $html);
        $this->assertMatchesRegularExpression('/style="--sb-mm-i: 0"[^>]*class="sb-mm-item border-b border-current\/10 block py-4 text-lg font-semibold/s', $html);
        $this->assertStringContainsString('sb-mm-close-line-1', $html, 'The close button\'s lines turn into an X.');
        $this->assertStringNotContainsString('backdrop-blur-sm', $html);
    }

    public function test_it_can_slide_in_from_either_side_or_fill_the_screen(): void
    {
        $right = $this->menuHtml(['open_from' => 'right']);
        $this->assertStringContainsString('inset-y-0 w-full shadow-2xl sm:max-w-sm right-0', $right);
        $this->assertStringContainsString('class="absolute inset-0 bg-black/40 backdrop-blur-sm"', $right, 'The page beside it, dimmed and blurred.');

        $left = $this->menuHtml(['open_from' => 'left', 'blur' => false]);
        $this->assertStringContainsString('x-transition:enter-start="-translate-x-full"', $left);
        $this->assertStringContainsString('class="absolute inset-0 bg-black/40"', $left);

        $full = $this->menuHtml(['open_from' => 'full', 'link_lines' => true]);
        $this->assertStringContainsString('flex flex-col justify-center', $full);
        $this->assertMatchesRegularExpression('/class="sb-mm-item block py-4 sb-mm-heading text-2xl font-semibold[^"]*text-center/', $full, 'Large, centred links with no lines.');
    }

    public function test_link_size_line_up_lines_and_entrance_can_change(): void
    {
        $html = $this->menuHtml(['link_size' => 'xlarge', 'link_align' => 'center', 'link_lines' => false, 'animate_links' => false]);

        $this->assertMatchesRegularExpression('/class="sb-mm-item block py-4 sb-mm-heading text-3xl font-bold sm:text-4xl[^"]*text-center/', $html);
        $this->assertStringNotContainsString('sb-mm-animate', $html);
    }

    public function test_a_colour_gradient_or_image_background_with_readable_text(): void
    {
        $this->assertStringContainsString('background-color: #4a1d3f; --color-sb-header-bg: transparent; --color-sb-header-text: #ffffff;', $this->menuHtml(['background' => 'colour', 'background_color' => '#4a1d3f']));
        $this->assertStringContainsString('--color-sb-header-text: #111827;', $this->menuHtml(['background' => 'colour', 'background_color' => '#fef3c7']), 'Near-black on a light colour.');
        $this->assertStringContainsString('background-image: linear-gradient(160deg, #0a3d2e, #1e40af);', $this->menuHtml(['background' => 'gradient', 'background_color' => '#0a3d2e', 'background_color_2' => '#1e40af', 'text_color' => '#fde68a']));

        Storage::fake('public');
        Storage::disk('public')->put('site-builder/pages/menu.jpg', 'image');
        $image = $this->menuHtml(['background' => 'image', 'background_image' => 'site-builder/pages/menu.jpg', 'overlay_opacity' => 60]);
        $this->assertStringContainsString('rgba(0, 0, 0, 0.6)', $image);
        $this->assertStringContainsString('url(&#039;/storage/site-builder/pages/menu.jpg&#039;)', $image);
        $this->assertTrue(app(MediaLibrary::class)->isUsed('site-builder/pages/menu.jpg'), 'The library counts it as in use.');

        $this->assertStringNotContainsString('#4a1d3f', $this->menuHtml(['background' => 'colour', 'background_color' => '#4a1d3f'], ['mobile_background' => '#ffffff']), 'A block\'s own mobile colours win.');
    }

    public function test_social_icons_and_a_contact_line_at_the_bottom(): void
    {
        config(['app.social_links' => ['facebook' => 'https://facebook.com/village-hall', 'x' => 'javascript:alert(1)']]);

        $html = $this->menuHtml(['show_social' => true, 'contact_phone' => '01234 567 890', 'contact_email' => 'hello@example.com']);

        $this->assertStringContainsString('href="https://facebook.com/village-hall"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="tel:01234567890"', $html);
        $this->assertStringContainsString('href="mailto:hello@example.com"', $html);
    }

    public function test_the_menu_button_looks(): void
    {
        $standard = $this->menuHtml();
        $this->assertMatchesRegularExpression('/sb-menu-button[^"]*h-10 w-10 rounded-md/', $standard);
        $this->assertStringContainsString('d="M4 6h16M4 12h16M4 18h16"', $standard);

        $dots = $this->menuHtml(['button_icon' => 'dots', 'button_shape' => 'circle', 'button_label' => true]);
        $this->assertMatchesRegularExpression('/sb-menu-button[^"]*h-11 px-3 rounded-full bg-current\/10/', $dots);
        $this->assertStringContainsString('<span class="text-sm font-semibold">Menu</span>', $dots);
        $this->assertStringContainsString('<circle cx="5" cy="12" r="2"/>', $dots);

        $this->assertStringContainsString('d="M4 9h16M4 15h16"', $this->menuHtml(['button_icon' => 'two_lines']));
    }

    public function test_saved_settings_are_checked(): void
    {
        $menus = app(NavigationMenus::class);
        $menus->save($menus->headerItems(), $menus->footerSettings(), [
            'open_from' => 'sideways',
            'link_size' => 'huge',
            'background' => 'image',
            'background_image' => 'site-builder/branding/logo.png',
            'background_color' => 'red; x: y',
            'overlay_opacity' => 400,
            'contact_email' => 'not an email',
            'contact_phone' => str_repeat('1', 80),
            'animate_links' => false,
        ]);

        $mobile = $menus->mobile();
        $this->assertSame(['top', 'normal', 'image', null, null, 100, '', false], [$mobile['open_from'], $mobile['link_size'], $mobile['background'], $mobile['background_image'], $mobile['background_color'], $mobile['overlay_opacity'], $mobile['contact_email'], $mobile['animate_links']]);
        $this->assertSame(40, mb_strlen($mobile['contact_phone']));
        $this->assertSame('', NavigationMenus::mobileStyle($mobile), 'An image background without an image draws the header colours.');
        $this->assertSame(NavigationMenus::defaultMobile()['button_icon'], app(Settings::class)->get(NavigationMenus::MOBILE_KEY)['button_icon']);
    }

    public function test_on_a_background_of_its_own_it_shows_the_logo_that_reads_on_it(): void
    {
        $this->assertNull(NavigationMenus::mobileLogoVariant(NavigationMenus::defaultMobile()), 'On the header colours, the header\'s logo.');
        $this->assertSame('dark', NavigationMenus::mobileLogoVariant(['background' => 'gradient', 'background_color' => '#0b1220', 'background_color_2' => '#134e4a']));
        $this->assertSame('light', NavigationMenus::mobileLogoVariant(['background' => 'colour', 'background_color' => '#fef3c7']));
        $this->assertSame('light', NavigationMenus::mobileLogoVariant(['background' => 'colour', 'background_color' => '#0b1220', 'text_color' => '#111827']), 'Text colour chosen: dark text, the logo for light backgrounds.');

        Storage::fake('public');
        app(Branding::class)->save([Branding::LOGO_LIGHT => 'site-builder/branding/light.svg', Branding::LOGO_DARK => 'site-builder/branding/dark.svg']);

        $html = $this->menuHtml(['background' => 'gradient', 'background_color' => '#0b1220', 'background_color_2' => '#134e4a']);
        $menu = substr($html, strpos($html, 'sb-mobile-menu-panel'));
        $this->assertStringContainsString('site-builder/branding/dark.svg', $menu);
        $this->assertStringNotContainsString('site-builder/branding/light.svg', $menu);
    }
}
