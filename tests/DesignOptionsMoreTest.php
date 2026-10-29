<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Stage 3 of the visual builder: transform, transitions, position and overflow; width and heights; per-side borders, inner
 * shadows and more lines; more filters and blend modes; list and quote styles; dividers; a link on the whole block; a
 * sweep gradient; and the same design options on Section rows and columns.
 */
class DesignOptionsMoreTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_transform_transitions_position_and_overflow_become_classes_and_safe_properties(): void
    {
        $output = BlockDesign::resolve(['position' => [
            'scale_x' => 110, 'rotate' => '-5', 'skew_x' => 4,
            'translate_x' => ['desktop' => '20px', 'phone' => '0px'], 'origin' => 'top-left',
            'overflow_x' => 'hidden',
            'transition_duration' => 500, 'transition_curve' => 'ease-in',
            'position' => 'absolute', 'location' => 'top-right', 'offset_y' => '12px', 'z_index' => 5,
        ]]);

        $classes = $output->classes('section');
        $style = $output->style('section');

        foreach (['sb-d-scale', 'sb-d-rotate', 'sb-d-skew', 'sb-d-tx', 'sb-d-tx-p', 'sb-d-origin-top-left', 'sb-d-ovx-hidden', 'sb-d-transition', 'sb-d-pos-absolute', 'sb-d-at-top-right', 'sb-d-oy', 'sb-d-z'] as $class) {
            $this->assertStringContainsString($class, $classes);
        }

        foreach (['--sb-scale: 1.1 1;', '--sb-rotate: -5deg;', '--sb-skew: skew(4deg, 0deg);', '--sb-tx: 20px;', '--sb-transition-duration: 500ms;', '--sb-transition-curve: ease-in;', '--sb-oy: 12px;', '--sb-z: 5;'] as $property) {
            $this->assertStringContainsString($property, $style);
        }

        $refused = BlockDesign::resolve(['position' => ['position' => 'fixed', 'scale_x' => 900, 'z_index' => 99999, 'overflow_x' => 'clip; color: red', 'transition_curve' => 'cubic-bezier(1,1,1,1)']]);
        $this->assertTrue($refused->isEmpty(), 'Fixed, out-of-range and unknown values give nothing.');
    }

    public function test_sizing_borders_shadows_filters_and_lists(): void
    {
        $output = BlockDesign::resolve([
            'spacing' => ['width' => ['desktop' => '60%'], 'height' => ['desktop' => '400px'], 'max_height' => ['tablet' => '50vh']],
            'borders' => ['border_width' => ['desktop' => ['top' => '2px']], 'border_line' => 'dashed', 'border_color_top' => '#ff0000'],
            'effects' => ['hue' => 90, 'sepia' => 30, 'blend' => 'multiply'],
            'typography' => ['lists' => ['bullet' => 'square', 'numbering' => 'upper-roman', 'position' => 'inside', 'quote_border' => '6px', 'quote_colour' => '#336699']],
        ]);

        $this->assertStringContainsString('sb-d-w', $output->classes('container'));
        $this->assertStringContainsString('--sb-w: 60%;', $output->style('container'));
        $this->assertStringContainsString('--sb-h: 400px;', $output->style('section'));
        $this->assertStringContainsString('--sb-maxh-t: 50vh;', $output->style('section'));
        $this->assertStringContainsString('--sb-bs: dashed;', $output->style('box'));
        $this->assertStringContainsString('--sb-bct: #ff0000;', $output->style('box'));
        $this->assertStringContainsString('hue-rotate(90deg) sepia(30%)', $output->style('section'));
        $this->assertStringContainsString('sb-d-blend-multiply', $output->classes('section'));
        $this->assertStringContainsString('--sb-ul: square;', $output->style('section'));
        $this->assertStringContainsString('--sb-ol: upper-roman;', $output->style('section'));
        $this->assertStringContainsString('sb-d-list-inside', $output->classes('section'));
        $this->assertStringContainsString('--sb-quote-bw: 6px;', $output->style('section'));

        $this->assertSame('box-shadow: inset 0 2px 6px 0 rgb(0 0 0 / 0.12);', BorderShadow::shadowStyle(['shadow' => 'inner_soft']));
        $this->assertStringStartsWith('box-shadow: inset 0px 8px', BorderShadow::shadowStyle(['shadow' => 'custom', 'shadow_inset' => true]));
        $this->assertStringContainsString('3px groove', BorderShadow::style(['border_style' => 'groove', 'border_width' => 3]));

        $this->assertSame('conic-gradient(from 90deg at center, #000000, #ffffff)', Colour::gradientCss(['gradient_type' => 'conic', 'gradient_direction' => '90', 'gradient_start' => '#000000', 'gradient_end' => '#ffffff']));
    }

    public function test_dividers_and_a_link_on_the_whole_block(): void
    {
        $output = BlockDesign::resolve(['dividers' => ['top_shape' => 'wave', 'top_colour' => '#f3f4f6', 'top_height' => 80, 'bottom_shape' => 'slant', 'bottom_flip' => true, 'bottom_repeat' => 3]]);

        foreach (['sb-d-div', 'sb-d-divt-wave', 'sb-d-divb-slant', 'sb-d-divb-flip'] as $class) {
            $this->assertStringContainsString($class, $output->classes('section'));
        }

        $this->assertStringContainsString('--sb-divt-c: #f3f4f6; --sb-divt-h: 80px;', $output->style('section'));
        $this->assertStringContainsString('--sb-divb-r: 3;', $output->style('section'));
        $this->assertTrue(BlockDesign::resolve(['dividers' => ['top_shape' => 'url(evil)']])->isEmpty());

        $this->page(['blocks' => [self::block('call_to_action', ['heading' => 'Book the Village Hall', 'appearance' => ['link_url' => '/contact', 'link_new_tab' => true]])]]);

        $this->get('/about-us')
            ->assertSee('sb-has-link', false)
            ->assertSee('<a href="/contact" class="sb-block-link"', false)
            ->assertSee('aria-label="Book the Village Hall"', false);

        $this->assertNull(BlockAppearance::resolve(['link_url' => 'javascript:alert(1)'])['link']);
    }

    public function test_rows_and_columns_take_design_options_and_keep_them_when_the_section_is_saved(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());

        $page = $this->page(['blocks' => [self::block('section', ['rows' => [
            ['layout' => '2', 'design' => ['position' => ['rotate' => 3]], 'columns' => [
                ['blocks' => [self::block('text', ['content' => '<p>Left</p>'])], 'design' => ['effects' => ['sepia' => 50]]],
                ['blocks' => [self::block('text', ['content' => '<p>Right</p>'])]],
            ]],
        ]])]]);

        $this->get('/about-us')
            ->assertSee('sb-d-rotate', false)
            ->assertSee('--sb-rotate: 3deg;', false)
            ->assertSee('sepia(50%)', false);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertHasNoActionErrors();

        $row = array_values(app(VisualDrafts::class)->open($page, $this->user())->blocks[0]['data']['rows'])[0];
        $this->assertSame(3, (int) ($row['design']['position']['rotate'] ?? 0), 'Saving the Section from its panel keeps its rows\' design.');
        $this->assertSame(50, (int) (array_values($row['columns'])[0]['design']['effects']['sepia'] ?? 0), '…and its columns\'.');
    }
}
