<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;

class BlockAppearanceTest extends TestCase
{
    public function test_nothing_saved_gives_the_blocks_defaults(): void
    {
        $look = BlockAppearance::resolve(null, 'narrow', 'none');

        $this->assertSame('relative py-12 sm:py-16 text-gray-900', $look['class']);
        $this->assertSame('max-w-3xl mx-auto px-4 sm:px-6', $look['container']);
        $this->assertFalse($look['isDark']);
        $this->assertSame('dark', $look['textScheme']);
        $this->assertNull($look['panel']);
        $this->assertNull($look['id']);
        $this->assertSame('', $look['headingDecorationClass']);
    }

    public function test_preset_backgrounds_set_the_text_colour(): void
    {
        $dark = BlockAppearance::resolve(['background' => 'brand_dark']);
        $this->assertStringContainsString('bg-primary-900', $dark['class']);
        $this->assertStringContainsString('text-white', $dark['class']);
        $this->assertTrue($dark['isDark']);
        $this->assertSame('light', $dark['textScheme']);

        $light = BlockAppearance::resolve(['background' => 'brand_light']);
        $this->assertStringContainsString('bg-primary-50', $light['class']);
        $this->assertFalse($light['isDark']);

        $forced = BlockAppearance::resolve(['background' => 'dark', 'text' => 'dark']);
        $this->assertFalse($forced['isDark'], 'Text colour › Dark wins over a dark background.');
    }

    public function test_a_custom_colour_picks_readable_text_and_ignores_invalid_colours(): void
    {
        $navy = BlockAppearance::resolve(['background' => 'custom', 'background_color' => '#0b1f44']);
        $this->assertSame('background-color: #0b1f44;', $navy['style']);
        $this->assertTrue($navy['isDark']);

        $this->assertFalse(BlockAppearance::resolve(['background' => 'custom', 'background_color' => '#fef3c7'])['isDark']);

        $bad = BlockAppearance::resolve(['background' => 'custom', 'background_color' => 'red; position: fixed']);
        $this->assertSame('', $bad['style']);
    }

    public function test_a_gradient_needs_a_start_and_end_colour(): void
    {
        $gradient = BlockAppearance::resolve(['background' => 'gradient', 'gradient_start' => '#000000', 'gradient_end' => '#1e3a8a', 'gradient_direction' => '90']);
        $this->assertSame('background-image: linear-gradient(90deg, #000000, #1e3a8a);', $gradient['style']);
        $this->assertTrue($gradient['isDark']);

        $this->assertSame('', BlockAppearance::resolve(['background' => 'gradient', 'gradient_start' => '#000000'])['style']);
    }

    public function test_an_image_background_uses_page_uploads_with_a_solid_or_gradient_overlay(): void
    {
        $solid = BlockAppearance::resolve(['background' => 'image', 'background_image' => 'site-builder/pages/hall.jpg', 'overlay_color' => '#112233', 'overlay_opacity' => 40]);
        $this->assertStringEndsWith('site-builder/pages/hall.jpg', (string) $solid['image']);
        $this->assertSame('background-color: #112233; opacity: 0.4;', $solid['overlayStyle']);
        $this->assertTrue($solid['isDark']);

        $gradient = BlockAppearance::resolve([
            'background' => 'image', 'background_image' => 'site-builder/pages/hall.jpg',
            'overlay_type' => 'gradient', 'overlay_direction' => 'to_right',
            'overlay_color' => '#000000', 'overlay_opacity' => 80, 'overlay_end_color' => '#ffffff', 'overlay_end_opacity' => 0,
        ]);
        $this->assertSame('background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.8), rgba(255, 255, 255, 0));', $gradient['overlayStyle']);

        $this->assertNull(BlockAppearance::resolve(['background' => 'image', 'background_image' => 'site-builder/branding/logo.png'])['image']);
        $this->assertNull(BlockAppearance::resolve(['background' => 'image', 'background_image' => 'site-builder/pages/hall.jpg', 'overlay_opacity' => 0])['overlayStyle']);
    }

    public function test_a_panel_takes_the_background_corners_shadow_and_overlap(): void
    {
        $look = BlockAppearance::resolve([
            'background' => 'custom', 'background_color' => '#0b1f44',
            'background_area' => 'panel', 'corners' => 'large', 'shadow' => 'strong', 'overlap' => 'medium',
            'border_style' => 'dashed', 'border_width' => 3, 'border_color' => '#ff0000',
        ]);

        $this->assertStringNotContainsString('background-color', $look['style'], 'The section has no background of its own.');
        $this->assertStringContainsString('-mt-16', $look['class']);
        $this->assertStringContainsString('rounded-3xl', $look['panel']['class']);
        $this->assertSame('background-color: #0b1f44; border: 3px dashed #ff0000; box-shadow: '.BorderShadow::SHADOWS['strong'][1].';', $look['panel']['style']);
        $this->assertStringNotContainsString('border:', $look['style'], 'The border goes on the panel, not the section.');

        $site = BlockAppearance::resolve(['background' => 'light', 'background_area' => 'panel']);
        $this->assertStringContainsString('rounded-sb-card', $site['panel']['class']);
        $this->assertSame('box-shadow: var(--shadow-sb-card);', $site['panel']['style'], 'A panel has the site shadow unless another is chosen.');
        $this->assertSame('', BlockAppearance::resolve(['background' => 'light', 'background_area' => 'panel', 'shadow' => 'none'])['panel']['style']);
        $this->assertSame('box-shadow: var(--shadow-sb-card);', BlockAppearance::resolve(['background' => 'light', 'background_area' => 'panel', 'shadow' => 'auto'])['panel']['style']);

        $noPanel = BlockAppearance::resolve(['background' => 'none', 'background_area' => 'panel', 'overlap' => 'large']);
        $this->assertNull($noPanel['panel']);
        $this->assertStringContainsString('-mt-24', $noPanel['class'], 'A block without a background overlaps too, e.g. a screenshot over a banner.');
        $this->assertStringContainsString('sm:-mt-72', BlockAppearance::resolve(['overlap' => 'huge'])['class']);
    }

