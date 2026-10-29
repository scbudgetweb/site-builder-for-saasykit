<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;

/**
 * Advanced › Effects (Design\Effects): hiding on some screens, entrance animations and image filters.
 */
class DesignEffectsTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_effects_become_classes_and_one_filter_on_the_section(): void
    {
        $output = BlockDesign::resolve(['effects' => [
            'hide_on' => ['phone', 'desktop'],
            'animation' => 'slide-up',
            'animation_speed' => 'fast',
            'greyscale' => 100,
            'brightness' => 110,
            'contrast' => 100,
            'blur' => 2,
            'filter_hover' => true,
        ]]);

        $this->assertSame('sb-d-hide-phone sb-d-hide-desktop sb-d-animate sb-d-animate-slide-up sb-d-filter sb-d-filter-hover', $output->classes('section'));
        $this->assertSame('--sb-anim-duration: 450ms; --sb-filter: grayscale(100%) brightness(110%) blur(2px);', $output->style('section'), 'Fast is a short duration; filters that change nothing are left out.');
        $this->assertSame('', $output->classes('box').$output->classes('container'));

        $this->assertSame('sb-d-animate sb-d-animate-zoom', BlockDesign::resolve(['effects' => ['animation' => 'zoom', 'animation_speed' => 'normal']])->classes('section'));
    }

    public function test_unknown_or_out_of_range_effects_are_left_out(): void
    {
        $output = BlockDesign::resolve(['effects' => [
            'hide_on' => ['watch', ['phone']],
            'animation' => 'spin',
            'greyscale' => 500,
            'blur' => '2px); background: red',
            'opacity' => 100,
            'brightness' => 100,
            'filter_hover' => true,
        ]]);

        $this->assertTrue($output->isEmpty());
    }

    public function test_effects_reach_the_page_and_the_canvas_keeps_hidden_blocks_editable(): void
    {
        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Desktop only</p>', 'design' => ['effects' => ['hide_on' => ['phone', 'tablet'], 'animation' => 'fade', 'greyscale' => 100]]]),
        ]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]*class="[^"]*sb-d-hide-phone sb-d-hide-tablet sb-d-animate sb-d-animate-fade sb-d-filter[^"]*"\s*style="--sb-anim-duration: 800ms; --sb-filter: grayscale\(100%\);"/', $html);
        $this->assertStringContainsString("document.documentElement.classList.add('sb-motion')", $html, 'The page starts its animations as they come into view.');

        $css = (string) file_get_contents(__DIR__.'/../resources/css/design/effects.css');
        $this->assertStringContainsString('@media (prefers-reduced-motion: no-preference)', $css);
        $this->assertStringContainsString('.sb-motion .sb-d-animate.sb-in-view:not(.sb-d-animate-items):not([data-sb-node] > *)', $css);
        $this->assertStringContainsString('.sb-d-hide-phone:not([data-sb-node] > *) { display: none !important; }', $css, 'Hidden on a public page only.');
        $this->assertStringContainsString('[data-sb-node] > .sb-d-hide-phone { opacity: 0.45;', $css, 'Faded, not hidden, on the canvas.');
        $this->assertStringContainsString('.sb-d-filter img:not([aria-hidden="true"] > img)', $css, 'The background image keeps its colours.');
    }

    public function test_the_effects_fields_are_in_the_block_panel_and_save(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Hi</p>'])]]);
        $uid = $page->blocks[0]['uid'];

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]))
            ->assertFormFieldExists('design.effects.hide_on', 'mountedActionSchema0', fn (CheckboxList $field): bool => filled($field->getHintIconTooltip()))
            ->assertFormFieldExists('design.effects.animation', 'mountedActionSchema0')
            ->assertFormFieldExists('design.effects.greyscale', 'mountedActionSchema0')
            ->assertFormFieldExists('design.effects.filter_hover', 'mountedActionSchema0');

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editBlock')->arguments(['uid' => $uid]), [
                'design.effects.hide_on' => ['tablet'],
                'design.effects.animation' => 'slide-left',
                'design.effects.greyscale' => 100,
                'design.effects.filter_hover' => true,
            ])
            ->assertHasNoActionErrors()
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => str_contains($params['html'], 'sb-d-hide-tablet')
                && str_contains($params['html'], 'sb-d-animate-slide-left')
                && str_contains($params['html'], 'sb-d-filter-hover')
                && str_contains($params['html'], 'style="--sb-anim-duration: 800ms; --sb-filter: grayscale(100%);"'));
    }
}
