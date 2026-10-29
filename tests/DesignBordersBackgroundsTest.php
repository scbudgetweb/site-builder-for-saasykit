<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Filament\Forms\BoxSides;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Style › Borders & corners, Hover and Pattern & shape (Design\BordersBackgrounds), and the background's extra settings:
 * radial gradients and the background image's size, position, repeat and "stay still" (BlockAppearance).
 */
class DesignBordersBackgroundsTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_borders_corners_hover_pattern_and_shape_go_on_the_box(): void
    {
        $output = BlockDesign::resolve(['borders' => [
            'border_width' => ['desktop' => ['top' => '4px', 'bottom' => '0px']],
            'border_color' => '#336699',
            'radius' => ['desktop' => ['top_left' => '24px', 'bottom_right' => '50%']],
            'hover_background' => '#0a3d2e',
            'hover_text' => '#ffffff',
            'pattern' => 'dots',
            'pattern_color' => '#ffffff',
            'pattern_strength' => 30,
            'pattern_size' => ['desktop' => '16px'],
            'shape' => 'blob',
            'shape_strength' => 100,
        ]]);

        $this->assertSame(
            'sb-d-bwt sb-d-bwb sb-d-bw sb-d-rtl sb-d-rbr sb-d-hover sb-d-hbg sb-d-htx sb-d-layers sb-d-pattern-dots sb-d-shape-blob',
            $output->classes('box'),
        );
        $this->assertSame(
            '--sb-bwt: 4px; --sb-bwb: 0px; --sb-bc: #336699; --sb-rtl: 24px; --sb-rbr: 50%; --sb-hbg: #0a3d2e; --sb-htx: #ffffff; --sb-pattern-opacity: 0.3; --sb-pattern-color: #ffffff; --sb-pattern-size: 16px; --sb-shape-opacity: 1;',
            $output->style('box'),
        );
        $this->assertSame('', $output->classes('section'));
        $this->assertStringContainsString('--sb-pattern-opacity: 0.15;', BlockDesign::resolve(['borders' => ['pattern' => 'grid', 'pattern_strength' => 0]])->style('box'), 'No strength yet (0) is the default.');
    }

    public function test_values_out_of_range_or_not_on_a_list_are_left_out(): void
    {
        $output = BlockDesign::resolve(['borders' => [
            'border_width' => ['desktop' => ['top' => '99px', 'middle' => '2px']],
            'border_color' => '#336699',
            'radius' => ['desktop' => ['top_left' => '24em']],
            'hover_text' => 'red; background: url(x)',
            'pattern' => 'leopard',
            'shape' => '<script>',
        ]]);

        $this->assertTrue($output->isEmpty(), 'And a border colour alone draws nothing.');
    }

    public function test_the_settings_reach_the_page_and_a_panel_carries_them(): void
    {
        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Bordered</p>', 'design' => ['borders' => ['border_width' => ['desktop' => ['left' => '6px']], 'pattern' => 'grid']]]),
            self::block('call_to_action', ['heading' => 'Panelled', 'appearance' => ['background' => 'dark', 'background_area' => 'panel'], 'design' => ['borders' => ['radius' => ['desktop' => ['top_right' => '3rem']], 'hover_border' => '#ff0000']]]),
        ]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]*class="[^"]*sb-d-bwl sb-d-bw sb-d-layers sb-d-pattern-grid[^"]*"\s*style="--sb-bwl: 6px; --sb-pattern-opacity: 0.15;"[^>]*>.*Bordered/s', $html);
        $this->assertMatchesRegularExpression('/class="sb-panel [^"]*sb-d-rtr sb-d-hover sb-d-hbc"[^>]*style="[^"]*--sb-rtr: 3rem; --sb-hbc: #ff0000;/', $html);
    }

    public function test_a_gradient_can_be_round_and_old_ones_stay_straight(): void
    {
        $stops = ['gradient_start' => '#000000', 'gradient_end' => '#1e3a8a'];

        $this->assertSame('linear-gradient(135deg, #000000, #1e3a8a)', Colour::gradientCss($stops));
        $this->assertSame('radial-gradient(circle at top right, #000000, #1e3a8a)', Colour::gradientCss([...$stops, 'gradient_type' => 'radial', 'gradient_shape' => 'circle', 'gradient_position' => 'top-right']));
        $this->assertSame('radial-gradient(ellipse at center, #000000, #1e3a8a)', Colour::gradientCss([...$stops, 'gradient_type' => 'radial', 'gradient_shape' => 'square', 'gradient_position' => 'nowhere']));

        $look = BlockAppearance::resolve(['background' => 'gradient', ...$stops, 'gradient_type' => 'radial']);
        $this->assertStringContainsString('background-image: radial-gradient(ellipse at center, #000000, #1e3a8a);', $look['style']);
    }

    public function test_background_image_settings_reach_the_image_or_draw_it_in_css(): void
    {
        Storage::fake('public');
        $image = ['background' => 'image', 'background_image' => 'site-builder/pages/hall.jpg'];

        $this->assertSame(['class' => '', 'style' => ''], BlockAppearance::imageLook($image, Storage::disk('public')->url('site-builder/pages/hall.jpg')), 'The standard settings add nothing.');

        $fitted = BlockAppearance::resolve([...$image, 'image_size' => 'contain', 'image_position' => 'top-left']);
        $this->assertStringContainsString('sb-bg-size-contain sb-bg-pos-top-left', $fitted['class']);
        $this->assertStringNotContainsString('--sb-bg-image', $fitted['style']);

        $tiled = BlockAppearance::resolve([...$image, 'image_repeat' => 'repeat-x', 'image_fixed' => true]);
        $this->assertStringContainsString('sb-bg-css sb-bg-repeat-x sb-bg-fixed', $tiled['class']);
        $this->assertMatchesRegularExpression('#--sb-bg-image: url\("[^"]*/site-builder/pages/hall\.jpg"\);#', $tiled['style']);

        $panel = BlockAppearance::resolve([...$image, 'background_area' => 'panel', 'image_fixed' => true]);
        $this->assertStringNotContainsString('sb-bg-fixed', $panel['class']);
        $this->assertStringContainsString('sb-bg-css sb-bg-fixed', $panel['panel']['class']);
        $this->assertStringContainsString('--sb-bg-image', $panel['panel']['style']);

        $odd = BlockAppearance::imageLook([...$image, 'image_fixed' => true], 'https://example.test/a "b" (c).jpg');
        $this->assertSame('--sb-bg-image: url("https://example.test/a%20%22b%22%20%28c%29.jpg");', $odd['style']);

        $this->assertSame(['class' => '', 'style' => ''], BlockAppearance::imageLook([...$image, 'image_size' => 'huge', 'image_repeat' => 'sideways', 'image_fixed' => 'yes'], 'https://example.test/a.jpg'));
    }

    public function test_the_settings_are_offered_in_the_block_panel_and_saved(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Hi</p>', 'appearance' => ['background' => 'gradient', 'gradient_start' => '#000000', 'gradient_end' => '#ffffff']])]]);
        $uid = $page->blocks[0]['uid'];

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]))
            ->assertFormFieldExists('design.borders.radius', 'mountedActionSchema0', fn (BoxSides $field): bool => ! $field->isResponsive() && $field->getParts() === BoxSides::CORNERS)
            ->assertFormFieldExists('design.borders.hover_background', 'mountedActionSchema0')
            ->assertFormFieldHidden('appearance.gradient_shape', 'mountedActionSchema0')
            ->setActionData(['appearance.gradient_type' => 'radial'])
            ->assertFormFieldVisible('appearance.gradient_shape', 'mountedActionSchema0')
            ->assertFormFieldHidden('appearance.gradient_direction', 'mountedActionSchema0');

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editBlock')->arguments(['uid' => $uid]), [
                'appearance.gradient_type' => 'radial',
                'design.borders.border_width' => ['desktop' => ['top' => '3px']],
                'design.borders.pattern' => 'checks',
            ])
            ->assertHasNoActionErrors()
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => str_contains($params['html'], 'radial-gradient(')
                && str_contains($params['html'], '--sb-bwt: 3px;')
                && str_contains($params['html'], 'sb-d-pattern-checks'));

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editBlock')->arguments(['uid' => $uid]), ['design.borders.border_width' => ['desktop' => ['top' => '50px']]])
            ->assertHasActionErrors(['design.borders.border_width']);
    }
}
