<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;

class BorderShadowTest extends TestCase
{
    public function test_nothing_saved_draws_nothing(): void
    {
        $this->assertSame('', BorderShadow::style([]));
        $this->assertSame('', BorderShadow::style(['border_style' => 'none', 'shadow' => 'none']));
    }

    public function test_borders_on_all_or_some_sides(): void
    {
        $this->assertSame('border: 2px dotted #123456;', BorderShadow::style(['border_style' => 'dotted', 'border_width' => '2', 'border_color' => '#123456']));
        $this->assertSame(
            'border-left: 4px solid #123456; border-right: 4px solid #123456;',
            BorderShadow::style(['border_style' => 'solid', 'border_width' => 4, 'border_color' => '#123456', 'border_sides' => 'left_right']),
        );
        $this->assertSame('border: 1px double color-mix(in oklab, currentColor 20%, transparent);', BorderShadow::style(['border_style' => 'double']));
    }

    public function test_preset_and_custom_shadows(): void
    {
        $this->assertSame('box-shadow: '.BorderShadow::SHADOWS['medium'][1].';', BorderShadow::style(['shadow' => 'medium']));
        $this->assertSame('box-shadow: var(--shadow-sb-card);', BorderShadow::style(['shadow' => 'auto'], defaultShadow: 'site'));
        $this->assertSame('', BorderShadow::style(['shadow' => 'auto']));
        $this->assertSame(
            'box-shadow: 0px 12px 30px 2px rgba(255, 0, 0, 0.35);',
            BorderShadow::style(['shadow' => 'custom', 'shadow_x' => 0, 'shadow_y' => 12, 'shadow_blur' => 30, 'shadow_spread' => 2, 'shadow_color' => '#ff0000', 'shadow_opacity' => 35]),
        );
    }

    public function test_prefixed_settings_style_one_part_of_a_block(): void
    {
        $data = ['image_border_style' => 'solid', 'image_border_width' => 3, 'image_shadow' => 'soft', 'border_style' => 'dashed'];

        $this->assertStringStartsWith('border: 3px solid', BorderShadow::style($data, 'image_'));
        $this->assertStringContainsString('box-shadow:', BorderShadow::style($data, 'image_'));
    }

    public function test_hand_edited_values_are_clamped_or_ignored(): void
    {
        $style = BorderShadow::style([
            'border_style' => 'solid; background: url(x)', 'shadow' => 'custom',
            'shadow_x' => 5000, 'shadow_y' => -5000, 'shadow_blur' => -3, 'shadow_spread' => 'wide',
            'shadow_color' => 'red; }', 'shadow_opacity' => 400,
        ]);

        $this->assertSame('box-shadow: 100px -100px 0px 0px rgba(0, 0, 0, 1);', $style);
        $this->assertSame('border: 20px solid color-mix(in oklab, currentColor 20%, transparent);', BorderShadow::style(['border_style' => 'solid', 'border_width' => 99]));
    }
}