    public function test_a_full_width_block_takes_the_border_and_shadow_itself(): void
    {
        $look = BlockAppearance::resolve(['border_style' => 'solid', 'border_sides' => 'top_bottom', 'shadow' => 'auto']);

        $this->assertStringContainsString('border-top: 1px solid color-mix(in oklab, currentColor 20%, transparent); border-bottom: 1px solid', $look['style']);
        $this->assertStringNotContainsString('box-shadow', $look['style'], 'Automatic means no shadow on a full-width block.');
        $this->assertContains('Border', BlockAppearance::summary(['border_style' => 'solid', 'shadow' => 'strong']));
        $this->assertContains('Shadow', BlockAppearance::summary(['border_style' => 'solid', 'shadow' => 'strong']));
    }

    public function test_spacing_width_sizes_anchor_and_classes(): void
    {
        $look = BlockAppearance::resolve([
            'spacing' => 'large', 'width' => 'wide', 'heading_size' => 'xl', 'text_size' => 'large',
            'heading_align' => 'left', 'heading_decoration' => 'none', 'anchor' => 'prices', 'css_class' => 'promo  dark-cards',
        ]);

        $this->assertStringContainsString('py-16 sm:py-24', $look['class']);
        $this->assertStringContainsString('max-w-screen-2xl', $look['container']);
        $this->assertStringContainsString('has-heading-size has-text-size', $look['class']);
        $this->assertStringContainsString('--heading-size: clamp(', $look['style']);
        $this->assertStringContainsString('--text-size: 1.125rem;', $look['style']);
        $this->assertSame('left', $look['headingAlign']);
        $this->assertSame('no-heading-decoration', $look['headingDecorationClass']);
        $this->assertSame('prices', $look['id']);
        $this->assertStringContainsString('promo dark-cards', $look['class']);
    }

    public function test_hand_edited_values_fall_back_to_safe_ones(): void
    {
        $look = BlockAppearance::resolve([
            'background' => 'url(x)', 'spacing' => 'enormous', 'width' => '100vw', 'heading_size' => '99px',
            'anchor' => '"><script>', 'css_class' => 'ok "onload=x', 'corners' => 'evil', 'overlap' => 'x',
        ], 'contained', 'none', 'fallback-anchor');

        $this->assertSame('relative py-12 sm:py-16 text-gray-900', $look['class']);
        $this->assertSame('', $look['style']);
        $this->assertSame('fallback-anchor', $look['id']);
    }

    public function test_a_block_in_a_section_takes_the_sections_text_colour_and_decoration(): void
    {
        $inner = BlockAppearance::resolve(null, 'contained', 'none', null, parentIsDark: true, parentHeadingDecoration: 'short_above');

        $this->assertTrue($inner['isDark']);
        $this->assertNull($inner['textScheme']);
        $this->assertStringNotContainsString('text-', $inner['class']);
        $this->assertSame('', $inner['container'], 'In a section, a block fills its column.');
        $this->assertStringNotContainsString('py-', $inner['class'], 'In a section, a block has no extra spacing.');
        $this->assertSame('heading-decoration-short-above', $inner['headingDecorationClass']);

        $own = BlockAppearance::resolve(['heading_decoration' => 'none', 'background' => 'light'], 'contained', 'none', null, true, 'short_above');
        $this->assertSame('no-heading-decoration', $own['headingDecorationClass'], 'The block\'s own choice wins over its section\'s.');
        $this->assertFalse($own['isDark'], 'A block with its own background has its own text colour.');
    }

    public function test_the_editor_card_tags_name_only_recognised_styles(): void
    {
        $this->assertSame(
            ['Gradient', 'Panel', 'Overlaps', 'Decoration: None', 'Heading: Large', '#prices', '.promo'],
            BlockAppearance::summary(['background' => 'gradient', 'background_area' => 'panel', 'overlap' => 'small', 'heading_decoration' => 'none', 'heading_size' => 'large', 'anchor' => 'prices', 'css_class' => 'promo']),
        );
        $this->assertSame([], BlockAppearance::summary(['background' => '<b>bold</b>', 'css_class' => '<script>']));
    }

    public function test_numeric_select_values_count_whether_strings_or_integers(): void
    {
        // Selects hand numeric keys back as integers (lesson 2).
        $this->assertSame(4, PageBlock::columns(['columns' => 4]));
        $this->assertSame(4, PageBlock::columns(['columns' => '4']));
        $this->assertSame(3, PageBlock::columns(['columns' => 9]));
        $this->assertSame('background-image: linear-gradient(90deg, #000000, #ffffff);', BlockAppearance::resolve(['background' => 'gradient', 'gradient_start' => '#000000', 'gradient_end' => '#ffffff', 'gradient_direction' => 90])['style']);
    }
}
