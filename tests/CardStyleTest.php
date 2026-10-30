<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Blocks\CardStyle;

/**
 * The Cards settings (a box or not, where the icon sits). Their look is Design › Items (ItemsDesignTest).
 */
class CardStyleTest extends TestCase
{
    public function test_unsaved_cards_have_a_background_a_border_and_the_site_shadow(): void
    {
        $card = CardStyle::resolve(null);

        $this->assertStringContainsString('rounded-sb-card p-6', $card['card']);
        $this->assertStringContainsString('bg-white', $card['card']);
        $this->assertStringContainsString('shadow-sb-card', $card['card']);
        $this->assertStringContainsString('border border-gray-200', $card['card']);
        $this->assertTrue($card['hasBox']);
        $this->assertSame('dark', $card['textScheme']);
        $this->assertStringContainsString('sb-item-icon sb-has-tile', $card['iconWrap']);
        $this->assertStringContainsString('bg-primary-50', $card['iconWrap']);
        $this->assertSame('h-6 w-6', $card['iconSize']);
    }

    public function test_plain_cards_take_the_blocks_text_colour(): void
    {
        $card = CardStyle::resolve(['background' => false, 'border' => false, 'icon_position' => 'top']);

        $this->assertFalse($card['hasBox']);
        $this->assertNull($card['textScheme']);
        $this->assertStringNotContainsString('text-gray-900', $card['card']);
        $this->assertStringNotContainsString('shadow-sb-card', $card['card'], 'No background: no shadow.');
    }

    public function test_a_dark_item_colour_gets_light_text_and_an_own_shadow_replaces_the_sites(): void
    {
        $card = CardStyle::resolve(['background' => true, 'icon_position' => 'top'], ['box' => ['background' => '#0b1f44', 'shadow' => 'strong']]);

        $this->assertStringContainsString('text-white', $card['card']);
        $this->assertSame('light', $card['textScheme']);
        $this->assertStringNotContainsString('shadow-sb-card', $card['card']);

        $light = CardStyle::resolve(['background' => false, 'border' => false, 'icon_position' => 'top'], ['box' => ['background' => '#fef3c7']]);
        $this->assertSame('dark', $light['textScheme'], 'A colour of its own gives the card its own text, even with the box off.');
    }

    public function test_icon_position(): void
    {
        $row = CardStyle::resolve(['icon_position' => 'left']);
        $this->assertTrue($row['isRow']);
        $this->assertStringContainsString('flex-row', $row['card']);

        $centred = CardStyle::resolve(['icon_position' => 'top_center']);
        $this->assertTrue($centred['isCentered']);
        $this->assertStringContainsString('items-center text-center', $centred['card']);

        $tampered = CardStyle::resolve(['icon_position' => 'sideways']);
        $this->assertFalse($tampered['isRow']);
        $this->assertFalse($tampered['isCentered']);
    }

    public function test_the_look_saved_before_1_7_moves_to_design_items(): void
    {
        $data = CardStyle::upgrade(['columns' => 3, 'card' => [
            'background' => true, 'border' => true, 'icon_position' => 'left',
            'background_color' => '#0b1f44', 'border_color' => '#112233', 'border_style' => 'dashed', 'border_width' => 3,
            'shadow' => 'custom', 'shadow_x' => 2, 'shadow_y' => 4, 'shadow_color' => '#112233',
            'icon_background' => 'none', 'icon_size' => 'large', 'icon_color' => '#ff0000', 'icon_background_color' => null,
            'title_size' => 'xl', 'title_color' => '#333333', 'text_size' => 'standard', 'text_color' => '',
        ]]);

        $this->assertSame(['background' => true, 'border' => true, 'icon_position' => 'left'], $data['card']);
        $this->assertSame([
            'box' => ['background' => '#0b1f44', 'border_colour' => '#112233', 'border_style' => 'dashed', 'border_width' => 3, 'shadow' => 'custom', 'shadow_x' => 2, 'shadow_y' => 4, 'shadow_color' => '#112233'],
            'icon' => ['colour' => '#ff0000', 'fill' => 'none', 'size' => ['desktop' => '32px']],
            'title' => ['colour' => '#333333', 'size' => ['desktop' => '1.5rem']],
        ], $data['design']['items']);
        $this->assertSame(3, $data['columns']);

        $this->assertSame($data, CardStyle::upgrade($data), 'Upgrading twice changes nothing.');
    }

    public function test_only_what_showed_before_moves_and_items_already_set_win(): void
    {
        $data = CardStyle::upgrade([
            'card' => ['background' => false, 'border' => false, 'background_color' => '#0b1f44', 'border_color' => '#112233', 'title_color' => '#333333'],
            'design' => ['items' => ['title' => ['colour' => '#00ff00']]],
        ]);

        $this->assertSame(['title' => ['colour' => '#00ff00']], $data['design']['items'], 'Colours of a box that was off stay off; Design › Items keeps its own.');
        $this->assertSame(['background' => false, 'border' => false], $data['card']);

        $this->assertSame(['heading' => 'Why us'], CardStyle::upgrade(['heading' => 'Why us']), 'No cards settings: nothing to do.');
    }
}
