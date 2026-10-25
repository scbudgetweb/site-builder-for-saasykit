<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Blocks\CardStyle;

class CardStyleTest extends TestCase
{
    public function test_unsaved_cards_have_a_background_and_a_border(): void
    {
        $card = CardStyle::resolve(null);

        $this->assertStringContainsString('rounded-sb-card p-6', $card['card']);
        $this->assertStringContainsString('bg-white', $card['card']);
        $this->assertSame('box-shadow: var(--shadow-sb-card);', $card['cardStyle']);
        $this->assertStringContainsString('border border-gray-200', $card['card']);
        $this->assertTrue($card['hasBox']);
        $this->assertSame('dark', $card['textScheme']);
        $this->assertStringContainsString('bg-primary-50', $card['iconWrap']);
        $this->assertStringContainsString('text-primary-600', $card['iconWrap']);
    }

    public function test_plain_cards_take_the_blocks_text_colour(): void
    {
        $card = CardStyle::resolve(['background' => false, 'border' => false, 'icon_position' => 'top']);

        $this->assertFalse($card['hasBox']);
        $this->assertNull($card['textScheme']);
        $this->assertStringNotContainsString('text-gray-900', $card['card']);
    }

    public function test_a_dark_card_colour_gets_light_text(): void
    {
        $card = CardStyle::resolve(['background' => true, 'background_color' => '#0b1f44', 'icon_position' => 'top']);

        $this->assertStringContainsString('text-white', $card['card']);
        $this->assertSame('light', $card['textScheme']);
        $this->assertStringContainsString('background-color: #0b1f44;', $card['cardStyle']);
    }

    public function test_card_borders_and_shadows(): void
    {
        $card = CardStyle::resolve(['border' => true, 'border_style' => 'dashed', 'border_width' => 3, 'shadow' => 'none', 'icon_position' => 'top']);
        $this->assertSame('border-style: dashed; border-width: 3px;', $card['cardStyle']);

        $plain = CardStyle::resolve(['background' => false, 'border' => false, 'icon_position' => 'top']);
        $this->assertSame('', $plain['cardStyle'], 'No background: no shadow unless one is chosen.');

        $custom = CardStyle::resolve(['shadow' => 'custom', 'shadow_x' => 2, 'shadow_y' => 4, 'shadow_blur' => 10, 'shadow_spread' => -1, 'shadow_color' => '#112233', 'shadow_opacity' => 50, 'icon_position' => 'top']);
        $this->assertStringContainsString('box-shadow: 2px 4px 10px -1px rgba(17, 34, 51, 0.5);', $custom['cardStyle']);

        $tampered = CardStyle::resolve(['border' => true, 'border_style' => 'groove;}', 'border_width' => 999, 'icon_position' => 'top']);
        $this->assertSame('border-width: 20px; box-shadow: var(--shadow-sb-card);', $tampered['cardStyle']);
    }

    public function test_icon_position_sizes_and_colours(): void
    {
        $card = CardStyle::resolve([
            'icon_position' => 'left', 'icon_background' => 'none', 'icon_size' => 'large', 'icon_color' => '#ff0000',
            'title_size' => 'xl', 'title_color' => '#333333', 'text_size' => 'small', 'text_color' => 'not-a-colour',
        ]);

        $this->assertTrue($card['isRow']);
        $this->assertStringContainsString('flex-row', $card['card']);
        $this->assertSame('h-8 w-8', $card['iconSize']);
        $this->assertSame('color: #ff0000;', $card['iconStyle']);
        $this->assertSame('color: #333333; font-size: 1.5rem;', $card['titleStyle']);
        $this->assertSame('font-size: 0.875rem;', $card['textStyle']);

        $centred = CardStyle::resolve(['icon_position' => 'top_center']);
        $this->assertTrue($centred['isCentered']);
        $this->assertStringContainsString('items-center text-center', $centred['card']);
    }

    public function test_hand_edited_values_fall_back_to_the_standard_look(): void
    {
        $card = CardStyle::resolve(['icon_position' => 'sideways', 'icon_size' => 'gigantic', 'border_color' => 'red;}']);

        $this->assertFalse($card['isRow']);
        $this->assertSame('h-6 w-6', $card['iconSize']);
        $this->assertStringNotContainsString('red', $card['cardStyle']);
    }
}
