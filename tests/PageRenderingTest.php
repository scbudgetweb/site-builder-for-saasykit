<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\PageRenderer;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\SiteBuilderServiceProvider;

class PageRenderingTest extends PageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_page_shows_the_standard_banner_with_its_title_then_its_blocks(): void
    {
        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>We run the village hall.</p>']),
            self::block('call_to_action', ['heading' => 'Book a room', 'buttons' => [['label' => 'Get in touch', 'url' => '/contact']]]),
        ]]);

        $this->get('/about-us')
            ->assertOk()
            ->assertSeeInOrder(['<h1 class="sb-h1 text-sb-banner-heading">About us</h1>', 'We run the village hall.', 'Book a room', 'Get in touch'], false);
    }

    public function test_a_page_opening_with_a_banner_puts_its_h1_there_instead(): void
    {
        $this->page(['blocks' => [
            self::block('banner', ['heading' => 'Welcome to the hall', 'subtitle' => 'Rooms for every occasion']),
            self::block('banner', ['heading' => 'A second banner']),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Welcome to the hall</h1>', $html);
        $this->assertStringContainsString('A second banner</h2>', $html);
        $this->assertStringNotContainsString('>About us</h1>', $html);
    }

    public function test_without_the_standard_banner_the_title_is_still_the_h1_for_screen_readers(): void
    {
        $this->page(['show_page_banner' => false, 'blocks' => [self::block('text', ['content' => '<p>Hello</p>'])]]);

        $this->get('/about-us')->assertSee('<h1 class="sr-only">About us</h1>', false);
    }

    public function test_rich_text_is_sanitised(): void
    {
        $this->page(['blocks' => [self::block('text', ['content' => '<p onclick="steal()">Safe <strong>bold</strong></p><script>alert(1)</script>'])]]);

        $this->get('/about-us')
            ->assertSee('<strong>bold</strong>', false)
            ->assertDontSee('<script>', false)
            ->assertDontSee('onclick', false);
    }

    public function test_page_content_cant_pin_itself_over_the_page(): void
    {
        // Rich text never carries inline styles of its own (the editor rebuilds them, and only text-align survives).
        $this->assertSame('<p style="text-align: center;">Hi</p>', PageBlock::html('<p style="text-align:center; position:fixed; inset:0">Hi</p>'));
        $this->assertSame('<p>Covered</p>', PageBlock::html(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left; position: fixed; inset: 0'], 'content' => [['type' => 'text', 'text' => 'Covered']]],
        ]]));

        // Custom classes can't pin a block either, even ones saved before "fixed" and "sticky" were refused.
        $this->assertSame('promo dark-cards', BlockAppearance::cssClasses('promo fixed dark-cards sticky'));
        $this->assertSame('fixed-width', BlockAppearance::cssClasses('fixed-width'));
    }

    public function test_hand_edited_values_cant_reach_the_page(): void
    {
        $this->page(['blocks' => [
            self::block('call_to_action', [
                'heading' => 'Book now',
                'buttons' => [
                    ['label' => 'Bad', 'url' => 'javascript:alert(1)'],
                    ['label' => 'Good', 'url' => 'https://example.com', 'style' => '1;color:red', 'width' => 'enormous'],
                ],
                'appearance' => ['background' => 'url(evil)', 'width' => 'huge', 'anchor' => '"><script>', 'css_class' => 'promo "onload=x [&>*]:hidden'],
            ]),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringNotContainsString('color:red', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('onload', $html);
        $this->assertStringNotContainsString('promo', $html, 'A class list with anything invalid in it is dropped whole.');
        // An unknown background falls back to the block's default, the dark site colour.
        $this->assertStringContainsString('bg-primary-900 py-12 sm:py-16 text-white', $html);
        $this->assertStringContainsString('site-button site-button-2', $html, 'Automatic buttons on a dark background use the on-dark style.');
    }

    public function test_appearance_sets_the_background_width_and_anchor(): void
    {
        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Hi</p>', 'appearance' => ['background' => 'light', 'width' => 'contained', 'anchor' => 'intro']]),
        ]]);

        $this->get('/about-us')
            ->assertSee('id="intro"', false)
            ->assertSee('bg-gray-50', false)
            ->assertSee('max-w-7xl', false);
    }

    public function test_a_panel_with_an_image_draws_the_image_and_overlay_inside_the_panel(): void
    {
        $this->page(['blocks' => [self::block('text', ['content' => '<p>Panel text</p>', 'appearance' => [
            'background' => 'image', 'background_image' => 'site-builder/pages/hall.jpg', 'overlay_opacity' => 50,
            'background_area' => 'panel', 'corners' => 'large', 'overlap' => 'small', 'heading_decoration' => 'short_under',
        ]])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]*class="relative py-12 sm:py-16 z-10 -mt-8 sm:-mt-12 text-white heading-decoration-short-under scroll-mt-24"/', $html);
        $this->assertStringContainsString('data-text-scheme="light"', $html);
        $this->assertMatchesRegularExpression('/rounded-3xl[^>]*>\s*<div class="absolute inset-0 overflow-hidden rounded-\[inherit\]" aria-hidden="true"><img src="[^"]*site-builder\/pages\/hall.jpg"/', $html);
        $this->assertStringContainsString('opacity: 0.5;', $html);
        $this->assertStringContainsString('Panel text', $html);
    }

    public function test_custom_button_colours_only_use_valid_hex_colours(): void
    {
        $this->page(['blocks' => [self::block('text', ['content' => '<p>Hi</p>', 'buttons' => [
            ['label' => 'Mine', 'url' => '/x', 'style' => 'custom', 'bg_color' => '#123456', 'text_color' => '#ffffff', 'border_color' => 'red;}'],
        ]])]]);

        $this->get('/about-us')
            ->assertSee('--site-button-bg: #123456;', false)
            ->assertDontSee('red;}', false);
    }

    public function test_images_and_buttons_take_their_borders_and_shadows(): void
    {
        $this->page(['blocks' => [
            self::block('image', ['image' => 'site-builder/pages/a.jpg', 'alt' => 'A', 'image_border_style' => 'solid', 'image_border_width' => 4, 'image_border_color' => '#00ff00', 'image_shadow' => 'strong']),
            self::block('image_text', ['image' => 'site-builder/pages/b.jpg', 'alt' => 'B']),
            self::block('text', ['content' => '<p>Hi</p>', 'buttons' => [
                ['label' => 'Dashed', 'url' => '/x', 'style' => 'custom', 'bg_color' => '#ffffff', 'text_color' => '#000000', 'border_color' => '#000000', 'border_style' => 'dashed', 'border_width' => 3],
                ['label' => 'Lifted', 'url' => '/y', 'shadow' => 'medium'],
            ]]),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('border: 4px solid #00ff00; box-shadow: '.BorderShadow::SHADOWS['strong'][1], $html);
        $this->assertStringContainsString('box-shadow: var(--shadow-sb-card);', $html, 'Image + text keeps its image shadow by default.');
        $this->assertStringContainsString('border: 3px dashed #000000;', $html);
        $this->assertStringContainsString('--site-button-shadow: '.BorderShadow::SHADOWS['medium'][1], $html);
    }

    public function test_block_images_must_be_page_uploads(): void
    {
        $this->page(['blocks' => [
            self::block('image_text', ['image' => 'site-builder/pages/hall.jpg', 'alt' => 'The main hall', 'heading' => 'Our hall', 'image_side' => 'right']),
            self::block('image_text', ['image' => 'site-builder/branding/logo.png', 'alt' => 'Not this', 'heading' => 'No image']),
        ]]);

        $this->get('/about-us')
            ->assertSee('site-builder/pages/hall.jpg', false)
            ->assertSee('alt="The main hall"', false)
            ->assertSee('md:order-2', false)
            ->assertDontSee('site-builder/branding/logo.png', false);
    }

    public function test_a_banner_can_use_its_own_image_with_an_overlay(): void
    {
        $this->page(['blocks' => [self::block('banner', [
            'heading' => 'Hello',
            'style' => 'image',
            'image' => 'site-builder/pages/banner.jpg',
            'overlay_color' => '#112233',
            'overlay_opacity' => 60,
            'height' => 'tall',
            'css_class' => 'my-banner',
        ])]]);

        $this->get('/about-us')
            ->assertSee('site-builder/pages/banner.jpg', false)
            ->assertSee('#112233', false)
            ->assertSee('py-24', false)
            ->assertSee('my-banner', false)
            // A one-colour overlay is drawn exactly as before gradients were added.
            ->assertSee('<div class="absolute inset-0" style="background-color: #112233; opacity: 0.6;"></div>', false);
    }

    public function test_a_banner_or_hero_image_can_have_a_gradient_overlay(): void
    {
        foreach (['banner', 'hero'] as $type) {
            $this->page(['slug' => "with-{$type}", 'blocks' => [self::block($type, [
                'heading' => 'Hello',
                'style' => 'image',
                'image' => 'site-builder/pages/banner.jpg',
                'overlay_type' => 'gradient',
                'overlay_direction' => 'to_top',
                'overlay_color' => '#0a3d2e',
                'overlay_opacity' => 80,
                'overlay_end_color' => '#000000',
                'overlay_end_opacity' => 10,
            ])]]);

            $this->get("/with-{$type}")
                ->assertSee('<div class="absolute inset-0" style="background-image: linear-gradient(0deg, rgba(10, 61, 46, 0.8), rgba(0, 0, 0, 0.1));"></div>', false);
        }

        // Hand-edited values fall back to safe ones.
        $this->assertSame(
            ['color' => '#000000', 'opacity' => 0.5, 'gradient' => 'linear-gradient(180deg, rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0))'],
            BlockAppearance::imageOverlay(['overlay_type' => 'gradient', 'overlay_direction' => '45deg); background: url(x', 'overlay_color' => 'red', 'overlay_end_opacity' => 'lots']),
        );
    }

    public function test_unknown_and_malformed_blocks_are_skipped(): void
    {
        $this->page(['blocks' => [
            self::block('no_such_block', ['heading' => 'Ghost']),
            ['type' => 'text'],
            'nonsense',
            self::block('text', ['content' => '<p>Still here</p>']),
        ]]);

        $this->get('/about-us')->assertOk()->assertSee('Still here')->assertDontSee('Ghost');
    }

    public function test_a_failing_block_is_reported_and_skipped_outside_debug_mode(): void
    {
        config(['app.debug' => false]);
        app(SiteBuilder::class)->blocks([FailingBlock::class]);

        $page = new Page(['title' => 'T', 'slug' => 't', 'blocks' => [self::block('failing'), self::block('text', ['content' => '<p>Fine</p>'])]]);

        $html = (string) app(PageRenderer::class)->render($page);

        $this->assertStringContainsString('Fine', $html);
    }

    public function test_a_page_can_replace_the_home_page(): void
    {
        // The theme's home view, in the stand-in layout.
        $home = SiteBuilderServiceProvider::themePath().'/home.blade.php';
        $this->assertStringContainsString('SaaSykit plans', view()->file($home)->render());

        $this->page(['title' => 'Home', 'slug' => 'home', 'replaces' => 'home', 'blocks' => [self::block('banner', ['heading' => 'Book the village hall'])]]);

        $html = view()->file($home)->render();

        $this->assertStringContainsString('Book the village hall</h1>', $html);
        $this->assertStringNotContainsString('SaaSykit plans', $html);
    }
}

class FailingBlock extends TextBlock
{
    public static function name(): string
    {
        return 'failing';
    }

    public static function viewData(array $data, Page $page): array
    {
        throw new RuntimeException('Broken block');
    }
}
