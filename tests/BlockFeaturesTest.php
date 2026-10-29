<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Facades\SiteBuilder as SiteBuilderFacade;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blocks\OpeningHoursBlock;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blocks\TripleButtonBlock;

/**
 * A block's panel finds what it needs from the block's fields (PageBlock::uses()): Design › Buttons from a
 * buttonsRepeater(), the heading settings from headingFields(), the text size from those or richText(). Overrides are
 * only for blocks that draw their own.
 */
class BlockFeaturesTest extends PageTestCase
{
    /**
     * What every core block's panel offered when each declared it by hand: [buttons, heading settings, body text].
     * Finding them from the fields must give the same.
     */
    private const CORE = [
        'banner' => [true, [], false],
        'buttons' => [true, [], false],
        'call_to_action' => [true, ['size'], true],
        'image' => [true, [], false],
        'image_cards' => [true, ['align', 'decoration', 'size'], true],
        'image_text' => [true, ['decoration', 'size'], true],
        'text' => [true, [], true],
        'faq' => [false, ['align', 'decoration', 'size'], true],
        'features' => [false, ['align', 'decoration', 'size'], true],
        'gallery' => [false, ['align', 'decoration', 'size'], true],
        'logo_strip' => [false, ['align', 'decoration', 'size'], true],
        'stats' => [false, ['align', 'decoration', 'size'], true],
        'steps' => [false, ['align', 'decoration', 'size'], true],
        'tabs' => [false, ['align', 'decoration', 'size'], true],
        'team' => [false, ['align', 'decoration', 'size'], true],
        'testimonials' => [false, ['align', 'decoration', 'size'], true],
        'video' => [false, ['align', 'decoration', 'size'], true],
        'mailing_list' => [false, [], false],
        'section' => [false, [], false],
        'social_follow' => [false, ['decoration', 'size'], false],
        'site_account_links' => [false, [], false],
        'site_copyright' => [false, [], true],
        'site_footer_links' => [false, [], true],
        'site_logo' => [false, [], false],
        'site_menu' => [false, [], false],
    ];

    public function test_core_blocks_offer_what_they_did_when_they_declared_it(): void
    {
        $registry = app(BlockRegistry::class);

        foreach (self::CORE as $name => $expected) {
            $blockClass = $registry->find($name);
            $this->assertNotNull($blockClass, $name);
            $this->assertSame($expected, [$blockClass::hasButtons(), $blockClass::headingSettings(), $blockClass::hasBodyText()], $name);
        }
    }

    public function test_an_apps_block_gets_its_settings_from_the_helpers_it_uses(): void
    {
        $this->assertSame([true, [], true], [TripleButtonBlock::hasButtons(), TripleButtonBlock::headingSettings(), TripleButtonBlock::hasBodyText()]);
        $this->assertSame([false, ['align', 'decoration', 'size'], true], [OpeningHoursBlock::hasButtons(), OpeningHoursBlock::headingSettings(), OpeningHoursBlock::hasBodyText()]);

        $toggles = $this->toggleKeys(TripleButtonBlock::class);
        $this->assertContains('button', $toggles, 'Design › Buttons, from buttonsRepeater().');
        $this->assertContains('text', $toggles, 'Design › Text (text size), from richText().');
        $this->assertNotContains('button', $this->toggleKeys(OpeningHoursBlock::class));
    }

    /**
     * The safety net for every registered block, an app's too: a view that draws buttons, the shared heading or body
     * text means the panel must offer their settings. A block that draws its own declares them (hasButtons(), …).
     */
    public function test_every_blocks_panel_offers_what_its_view_draws(): void
    {
        SiteBuilderFacade::blocks([OpeningHoursBlock::class, TripleButtonBlock::class]);

        foreach (app(BlockRegistry::class)->all() as $name => $blockClass) {
            $view = $this->viewSource($blockClass::view());

            if (preg_match('/partials\.buttons|site-button/', $view) === 1) {
                $this->assertTrue($blockClass::hasButtons(), "{$name} draws buttons, so it needs hasButtons().");
            }

            if (! $blockClass::hasAppearance()) {
                continue;
            }

            if (str_contains($view, 'section-heading')) {
                $this->assertNotSame([], $blockClass::headingSettings(), "{$name} draws a heading, so it needs headingSettings().");
            }

            if (str_contains($view, 'block-text')) {
                $this->assertTrue($blockClass::hasBodyText(), "{$name} draws body text, so it needs hasBodyText().");
            }
        }
    }

    /**
     * @param  class-string<PageBlock>  $blockClass
     * @return array<int, string>
     */
    private function toggleKeys(string $blockClass): array
    {
        $method = new \ReflectionMethod($blockClass, 'toggles');

        return array_values(array_map(fn ($toggle): string => $toggle->key, array_filter(
            $method->invoke(null, false),
            fn ($toggle): bool => $toggle !== null && $toggle->fields !== [],
        )));
    }

    /**
     * The view's source with the partials it includes (one level), so a card partial's buttons count.
     */
    private function viewSource(string $view): string
    {
        $source = (string) file_get_contents(view()->getFinder()->find($view));

        preg_match_all("/@include\\('(site-builder::blocks\\.partials\\.[a-z-]+)'/", $source, $matches);

        foreach ($matches[1] as $partial) {
            $source .= (string) file_get_contents(view()->getFinder()->find($partial));
        }

        return $source;
    }
}
