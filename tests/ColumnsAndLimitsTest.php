<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Forms\Components\Repeater;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\StatsBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\StepsBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Design\Items;

/**
 * Repeated items' columns on each screen size, higher item limits, and rows that stay side by side on phones.
 */
class ColumnsAndLimitsTest extends PageTestCase
{
    public function test_items_columns_apply_on_their_own_screen_size_only(): void
    {
        $output = BlockDesign::resolve(['items' => ['box' => ['columns' => ['desktop' => '4', 'phone' => 2]]]]);

        $classes = explode(' ', $output->classes('section'));
        $this->assertContains('sb-d-icold', $classes);
        $this->assertContains('sb-d-icolp', $classes);
        $this->assertNotContains('sb-d-icolt', $classes, 'Blank: the block\'s standard on tablets.');
        $this->assertStringContainsString('--sb-icold: 4;', $output->style('section'));
        $this->assertStringContainsString('--sb-icolp: 2;', $output->style('section'));

        $this->assertSame([], Items::columns(['box' => ['columns' => ['desktop' => 7, 'tablet' => 0, 'phone' => '2;}']]]), 'Only 1 to 6.');

        $css = (string) file_get_contents(__DIR__.'/../resources/css/design/items.css');
        $this->assertStringContainsString('.sb-d-icolp .sb-items { grid-template-columns: repeat(var(--sb-icolp), minmax(0, 1fr)); }', $css);
    }

    public function test_steps_connectors_follow_the_columns(): void
    {
        $this->page(['blocks' => [self::block('steps', [
            'connector' => 'solid',
            'items' => [['title' => 'One'], ['title' => 'Two'], ['title' => 'Three'], ['title' => 'Four']],
            'design' => ['items' => ['box' => ['columns' => ['tablet' => 4, 'phone' => 2]]]],
        ])]]);

        $this->assertStringContainsString('sb-steps-d-4 sb-steps-t-4 sb-steps-p-2 sb-steps-line', $this->get('/about-us')->getContent());

        $css = (string) file_get_contents(__DIR__.'/../resources/css/design/items.css');
        $this->assertStringContainsString('.sb-steps-line.sb-steps-p-2 .sb-item:nth-child(2n) .sb-step-head::after', $css);
    }

    public function test_stats_and_steps_take_up_to_twelve(): void
    {
        foreach ([StatsBlock::class, StepsBlock::class] as $blockClass) {
            $fields = (new \ReflectionMethod($blockClass, 'fields'))->invoke(null);
            $items = collect($fields)->first(fn ($field): bool => $field instanceof Repeater && $field->getName() === 'items');

            $this->assertSame(12, $items->getMaxItems(), $blockClass);
        }
    }

    public function test_a_row_can_stay_side_by_side_on_phones(): void
    {
        $this->page(['blocks' => [self::block('section', ['rows' => [
            ['layout' => '1-2', 'side_by_side_on_phones' => true, 'reverse_on_phones' => true, 'blocks' => [
                self::block('text', ['content' => '<p>Logo</p>']),
                self::block('text', ['content' => '<p>Menu</p>']),
            ]],
            ['layout' => '2', 'blocks' => [
                self::block('text', ['content' => '<p>Stacks</p>']),
                self::block('text', ['content' => '<p>On phones</p>']),
            ]],
        ]])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="grid [^"]*grid-cols-3[^"]*"\s*data-row-layout="1-2"/', $html);
        $this->assertMatchesRegularExpression('/class="min-w-0 col-span-1"[^>]*>.*Logo.*class="min-w-0 col-span-2"[^>]*>.*Menu/s', $html);
        $this->assertStringNotContainsString('max-md:order-first', $html, 'Never stacked, so never reordered.');
        $this->assertMatchesRegularExpression('/class="grid grid-cols-1 [^"]*md:grid-cols-2[^"]*"\s*data-row-layout="2"/', $html, 'Other rows stack as before.');

        $this->assertSame(array_keys(SectionBlock::LAYOUTS), ['stacked', ...array_keys(SectionBlock::SIDE_BY_SIDE_LAYOUTS)], 'Every side by side layout has its phone classes.');
    }
}
