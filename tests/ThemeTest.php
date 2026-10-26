<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\SiteBuilderServiceProvider;

class ThemeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_theme_views_are_found_before_saasykits(): void
    {
        $finder = $this->app['view']->getFinder();

        $this->assertSame(SiteBuilderServiceProvider::themePath(), $finder->getPaths()[0]);
        $this->assertStringStartsWith(SiteBuilderServiceProvider::themePath(), $finder->find('home'));
        $this->assertStringStartsWith(SiteBuilderServiceProvider::themePath(), $finder->find('components.layouts.app'));
    }

    public function test_the_theme_can_be_switched_off(): void
    {
        $this->refreshApplicationWithConfig(['site-builder.theme.enabled' => false]);

        $this->assertNotContains(SiteBuilderServiceProvider::themePath(), $this->app['view']->getFinder()->getPaths());
    }

    public function test_the_apps_own_theme_views_win_over_the_theme_which_wins_over_saasykits(): void
    {
        [$appTheme, $saasykit] = $this->useAppThemeFolder();
        $finder = $this->app['view']->getFinder();

        $this->assertSame([realpath($appTheme), SiteBuilderServiceProvider::themePath(), realpath($saasykit)], $finder->getPaths());
        $this->assertStringContainsString("The app's own home page", view('home')->render());
        // Views the app doesn't override still come from the theme, ahead of SaaSykit's.
        $this->assertStringStartsWith(SiteBuilderServiceProvider::themePath(), $finder->find('components.layouts.app'));
    }

    public function test_without_an_app_theme_folder_the_theme_wins_over_saasykits_views(): void
    {
        [, $saasykit] = $this->useAppThemeFolder(viewPaths: fn (string $appTheme, string $saasykit): array => [$saasykit]);

        $this->assertSame([SiteBuilderServiceProvider::themePath(), realpath($saasykit)], $this->app['view']->getFinder()->getPaths());
        $this->assertStringStartsWith(SiteBuilderServiceProvider::themePath(), $this->app['view']->getFinder()->find('home'));
    }

    public function test_with_the_theme_off_saasykits_views_are_used(): void
    {
        [, $saasykit] = $this->useAppThemeFolder(viewPaths: fn (string $appTheme, string $saasykit): array => [$saasykit], themeEnabled: false);

        $this->assertNotContains(SiteBuilderServiceProvider::themePath(), $this->app['view']->getFinder()->getPaths());
        $this->assertStringContainsString("SaaSykit's home page", view('home')->render());
    }

    public function test_the_logo_falls_back_to_the_site_name(): void
    {
        $this->assertStringContainsString('Example Site', Blade::render('<x-site-builder::logo place="header" />'));

        $this->app->make(Branding::class)->save([Branding::LOGO_LIGHT => 'site-builder/branding/logo.png']);

        $html = Blade::render('<x-site-builder::logo place="header" />');
        $this->assertStringContainsString('site-builder/branding/logo.png', $html);
        $this->assertStringContainsString('alt="Example Site"', $html);

        // The footer uses the dark-background logo by default, which isn't uploaded.
        $this->assertStringContainsString('Example Site', Blade::render('<x-site-builder::logo place="footer" />'));
    }

    public function test_banners_use_the_standard_gradient_until_branding_sets_one(): void
    {
        $html = Blade::render('<x-site-builder::banner variant="hero"><h1>Hello</h1></x-site-builder::banner>');

        $this->assertStringContainsString('from-primary-900', $html);
        $this->assertStringContainsString('--color-sb-banner-heading: var(--color-sb-hero-heading)', $html);
        $this->assertStringContainsString('<h1>Hello</h1>', $html);
        $this->assertStringNotContainsString('overflow-hidden relative', $html, 'The banner itself must not clip its content (lesson 8).');

        $this->app->make(Branding::class)->save([
            Branding::bannerKey('hero', 'background_type') => Branding::BANNER_TYPE_COLOR,
            Branding::bannerKey('hero', 'background_color') => '#123456',
        ]);

        $html = Blade::render('<x-site-builder::banner variant="hero">Hi</x-site-builder::banner>');
        $this->assertStringContainsString('background-color: #123456', $html);
        $this->assertStringNotContainsString('from-primary-900', $html);
    }

    public function test_the_branding_head_outputs_icons_fonts_and_css_without_google_fonts(): void
    {
        File::copyDirectory(__DIR__.'/../resources/fonts', public_path(Branding::PACKAGE_FONTS_DIRECTORY));

        $this->app->make(Branding::class)->save([
            Branding::APPLE_TOUCH_ICON => 'site-builder/branding/apple.png',
            Branding::HEADER_BG_COLOR => '#003300',
            Branding::CUSTOM_CSS => '.promo { color: #123456; }',
        ]);

        $html = view('site-builder::branding.head')->render();

        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('vendor/site-builder/fonts/poppins/font.css', $html);
        $this->assertStringContainsString('--color-sb-header-bg: #003300', $html);
        $this->assertStringContainsString('<style id="site-builder-custom-css">.promo { color: #123456; }</style>', $html);
        $this->assertStringNotContainsString('fonts.googleapis', $html);

        File::deleteDirectory(public_path('vendor/site-builder'));
    }

    public function test_unsafe_custom_css_is_not_output(): void
    {
        $this->app->make(Branding::class)->save([Branding::CUSTOM_CSS => '</style><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', view('site-builder::branding.head')->render());
    }

    /**
     * An app whose view.paths are its own theme folder, then SaaSykit's views (as in an app's config/view.php), from
     * tests/Fixtures/theme.
     *
     * @param  (\Closure(string, string): array<int, string>)|null  $viewPaths
     * @return array{string, string} the app theme folder and the SaaSykit views folder
     */
    private function useAppThemeFolder(?\Closure $viewPaths = null, bool $themeEnabled = true): array
    {
        $appTheme = __DIR__.'/Fixtures/theme/app-theme';
        $saasykit = __DIR__.'/Fixtures/theme/saasykit';

        $this->refreshApplicationWithConfig([
            'site-builder.theme.enabled' => $themeEnabled,
            'site-builder.theme.app_path' => $appTheme,
            'view.paths' => $viewPaths ? $viewPaths($appTheme, $saasykit) : [$appTheme, $saasykit],
        ]);

        return [$appTheme, $saasykit];
    }

    /**
     * Changes config, then resolves the view factory again (the theme path is added when it's resolved).
     *
     * @param  array<string, mixed>  $config
     */
    private function refreshApplicationWithConfig(array $config): void
    {
        $this->app['config']->set($config);
        $this->app->forgetInstance('view');
        $this->app->forgetInstance('view.finder');
        Facade::clearResolvedInstance('view');
    }
}
