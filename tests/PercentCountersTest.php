<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\Core\PercentCountersBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;

/**
 * Circle counters and Bar counters: percentages as rings or bars, filling up as they scroll into view, each drawn at
 * its full value in the HTML so it reads right without JavaScript.
 */
class PercentCountersTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_circles_show_each_percentage_in_a_ring(): void
    {
        $this->page(['blocks' => [self::block('circle_counters', [
            'heading' => 'How busy we are',
            'items' => [
                ['label' => 'Weekends booked', 'value' => 85],
                ['label' => 'Over the top', 'value' => 140, 'colour' => '#be185d'],
                ['label' => '', 'value' => 10],
                ['label' => 'Not a number', 'value' => 'lots'],
            ],
            'size' => 'large',
            'thickness' => 'thick',
            'meter_colour' => '#0b1f44',
        ])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, '<div class="sb-item '), 'Only items with a label and a number.');
        $this->assertStringContainsString('<dt class="sb-item-label mt-3 text-base font-semibold">Weekends booked</dt>', $html);
        $this->assertStringContainsString('stroke-dashoffset="15" :stroke-dashoffset="100 - shown"', $html, '85% filled without JavaScript.');
        $this->assertStringContainsString('stroke-dashoffset="0"', $html, 'At most 100%.');
        $this->assertStringContainsString('<span x-text="shown">85</span>%', $html);
        $this->assertStringContainsString('style="stroke: #0b1f44"', $html);
        $this->assertStringContainsString('style="stroke: #be185d"', $html, 'Its own colour wins.');
        $this->assertStringContainsString('r="42" fill="none" stroke-width="16"', $html, 'A thick ring stays inside the circle.');
        $this->assertStringContainsString('width: 11rem; height: 11rem', $html);
        $this->assertStringContainsString('x-intersect.once="fill()"', $html);
    }

    public function test_bars_show_each_percentage_as_a_bar(): void
    {
        $this->page(['blocks' => [self::block('bar_counters', [
            'items' => [['label' => 'Rooms in use', 'value' => 60]],
            'animate' => false,
            'show_percent' => false,
            'height' => 'thick',
        ])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('<dt class="sb-item-label font-semibold">Rooms in use</dt>', $html);
        $this->assertStringContainsString('<span x-text="shown">60</span></dd>', $html, 'No % sign.');
        $this->assertStringContainsString('style="width: 60%; background: var(--color-primary-600)"', $html);
        $this->assertStringContainsString('height: 1.25rem; background: color-mix(in oklab, currentColor 12%, transparent)', $html);
        $this->assertStringNotContainsString('x-intersect', $html, 'Shown full at once.');

        $this->page(['slug' => 'dark', 'blocks' => [self::block('bar_counters', ['items' => [['label' => 'Rooms in use', 'value' => 60]], 'appearance' => ['background' => 'brand_dark']])]]);
        $this->assertStringContainsString('background: var(--color-primary-400)', $this->get('/dark')->getContent(), 'A lighter site colour on a dark background.');
    }

    public function test_the_fill_eases_and_respects_reduced_motion(): void
    {
        $this->assertStringContainsString('prefers-reduced-motion: reduce', PercentCountersBlock::alpine(50, true));
        $this->assertSame('{ shown: 50 }', PercentCountersBlock::alpine(50, false));
    }

    public function test_their_panels_style_the_numbers_labels_and_colours(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('circle_counters', ['items' => [['label' => 'Weekends booked', 'value' => 85]]])]]);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertFormFieldExists('meter_colour', 'mountedActionSchema0')
            ->assertFormFieldExists('thickness', 'mountedActionSchema0')
            ->assertFormFieldExists('design.items.value.colour', 'mountedActionSchema0')
            ->assertFormFieldExists('design.items.label.size', 'mountedActionSchema0')
            ->assertFormFieldExists('design.items.box.columns', 'mountedActionSchema0');
    }

    public function test_a_percentage_is_0_to_100(): void
    {
        $result = app(PageImport::class)->import(['title' => 'Busy', 'slug' => 'busy', 'blocks' => [
            ['type' => 'bar_counters', 'data' => ['items' => [['label' => 'Rooms in use', 'value' => 120], ['value' => 50]]]],
        ]]);

        $this->assertSame([
            'blocks.0.data.items.0.value: The percentage field must not be greater than 100.',
            'blocks.0.data.items.1.label: The label field is required.',
        ], $result->errors);
    }
}
