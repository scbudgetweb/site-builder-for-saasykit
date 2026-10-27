<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ScBudgetWeb\SiteBuilder\Branding\Branding;

class BrandingTest extends TestCase
{
    private Branding $branding;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        File::copyDirectory(__DIR__.'/../resources/fonts', public_path(Branding::PACKAGE_FONTS_DIRECTORY));
        $this->branding = $this->app->make(Branding::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('vendor/site-builder'));
        File::deleteDirectory(public_path(Branding::APP_FONTS_DIRECTORY));

        parent::tearDown();
    }

    public function test_nothing_saved_outputs_no_css_and_keeps_the_standard_icons(): void
    {
        $this->assertSame('', $this->branding->siteCssVariables());
        $this->assertStringEndsWith('images/favicon.ico', $this->branding->faviconUrl());
        $this->assertNull($this->branding->appleTouchIconUrl());
        $this->assertSame('poppins', $this->branding->siteFont());
        $this->assertNull($this->branding->appFont());
    }

    public function test_site_colours_override_saasykits_primary_and_secondary_palettes(): void
    {
        $this->branding->save([Branding::SITE_COLOR => '#2e6a4c', Branding::SITE_SECONDARY_COLOR => '#123456']);

        $css = $this->branding->siteCssVariables();

        $this->assertStringContainsString('--color-primary-500:', $css);
        $this->assertStringContainsString('--color-secondary-500:', $css);
        $this->assertStringNotContainsString('jobboard', $css);
    }

    public function test_element_colours_set_their_variables_and_invalid_values_are_ignored(): void
    {
        $this->branding->save([
            Branding::HEADER_BG_COLOR => '#003300',
            Branding::HEADER_LINK_COLOR => '#ffffff',
            Branding::FOOTER_BG_COLOR => 'red; } body { display:none',
        ]);

        $css = $this->branding->siteCssVariables();

        $this->assertStringContainsString('--color-sb-header-bg: #003300', $css);
        $this->assertStringContainsString('--color-sb-header-link: #ffffff', $css);
        $this->assertStringContainsString('--color-sb-header-account-link: #ffffff', $css);
        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringNotContainsString('--color-sb-footer-bg', $css);
    }

    public function test_text_size_and_heading_scale(): void
    {
        $this->branding->save([Branding::TEXT_SIZE => '18', Branding::HEADING_SCALE => 'large']);

        $css = $this->branding->siteCssVariables();

        $this->assertStringContainsString('--sb-text-base: 1.125rem', $css);
        $this->assertStringContainsString('--sb-heading-scale: 1.15', $css);

        $this->branding->save([Branding::TEXT_SIZE => '99', Branding::HEADING_SCALE => 'huge']);
        $this->assertSame('', $this->branding->siteCssVariables());
    }

    public function test_fonts_are_discovered_from_the_package_and_the_app_folders(): void
    {
        File::ensureDirectoryExists(public_path(Branding::APP_FONTS_DIRECTORY.'/figtree'));
        File::put(public_path(Branding::APP_FONTS_DIRECTORY.'/figtree/font.css'), '/* figtree */');
        File::ensureDirectoryExists(public_path(Branding::APP_FONTS_DIRECTORY.'/no_css'));

        $fonts = $this->app->make(Branding::class)->availableFonts();

        $this->assertSame('Open Sans', $fonts['open_sans']);
        $this->assertSame('Figtree', $fonts['figtree']);
        $this->assertArrayNotHasKey('no_css', $fonts);
        $this->assertStringContainsString('fonts/site-builder/figtree/font.css', $this->branding->fontStylesheetUrl('figtree'));
        $this->assertStringContainsString('vendor/site-builder/fonts/inter/font.css', $this->branding->fontStylesheetUrl('inter'));
    }

    public function test_chosen_fonts_become_css_variables_and_unknown_fonts_are_ignored(): void
    {
        $this->branding->save([Branding::SITE_FONT => 'open_sans', Branding::SITE_HEADING_FONT => 'montserrat']);

        $css = $this->branding->siteCssVariables();

        $this->assertStringContainsString("--font-sans: 'Open Sans'", $css);
        $this->assertStringContainsString("--font-sb-heading: 'Montserrat'", $css);

        $this->branding->save([Branding::SITE_HEADING_FONT => 'open_sans']);
        $this->assertNull($this->branding->siteHeadingFont(), 'The same font as the body text means no separate heading font.');

        $this->branding->save([Branding::SITE_FONT => 'comic_sans', Branding::SITE_HEADING_FONT => 'wingdings']);
        $this->assertSame('poppins', $this->branding->siteFont());
        $this->assertNull($this->branding->siteHeadingFont());

        // A name that isn't an available font can't carry CSS.
        $this->assertSame('Bad Fontstylestylebody', $this->branding->fontFamily("bad-font'</style><style>body"));
    }

    public function test_logos_icons_and_which_logo_shows_where(): void
    {
        $this->branding->save([
            Branding::LOGO_LIGHT => 'site-builder/branding/light.png',
            Branding::FAVICON => 'site-builder/branding/favicon.png',
            Branding::APPLE_TOUCH_ICON => 'site-builder/branding/apple.png',
            Branding::LOGO_FOOTER => 'light',
        ]);

        $this->assertStringEndsWith('site-builder/branding/light.png', $this->branding->logoUrlFor(Branding::LOGO_HEADER));
        $this->assertStringEndsWith('site-builder/branding/light.png', $this->branding->logoUrlFor(Branding::LOGO_FOOTER));
        $this->assertNull($this->branding->logoUrl('dark'));
        $this->assertStringEndsWith('site-builder/branding/favicon.png', $this->branding->faviconUrl());
        $this->assertStringEndsWith('site-builder/branding/apple.png', $this->branding->appleTouchIconUrl());
    }

    public function test_banner_resolution_and_validation(): void
    {
        $this->assertSame(['image' => null, 'imagePosition' => 'center', 'overlay' => null, 'gradient' => null, 'color' => null], $this->branding->banner('hero'));

        $this->branding->save([
            Branding::bannerKey('hero', 'gradient_start') => '#000000',
            Branding::bannerKey('hero', 'gradient_end') => '#ffffff',
            Branding::bannerKey('hero', 'gradient_direction') => '999',
        ]);
        $this->assertSame('linear-gradient(135deg, #000000, #ffffff)', $this->branding->banner('hero')['gradient']);

        $this->branding->save([
            Branding::bannerKey('inner', 'background_type') => Branding::BANNER_TYPE_IMAGE,
            Branding::bannerKey('inner', 'image') => 'site-builder/branding/banner.jpg',
            Branding::bannerKey('inner', 'overlay_enabled') => true,
            Branding::bannerKey('inner', 'overlay_opacity') => 250,
            Branding::bannerKey('inner', 'image_position') => 'sideways',
        ]);
        $inner = $this->branding->banner('inner');
        $this->assertStringEndsWith('banner.jpg', $inner['image']);
        $this->assertSame(1.0, $inner['overlay']['opacity']);
        $this->assertNull($inner['overlay']['gradient']);
        $this->assertSame('center', $inner['imagePosition']);

        // A gradient overlay, from the same settings as a block's image background.
        $this->branding->save([
            Branding::bannerKey('inner', 'overlay_type') => 'gradient',
            Branding::bannerKey('inner', 'overlay_direction') => 'to_right',
            Branding::bannerKey('inner', 'overlay_color') => '#000000',
            Branding::bannerKey('inner', 'overlay_opacity') => 70,
            Branding::bannerKey('inner', 'overlay_end_color') => '#ffffff',
            Branding::bannerKey('inner', 'overlay_end_opacity') => 0,
        ]);
        $this->assertSame('linear-gradient(90deg, rgba(0, 0, 0, 0.7), rgba(255, 255, 255, 0))', $this->branding->banner('inner')['overlay']['gradient']);
        $this->assertStringContainsString(
            'style="background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.7), rgba(255, 255, 255, 0));"',
            Blade::render('<x-site-builder::banner-background variant="inner" />'),
        );

        // A hand-edited image path outside the Branding folder (or climbing out of it) isn't shown.
        foreach (['site-builder/pages/hall.jpg', 'site-builder/branding/../../.env', 'elsewhere/banner.jpg'] as $path) {
            $this->branding->save([Branding::bannerKey('inner', 'image') => $path]);
            $this->assertNull($this->branding->banner('inner')['image'], $path);
        }
    }

    public function test_unreadable_banner_text_and_image_overlay_warnings(): void
    {
        $this->assertSame(['heading', 'subtitle'], $this->branding->unreadableBannerText([
            'background_type' => Branding::BANNER_TYPE_COLOR,
            'background_color' => '#ffffff',
        ]));

        $this->assertTrue($this->branding->bannerImageNeedsOverlay(['background_type' => 'image', 'image' => 'x.jpg', 'overlay_enabled' => false]));
        $this->assertFalse($this->branding->bannerImageNeedsOverlay(['background_type' => 'image', 'image' => 'x.jpg', 'overlay_enabled' => true, 'overlay_opacity' => 50]));

        // A gradient counts by its stronger end.
        $gradient = ['background_type' => 'image', 'image' => 'x.jpg', 'overlay_enabled' => true, 'overlay_type' => 'gradient', 'overlay_opacity' => 0];
        $this->assertFalse($this->branding->bannerImageNeedsOverlay([...$gradient, 'overlay_end_opacity' => 40]));
        $this->assertTrue($this->branding->bannerImageNeedsOverlay([...$gradient, 'overlay_end_opacity' => 35]));
    }

    public function test_panel_colours_are_per_panel_and_unknown_keys_are_not_saved(): void
    {
        $this->branding->save([
            Branding::panelColorKey('dashboard') => '#2e6a4c',
            'branding.not_a_setting' => 'x',
            'navigation.header' => 'x',
        ]);

        $this->assertNotNull($this->branding->panelPalette('dashboard'));
        $this->assertNull($this->branding->panelPalette('admin'));
        $this->assertNull($this->branding->get('branding.not_a_setting'));
        $this->assertNull($this->branding->get('navigation.header'));
    }
}
