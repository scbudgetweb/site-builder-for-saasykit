<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\BlockPresets;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;
use ScBudgetWeb\SiteBuilder\Blocks\StyleClipboard;

/**
 * Design › Small heading (Design\Eyebrow): the eyebrow's colour and text style, a pill's colour, and a line or dot
 * beside it.
 */
class EyebrowDesignTest extends PageTestCase
{
    public function test_the_small_heading_becomes_classes_and_properties(): void
    {
        $output = BlockDesign::resolve(['eyebrow' => [
            'decoration' => 'both', 'decoration_colour' => '#c9a227', 'line_length' => ['desktop' => '40px'],
            'pill_colour' => '#0b1f44', 'colour' => '#c9a227', 'style' => ['upper'], 'letter_spacing' => ['desktop' => '3px'], 'align' => 'center',
        ]]);

        $classes = explode(' ', $output->classes('section'));
        $style = $output->style('section');

        foreach (['sb-d-eb-both', 'sb-d-ebbg', 'sb-d-ebc', 'sb-d-eb-upper', 'sb-d-ebls', 'sb-d-eb-align-center'] as $class) {
            $this->assertContains($class, $classes);
        }

        foreach (['--sb-ebdc: #c9a227;', '--sb-ebdw: 40px;', '--sb-ebbg: #0b1f44;', '--sb-ebc: #c9a227;', '--sb-ebls: 3px;'] as $property) {
            $this->assertStringContainsString($property, $style);
        }
    }

    public function test_hand_edited_values_never_reach_the_page(): void
    {
        $output = BlockDesign::resolve(['eyebrow' => ['decoration' => 'zigzag', 'pill_colour' => 'red;}', 'line_length' => ['desktop' => '9000px']]]);

        $this->assertSame('', $output->classes('section'));
        $this->assertSame('', $output->style('section'));

        $dot = BlockDesign::resolve(['eyebrow' => ['decoration' => 'dot', 'line_length' => ['desktop' => '9000px']]]);
        $this->assertSame('sb-d-eb-dot', $dot->classes('section'));
        $this->assertSame('', $dot->style('section'), 'A length out of range is left out.');
    }

    public function test_blocks_with_a_small_heading_offer_it(): void
    {
        $registry = app(BlockRegistry::class);

        foreach (['text', 'features', 'call_to_action', 'image_text', 'stats'] as $name) {
            $this->assertTrue($registry->find($name)::hasEyebrow(), $name);
        }

        foreach (['site_menu', 'image', 'buttons'] as $name) {
            $this->assertFalse($registry->find($name)::hasEyebrow(), $name);
        }

        $toggles = (new \ReflectionMethod(TextBlock::class, 'toggles'))->invoke(null, false);
        $this->assertContains('eyebrow', array_map(fn ($toggle) => $toggle?->key, $toggles));
    }

    public function test_it_renders_with_its_classes_and_can_be_a_saved_style(): void
    {
        $style = $this->app->make(BlockPresets::class)->createStyle('eyebrow', 'Gold line', ['design' => ['eyebrow' => ['decoration' => 'before', 'colour' => '#c9a227']]]);

        $this->page(['blocks' => [self::block('features', [
            'eyebrow' => 'Why choose us',
            'heading' => 'Built for village halls',
            'items' => [['title' => 'Free parking']],
            'style_presets' => ['eyebrow' => $style],
        ])]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]+class="[^"]*sb-d-ebc[^"]*sb-d-eb-before/', $html);
        $this->assertStringContainsString('class="sb-eyebrow">Why choose us</p>', $html);

        $this->assertSame(['eyebrow_style', 'design.eyebrow', 'style_presets.eyebrow'], StyleClipboard::paths('eyebrow'));

        $stylesheet = (string) file_get_contents(__DIR__.'/../resources/css/site-builder.css');
        $this->assertStringContainsString("@import './design/eyebrow.css';", $stylesheet);
    }
}
