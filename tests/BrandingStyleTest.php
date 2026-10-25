<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Branding\BrandingStyle;

class BrandingStyleTest extends TestCase
{
    public function test_nothing_saved_outputs_nothing(): void
    {
        $this->assertSame('', $this->app->make(BrandingStyle::class)->css());
    }

    public function test_choices_become_variables_and_unknown_choices_are_ignored(): void
    {
        $this->app->make(Branding::class)->save([
            BrandingStyle::BUTTON_CORNERS => 'pill',
            BrandingStyle::CARD_CORNERS => 'enormous',
            BrandingStyle::SHADOWS => 'strong',
            BrandingStyle::HEADING_DECORATION => 'short_under',
        ]);

        $css = $this->app->make(BrandingStyle::class)->css();

        $this->assertStringContainsString('--radius-sb-button: 9999px', $css);
        $this->assertStringNotContainsString('--radius-sb-card', $css);
        $this->assertStringContainsString('--shadow-sb-card: 0 10px', $css);
        $this->assertStringContainsString('.section-heading::after', $css);
    }

    public function test_button_styles_output_validated_colours_and_toggles(): void
    {
        $this->app->make(Branding::class)->save([
            BrandingStyle::buttonKey(1, 'bg') => '#2e6a4c',
            BrandingStyle::buttonKey(1, 'border') => '#000000',
            BrandingStyle::buttonKey(1, 'uppercase') => true,
            BrandingStyle::buttonKey(2, 'text') => 'url(evil)',
            BrandingStyle::buttonKey(3, 'lift') => true,
        ]);

        $css = $this->app->make(BrandingStyle::class)->css();

        $this->assertStringContainsString('.site-button-1 { --site-button-bg: #2e6a4c', $css);
        $this->assertStringContainsString('--site-button-border-width: 2px', $css);
        $this->assertStringContainsString('--site-button-transform: uppercase', $css);
        $this->assertStringNotContainsString('.site-button-2', $css);
        $this->assertStringContainsString('.site-button-3 { --site-button-lift: -3px', $css);
    }

    public function test_automatic_buttons_pick_the_light_or_dark_style(): void
    {
        $style = $this->app->make(BrandingStyle::class);

        $this->assertSame('1', $style->buttonSlot('auto', false));
        $this->assertSame('2', $style->buttonSlot('auto', true));
        $this->assertSame('3', $style->buttonSlot('outline', false));
        $this->assertSame('4', $style->buttonSlot('4', false));

        $this->app->make(Branding::class)->save([BrandingStyle::BUTTON_ON_DARK => '4']);
        $this->assertSame('4', $this->app->make(BrandingStyle::class)->buttonSlot('auto', true));
    }

    public function test_button_names_and_unreadable_styles(): void
    {
        $this->app->make(Branding::class)->save([BrandingStyle::buttonKey(1, 'name') => 'Village green']);

        $style = $this->app->make(BrandingStyle::class);

        $this->assertSame('Village green', $style->buttonNames()[1]);
        $this->assertSame('White', $style->buttonNames()[2]);
        $this->assertSame(['White'], $style->unreadableButtonStyles(['button_2_text' => '#ffffff']));
    }
}
