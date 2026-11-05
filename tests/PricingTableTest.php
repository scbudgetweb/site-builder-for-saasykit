<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\Core\PricingTableBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;

/**
 * The Pricing table block: plans side by side, each with a price, features included or not, a button, one featured;
 * or SaaSykit's live plans where the app has them (tests/Fixtures/views/components/plans stands in). Its own plans can be
 * priced monthly and annually (the monthly price worked out from the annual discount or set by hand) and by company
 * size, with the visitor's choice added to the plans' button links.
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function viewOf(array $data): array
    {
        return PricingTableBlock::viewData($data, new Page);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function html(array $data): string
    {
        $this->page(['slug' => 'pricing', 'blocks' => [self::block('pricing_table', $data)]]);

        return (string) $this->get('/pricing')->assertOk()->getContent();
    }

    /**
     * Two plans billed monthly and annually, with a 20% annual discount.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function billed(array $data = []): array
    {
        return [
            'billing' => 'both',
            'annual_discount' => 20,
            'plans' => [
                ['name' => 'Essentials', 'price' => '149', 'period' => '/month'],
                ['name' => 'Professional', 'price' => '239', 'monthly_price' => '299', 'period' => '/month'],
            ],
            ...$data,
        ];
    }

    /**
     * Two plans priced by company size, the second blank for the larger size.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function sized(array $data = []): array
    {
        return [
            'by_size' => true,
            'plans' => [['name' => 'Essentials'], ['name' => 'Professional']],
            'sizes' => [
                ['label' => '1–20 employees', 'price_1' => '149', 'price_2' => '1239'],
                ['label' => '', 'price_1' => '1'],
                ['label' => '21–50 employees', 'price_1' => '219', 'price_2' => ''],
            ],
            ...$data,
        ];
    }

    public function test_one_price_shows_as_it_always_has(): void
    {
        $view = $this->viewOf(['plans' => [['name' => 'Community', 'price' => '1200', 'period' => 'per hour'], ['name' => 'Free', 'price' => 'Free']]]);

        $this->assertNull($view['choices'], 'Nothing to choose.');
        $this->assertSame(['£1200', 'Free'], array_column($view['plans'], 'price'));
        $this->assertSame(['', ''], array_column($view['plans'], 'note'));

        $html = $this->html(['plans' => [['name' => 'Community', 'price' => '29', 'period' => 'per hour']]]);
        $this->assertStringContainsString('£29', $html);
        $this->assertStringNotContainsString('sb-pricing-choices', $html);
        $this->assertStringNotContainsString('x-text="price(', $html);
    }

    public function test_the_monthly_price_is_worked_out_from_the_annual_discount_or_set(): void
    {
        $view = $this->viewOf(self::billed());
        [$essentials, $professional] = $view['plans'];

        $this->assertSame(['annual', 'monthly'], $view['choices']['billings']);
        $this->assertSame('annual', $view['choices']['billing'], 'Annual first unless chosen.');
        $this->assertSame(20, $view['choices']['saving']);
        $this->assertSame(['£149', '£1,788 billed annually'], [$essentials['price'], $essentials['note']]);
        $this->assertSame(['amount' => '£186.25', 'note' => 'Billed monthly', 'isAsk' => false], $essentials['prices'][0]['monthly'], '149 ÷ 0.8.');
        $this->assertSame('£299', $professional['prices'][0]['monthly']['amount'], 'Set by hand.');
        $this->assertSame('£2,868 billed annually', $professional['prices'][0]['annual']['note']);

        $monthlyFirst = $this->viewOf(self::billed(['default_billing' => 'monthly']));
        $this->assertSame(['£186.25', 'Billed monthly'], [$monthlyFirst['plans'][0]['price'], $monthlyFirst['plans'][0]['note']]);
    }

    public function test_billing_wording_and_discount_can_be_changed(): void
    {
        $view = $this->viewOf(self::billed([
            'annual_discount' => '0',
            'annual_label' => 'Yearly',
            'monthly_label' => 'Month by month',
            'annual_note' => 'Just :total a year',
            'monthly_note' => 'Cancel any time',
            'plans' => [['name' => 'Essentials', 'price' => '9.5'], ['name' => 'Free', 'price' => 'Free']],
        ]));

        $this->assertSame(0, $view['choices']['saving'], 'No discount, no "Save".');
        $this->assertSame(['annual' => 'Yearly', 'monthly' => 'Month by month'], $view['choices']['labels']);
        $this->assertSame(['amount' => '£9.50', 'note' => 'Just £114 a year', 'isAsk' => false], $view['plans'][0]['prices'][0]['annual']);
        $this->assertSame(['amount' => '£9.50', 'note' => 'Cancel any time', 'isAsk' => false], $view['plans'][0]['prices'][0]['monthly']);
        $this->assertSame(['amount' => 'Free', 'note' => '', 'isAsk' => false], $view['plans'][1]['prices'][0]['monthly'], 'A word is the same either way.');
        $this->assertSame(PricingTableBlock::MAX_DISCOUNT, (int) PricingTableBlock::discount('150'));
        $this->assertSame((float) PricingTableBlock::DEFAULT_DISCOUNT, PricingTableBlock::discount(null));
    }

    public function test_prices_by_company_size_with_lets_talk_for_a_blank_price(): void
    {
        $view = $this->viewOf(self::sized());
        [$essentials, $professional] = $view['plans'];

        $this->assertSame(['1–20 employees', '21–50 employees'], $view['choices']['sizes'], 'Sizes without a label are left out.');
        $this->assertSame(['one'], $view['choices']['billings']);
        $this->assertSame('£149', $essentials['price'], 'The first size first.');
        $this->assertSame('£1,239', $professional['prices'][0]['one']['amount']);
        $this->assertSame('£219', $essentials['prices'][1]['one']['amount']);
        $this->assertSame(['amount' => 'Let\'s talk', 'note' => 'Tailored pricing for larger teams', 'isAsk' => true], $professional['prices'][1]['one']);

        $own = $this->viewOf(self::sized(['ask_text' => 'Call us', 'ask_note' => 'For bigger teams', 'billing' => 'both']));
        $this->assertSame(['amount' => 'Call us', 'note' => 'For bigger teams', 'isAsk' => true], $own['plans'][1]['prices'][1]['monthly']);
        $this->assertSame('£273.75', $own['plans'][0]['prices'][1]['monthly']['amount'], 'Worked out for each size: 219 ÷ 0.8.');
    }

    public function test_size_prices_follow_the_plans_places(): void
    {
        $view = $this->viewOf([
            'by_size' => true,
            'plans' => [['name' => 'Basic'], ['name' => ''], ['name' => 'Gold']],
            'sizes' => [['label' => 'Small', 'price_1' => '10', 'price_2' => '20', 'price_3' => '30']],
        ]);

        $this->assertSame(['Basic', 'Gold'], array_column($view['plans'], 'name'));
        $this->assertSame(['£10', '£30'], array_column($view['plans'], 'price'), 'Gold is the third plan, so it has the third price.');
        $this->assertSame('Gold', PricingTableBlock::planName([['name' => 'Basic'], [], ['name' => 'Gold']], 3));
        $this->assertSame('Plan 2', PricingTableBlock::planName([['name' => 'Basic'], ['name' => '']], 2));
        $this->assertNull(PricingTableBlock::planName([['name' => 'Basic']], 2));
    }

    public function test_sizes_turned_off_or_left_empty_use_the_plans_own_prices(): void
    {
        $this->assertNull($this->viewOf(self::sized(['by_size' => false, 'plans' => [['name' => 'Basic', 'price' => '5']]]))['choices']);
        $this->assertSame('£5', $this->viewOf(['by_size' => true, 'sizes' => [], 'plans' => [['name' => 'Basic', 'price' => '5']]])['plans'][0]['price']);
    }

    public function test_numbers_are_read_and_shown_as_prices(): void
    {
        $this->assertSame(1200.0, PricingTableBlock::amount('1,200'));
        $this->assertSame(9.5, PricingTableBlock::amount(' 9.50 '));
        $this->assertSame(29.0, PricingTableBlock::amount(29));
        $this->assertNull(PricingTableBlock::amount('Free'));
        $this->assertNull(PricingTableBlock::amount('-5'));
        $this->assertNull(PricingTableBlock::amount(null));
        $this->assertSame('£1,188', PricingTableBlock::money(1188, '£'));
        $this->assertSame('$186.25', PricingTableBlock::money(186.25, '$'));
        $this->assertSame(186.25, PricingTableBlock::workedOutMonthly(149, 20));
        $this->assertSame(149.0, PricingTableBlock::workedOutMonthly(149, 0));
    }

    public function test_the_choice_can_be_added_to_the_plans_button_links(): void
    {
        $this->assertSame('/contact?size=Small&billing=annual#form', PricingTableBlock::withChoice('/contact#form', 'size=Small&billing=annual'));
        $this->assertSame('https://example.com/a?b=1&billing=monthly', PricingTableBlock::withChoice('https://example.com/a?b=1', 'billing=monthly'));
        $this->assertSame('#contact', PricingTableBlock::withChoice('#contact', 'billing=monthly'), 'A part of this page can\'t take a query.');
        $this->assertSame('mailto:hi@example.com', PricingTableBlock::withChoice('mailto:hi@example.com', 'billing=monthly'));
        $this->assertSame('/contact', PricingTableBlock::withChoice('/contact', null));

        $choices = $this->viewOf(self::sized(['billing' => 'both', 'pass_choice' => true]))['choices'];
        $this->assertSame('size=1%E2%80%9320%20employees&billing=annual', $choices['queries'][0]['annual']);
        $this->assertSame('size=21%E2%80%9350%20employees&billing=monthly', $choices['queries'][1]['monthly']);
        $this->assertSame([['annual' => 'billing=annual', 'monthly' => 'billing=monthly']], $this->viewOf(self::billed(['pass_choice' => true]))['choices']['queries'], 'Only what there is to choose.');
        $this->assertNull($this->viewOf(self::billed())['choices']['queries'], 'Off unless chosen.');

        $buttons = fn (string $url): array => [['label' => 'Start', 'url' => $url]];
        $html = $this->html(self::billed(['pass_choice' => true, 'plans' => [
            ['name' => 'Essentials', 'price' => '149', 'buttons' => $buttons('/contact#form')],
            ['name' => 'Professional', 'price' => '239', 'buttons' => $buttons('mailto:sales@sometown-hall.test')],
        ]]));

        $this->assertStringContainsString('href="/contact?billing=annual#form"', $html);
        $this->assertStringContainsString('x-bind:href="link(\'\/contact#form\')"', $html);
        $this->assertStringContainsString('href="mailto:sales@sometown-hall.test"', $html);
        $this->assertSame(1, substr_count($html, 'x-bind:href="link('), 'Only links to a page carry it.');
    }

    public function test_the_page_shows_the_choices_with_the_first_ones_prices(): void
    {
        $html = $this->html(self::sized(['billing' => 'both', 'size_label' => 'Team size']));

        $this->assertStringContainsString('sb-pricing-choices', $html);
        $this->assertStringContainsString('>Team size</label>', $html);
        $this->assertStringContainsString('<option value="1" >21–50 employees</option>', $html);
        $this->assertStringContainsString('Save 20%', $html);
        $this->assertMatchesRegularExpression('/billing = \'monthly\'"\s+aria-pressed="false"/', $html);
        $this->assertMatchesRegularExpression('/billing = \'annual\'"\s+aria-pressed="true"/', $html, 'Annual is pressed first.');
        $this->assertMatchesRegularExpression('/x-text="price\(0\)\.amount"\s*>£149<\/span>/', $html, 'The first choice\'s price, before Alpine runs.');
        $this->assertStringContainsString('£1,788 billed annually', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
    }

    public function test_its_panel_shows_the_price_settings_as_they_apply(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('pricing_table', ['plans' => [['name' => 'Essentials', 'price' => '149']]])]]);
        $schema = 'mountedActionSchema0';

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertFormFieldHidden('annual_discount', $schema)
            ->assertFormFieldHidden('sizes', $schema)
            ->assertFormFieldHidden('pass_choice', $schema)
            ->set('mountedActions.0.data.billing', 'both')
            ->assertFormFieldVisible('annual_discount', $schema)
            ->assertFormFieldVisible('default_billing', $schema)
            ->assertFormFieldVisible('pass_choice', $schema)
            ->set('mountedActions.0.data.plans', ['plan-1' => ['name' => 'Essentials', 'price' => '149']])
            ->assertFormFieldExists('plans.plan-1.monthly_price', $schema, fn ($field): bool => $field->getPlaceholder() === '186.25')
            ->set('mountedActions.0.data.by_size', true)
            ->assertFormFieldVisible('sizes', $schema)
            ->assertFormFieldVisible('size_label', $schema)
            ->assertFormFieldHidden('plans.plan-1.price', $schema)
            ->set('mountedActions.0.data.sizes', ['size-1' => ['label' => 'Small', 'price_1' => '80']])
            ->assertFormFieldExists('sizes.size-1.price_1', $schema, fn ($field): bool => str_starts_with($field->getLabel(), 'Essentials'))
            ->assertFormFieldExists('sizes.size-1.monthly_1', $schema, fn ($field): bool => $field->getPlaceholder() === '100')
            ->assertFormFieldHidden('sizes.size-1.price_2', $schema);
    }
}
