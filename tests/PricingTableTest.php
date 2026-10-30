<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\Core\PricingTableBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;

/**
 * The Pricing table block: plans side by side, each with a price, features included or not, a button, one featured;
 * or SaaSykit's live plans where the app has them (tests/Fixtures/views/components/plans stands in).
 */
class PricingTableTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function plans(): array
    {
        return [
            'heading' => 'Hire the hall',
            'plans' => [
                ['name' => 'Community', 'price' => '0', 'period' => 'per hour', 'description' => 'For village groups.', 'features' => "Main hall\n+ Tables and chairs\n- Kitchen\n\n-"],
                ['name' => 'Private party', 'price' => '25.50', 'period' => 'per hour', 'featured' => true, 'badge' => 'Most popular', 'features' => "Main hall\nKitchen", 'buttons' => [['label' => 'Book now', 'url' => '/book']]],
                ['name' => 'Wedding', 'price' => 'Ask us', 'badge' => 'New'],
                ['name' => '', 'price' => '99'],
            ],
        ];
    }

    public function test_plans_show_side_by_side(): void
    {
        $this->page(['blocks' => [self::block('pricing_table', self::plans())]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, '<h3 class="sb-item-title'), 'A plan needs a name.');
        $this->assertStringContainsString('lg:grid-cols-3', $html);
        foreach (['£0</span>', '£25.50</span>', 'Ask us</span>', 'per hour</span>', 'For village groups.', 'Tables and chairs</span>', 'Book now', 'Most popular</span>', 'New</span>'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertMatchesRegularExpression('/<li class="flex gap-3 opacity-55">\s*<svg[^>]*>.*?<\/svg>\s*<span><span class="sr-only">Not included:<\/span> Kitchen<\/span>/s', $html);
        $this->assertMatchesRegularExpression('/class="sb-item [^"]*sb-item-highlight[^"]*ring-2[^"]*">\s*<span class="absolute -top-3[^"]*">Most popular<\/span>\s*<h3[^>]*>Private party/', $html, 'The featured plan stands out, its badge on its edge.');
        $this->assertStringNotContainsString('SaaSykit plans', $html);

        $this->page(['slug' => 'dark', 'blocks' => [self::block('pricing_table', [...self::plans(), 'appearance' => ['background' => 'brand_dark']])]]);
        $this->assertStringContainsString('ring-primary-400', $this->get('/dark')->getContent(), 'A lighter ring on a dark background.');
    }

    public function test_prices_and_feature_lines(): void
    {
        $this->assertSame('£29', PricingTableBlock::price('29', '£'));
        $this->assertSame('$9.5', PricingTableBlock::price(9.5, '$'), 'As typed.');
        $this->assertSame('Free', PricingTableBlock::price(' Free ', '£'));
        $this->assertSame('', PricingTableBlock::price(['29'], '£'));

        $this->assertSame([
            ['text' => 'Main hall', 'included' => true],
            ['text' => 'Tables', 'included' => true],
            ['text' => 'Kitchen', 'included' => false],
        ], PricingTableBlock::features("Main hall\r\n+Tables\n -  Kitchen\n\n+"));
    }

    public function test_it_can_show_the_live_saasykit_plans_uncached(): void
    {
        $this->page(['blocks' => [self::block('pricing_table', ['heading' => 'Our plans', 'source' => 'saasykit', 'plans' => self::plans()['plans']])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="sb-pricing-live pricing">\s*<div>SaaSykit plans<\/div>\s*<\/div>/', $html);
        $this->assertStringContainsString('Our plans', $html);
        $this->assertStringNotContainsString('Private party', $html, 'Its own plans are kept, not shown.');
        $this->assertFalse(PricingTableBlock::isCacheable(['source' => 'saasykit']), 'They show each visitor their own plan.');
        $this->assertTrue(PricingTableBlock::isCacheable([]));
    }

    public function test_without_saasykits_plans_it_shows_its_own(): void
    {
        $this->app['config']->set('view.paths', array_values(array_filter(config('view.paths'), fn (string $path): bool => ! str_contains($path, 'Fixtures'))));
        $this->app['view']->getFinder()->setPaths(config('view.paths'));
        $this->app['view']->getFinder()->flush();

        $this->assertFalse(PricingTableBlock::hasLivePlans());
        $this->assertFalse(PricingTableBlock::isLive(['source' => 'saasykit']));
    }

    public function test_its_panel_offers_the_live_plans_and_card_styles(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('pricing_table', self::plans())]]);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertFormFieldExists('source', 'mountedActionSchema0')
            ->assertFormFieldExists('design.items.box.background', 'mountedActionSchema0')
            ->assertFormFieldExists('design.items.value.size', 'mountedActionSchema0')
            ->assertFormFieldExists('button_design.style', 'mountedActionSchema0')
            ->set('mountedActions.0.data.source', 'saasykit')
            ->assertFormFieldHidden('plans', 'mountedActionSchema0')
            ->assertFormFieldHidden('currency', 'mountedActionSchema0');
    }

    public function test_a_plan_needs_a_name_and_there_are_at_most_four(): void
    {
        $plan = ['name' => 'Community', 'price' => '0'];
        $result = app(PageImport::class)->import(['title' => 'Prices', 'slug' => 'prices', 'blocks' => [
            ['type' => 'pricing_table', 'data' => ['plans' => [['price' => '10']]]],
            ['type' => 'pricing_table', 'data' => ['plans' => [$plan, $plan, $plan, $plan, $plan]]],
        ]]);

        $this->assertSame([
            'blocks.0.data.plans.0.name: The name field is required.',
            'blocks.1.data.plans: The plans field must not have more than 4 items.',
        ], $result->errors);
    }
}
