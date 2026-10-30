<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;

/**
 * The Text block's text styles: Lead, Statement (with an accent line) and Note.
 */
class TextStylesTest extends PageTestCase
{
    public function test_each_style_is_a_class_on_the_text(): void
    {
        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Plain</p>']),
            self::block('text', ['content' => '<p>Lead</p>', 'text_style' => 'lead']),
            self::block('text', ['content' => '<p>Statement</p>', 'text_style' => 'statement', 'accent_colour' => '#c9a227']),
            self::block('text', ['content' => '<p>Note</p>', 'text_style' => 'note', 'accent_colour' => '#c9a227']),
            self::block('text', ['content' => '<p>Tampered</p>', 'text_style' => 'shouting;}']),
        ]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/class="block-text sb-prose"\s*>\s*<p>Plain/', $html);
        $this->assertMatchesRegularExpression('/class="block-text sb-prose sb-text-lead"\s*>\s*<p>Lead/', $html);
        $this->assertMatchesRegularExpression('/class="block-text sb-prose sb-text-statement"\s*style="--sb-text-accent: #c9a227"\s*>\s*<p>Statement/', $html);
        $this->assertMatchesRegularExpression('/class="block-text sb-prose sb-text-note"\s*>\s*<p>Note/', $html, 'The accent colour is only for a statement.');
        $this->assertMatchesRegularExpression('/class="block-text sb-prose"\s*>\s*<p>Tampered/', $html);
    }

    public function test_the_styles_are_in_the_stylesheet_and_copy_with_all_styles(): void
    {
        $css = (string) file_get_contents(__DIR__.'/../resources/css/site-builder.css');

        foreach (array_keys(TextBlock::TEXT_STYLES) as $style) {
            if ($style !== 'standard') {
                $this->assertStringContainsString(".sb-prose.sb-text-{$style} {", $css);
            }
        }

        $this->assertContains('text_style', TextBlock::ownStyleKeys());
        $this->assertContains('accent_colour', TextBlock::ownStyleKeys());
    }
}
