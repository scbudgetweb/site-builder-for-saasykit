<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\View;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Support\NumberFormat;

/**
 * Plans side by side: each with a name, a price and its period, feature lines included
 * or not ("-" in front), a button, and one featured with a badge. Or, where the app has SaaSykit's plans component,
 * the live plans themselves (with their checkout links), which change with each visitor so aren't cached.
 *
 * Your own plans can be priced monthly and annually (the annual price per month, with the monthly one worked out from
 * the annual discount or set by hand) and by company size (a list of sizes, each with a price per plan, in the
 * plans' order). Every price is worked out here, and the view switches between them (Alpine) as the visitor chooses,
 * so the page stays cacheable and shows the first choice without JavaScript. The choice can be added to the plans'
 * button links (?size=…&billing=…) for a form to pick up (a Contact form field's Filled in from the link).
 */
class PricingTableBlock extends PageBlock
{
    public const MAX_PLANS = 4;

    public const BILLINGS = ['one' => 'One price', 'both' => 'Monthly and annual'];

    public const FIRST_BILLING = ['annual' => 'Annual', 'monthly' => 'Monthly'];

    public const DEFAULT_DISCOUNT = 20;

    public const MAX_DISCOUNT = 90;

    /** Links that can carry the visitor's choice: a page on this site or a web address (not #, mailto: or tel:). */
    public const CHOICE_LINK_PATTERN = '/^(\/|https?:\/\/)/i';

    public static function name(): string
    {
        return 'pricing_table';
    }

    public static function label(): string
    {
        return __('Pricing table');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedCurrencyPound;
    }

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(__('Plans'))
            ->text('title', __('Plan name text'))
            ->text('value', __('Price text'))
            ->text('meta', __('Period text'))
            ->text('text', __('Description and features text'));
    }

    /** Whether the app has SaaSykit's plans component to show. */
    public static function hasLivePlans(): bool
    {
        return View::exists('components.plans.all');
    }

    protected static function fields(): array
    {
        $isOwn = fn (Get $get): bool => $get('source') !== 'saasykit';

        return [
            ...static::headingFields(),
            Select::make('source')
                ->label(__('Plans'))
                ->options(fn (): array => [
                    'custom' => __('Your own plans'),
                    ...(static::hasLivePlans() ? ['saasykit' => __('Your SaaSykit plans (live, with their sign-up links)')] : []),
                ])
                ->default('custom')
                ->selectablePlaceholder(false)
                ->live()
                ->visible(fn (): bool => static::hasLivePlans()),
            TextInput::make('currency')
                ->label(__('Currency symbol'))
                ->placeholder('£')
                ->maxLength(5)
                ->default('£')
                ->visible($isOwn),
            static::pricesSection($isOwn),
            static::itemTooltips(Repeater::make('plans'), __('plan'))
                ->label(__('Plans'))
                ->visible($isOwn)
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        TextInput::make('name')->label(__('Name'))->required()->maxLength(60),
                        TextInput::make('badge')
                            ->label(__('Badge (optional)'))
                            ->placeholder(__('Most popular'))
                            ->maxLength(30),
                        TextInput::make('price')
                            ->label(fn (Get $get): string => $get('../../billing') === 'both' ? __('Price per month, billed annually') : __('Price'))
                            ->hintIcon(self::HELP_ICON, __('e.g. 29, or a word such as Free. The currency symbol goes in front of a number.'))
                            ->maxLength(20)
                            ->live(onBlur: true)
                            ->hidden(fn (Get $get): bool => (bool) $get('../../by_size'))
                            // Kept while priced by size, for turning it off again.
                            ->dehydratedWhenHidden(),
                        static::monthlyInput('monthly_price', 'price', '../../annual_discount')
                            ->visible(fn (Get $get): bool => $get('../../billing') === 'both' && ! $get('../../by_size')),
                        TextInput::make('period')
                            ->label(__('Period'))
                            ->placeholder(__('per month'))
                            ->maxLength(40),
                    ]),
                    Textarea::make('description')->label(__('Description (optional)'))->rows(2)->maxLength(250),
                    Textarea::make('features')
                        ->label(__('Features'))
                        ->helperText(__('One per line. Start a line with - for something not included, e.g. "- Priority support".'))
                        ->rows(5)
                        ->maxLength(2000),
                    Toggle::make('featured')->label(__('Featured (stands out)')),
                    static::buttonsRepeater(1),
                ])
                ->minItems(1)
                ->maxItems(self::MAX_PLANS)
                ->defaultItems(3)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->addActionLabel(__('Add plan')),
        ];
    }

    /**
     * Prices: one price or monthly and annual, and by company size, with what the visitor sees around them.
     */
    protected static function pricesSection(Closure $isOwn): Section
    {
        $isBoth = fn (Get $get): bool => $get('billing') === 'both';
        $bySize = fn (Get $get): bool => (bool) $get('by_size');

        return Section::make(__('Prices'))
            ->compact()
            ->visible($isOwn)
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    Select::make('billing')
                        ->label(__('Prices'))
                        ->options(array_map(fn (string $label): string => __($label), self::BILLINGS))
                        ->default('one')
                        ->selectablePlaceholder(false)
                        ->live(),
                    TextInput::make('annual_discount')
                        ->label(__('Annual discount'))
                        ->hintIcon(self::HELP_ICON, __('How much less paying annually costs. Each monthly price is worked out from it, and you can change any of them.'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(self::MAX_DISCOUNT)
                        ->suffix('%')
                        ->default(self::DEFAULT_DISCOUNT)
                        ->live(onBlur: true)
                        ->visible($isBoth),
                    ToggleButtons::make('default_billing')
                        ->label(__('Shown first'))
                        ->options(array_map(fn (string $label): string => __($label), self::FIRST_BILLING))
                        ->default('annual')
                        ->inline()
                        ->visible($isBoth),
                    TextInput::make('annual_label')
                        ->label(__('Annual switch text'))
                        ->placeholder(__('Annually'))
                        ->hintIcon(self::HELP_ICON, __('"Save 20%" is added after it, from the annual discount.'))
                        ->maxLength(30)
                        ->visible($isBoth),
                    TextInput::make('monthly_label')
                        ->label(__('Monthly switch text'))
                        ->placeholder(__('Monthly'))
                        ->maxLength(30)
                        ->visible($isBoth),
                    TextInput::make('annual_note')
                        ->label(__('Under an annual price'))
                        ->placeholder(__(':total billed annually'))
                        ->hintIcon(self::HELP_ICON, __(':total is the price for the year (12 × the price per month).'))
                        ->maxLength(80)
                        ->visible($isBoth),
                    TextInput::make('monthly_note')
                        ->label(__('Under a monthly price'))
                        ->placeholder(__('Billed monthly'))
                        ->maxLength(80)
                        ->visible($isBoth),
                    Toggle::make('by_size')
                        ->label(__('Price by company size'))
                        ->hintIcon(self::HELP_ICON, __('Visitors choose their size from a list, and each plan shows its price for that size.'))
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('size_label')
                        ->label(__('List label'))
                        ->placeholder(__('Company size'))
                        ->maxLength(40)
                        ->visible($bySize),
                    TextInput::make('ask_text')
                        ->label(__('Instead of a blank price'))
                        ->placeholder(__('Let\'s talk'))
                        ->maxLength(30)
                        ->visible($bySize),
                    TextInput::make('ask_note')
                        ->label(__('Under it'))
                        ->placeholder(__('Tailored pricing for larger teams'))
                        ->maxLength(80)
                        ->visible($bySize)
                        ->columnSpanFull(),
                ]),
                static::itemTooltips(Repeater::make('sizes'), __('size'))
                    ->label(__('Sizes'))
                    ->hintIcon(self::HELP_ICON, __('Each size has a price for each plan, in the plans\' order: moving or removing a plan doesn\'t move its prices. Leave a price blank to show "Let\'s talk".'))
                    ->schema([
                        TextInput::make('label')
                            ->label(__('Size'))
                            ->placeholder(__('1–20 employees'))
                            ->required()
                            ->maxLength(60)
                            ->live(onBlur: true),
                        Grid::make(['default' => 1, 'sm' => 2])->schema(collect(range(1, self::MAX_PLANS))->flatMap(fn (int $number): array => [
                            TextInput::make("price_{$number}")
                                ->label(fn (Get $get): string => static::planName($get('../../plans'), $number).($get('../../billing') === 'both' ? ' · '.__('per month, billed annually') : ''))
                                ->maxLength(20)
                                ->live(onBlur: true)
                                ->visible(fn (Get $get): bool => static::planName($get('../../plans'), $number) !== null)
                                ->columnSpan(fn (Get $get): int|string => $get('../../billing') === 'both' ? 1 : 'full'),
                            static::monthlyInput("monthly_{$number}", "price_{$number}", '../../annual_discount')
                                ->label(fn (Get $get): string => static::planName($get('../../plans'), $number).' · '.__('monthly price'))
                                ->visible(fn (Get $get): bool => $get('../../billing') === 'both' && static::planName($get('../../plans'), $number) !== null),
                        ])->all()),
                    ])
                    ->defaultItems(0)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => is_string($state['label'] ?? null) ? $state['label'] : null)
                    ->addActionLabel(__('Add size'))
                    ->visible($bySize)
                    ->dehydratedWhenHidden(),
                Toggle::make('pass_choice')
                    ->label(__('Add the visitor\'s choice to the plan buttons\' links'))
                    ->hintIcon(self::HELP_ICON, __('Adds ?size=…&billing=annual (or monthly) to links to a page, so a form there can fill them in: give its fields Filled in from the link size and billing.'))
                    ->visible(fn (Get $get): bool => $isBoth($get) || $bySize($get)),
            ]);
    }

    /**
     * A monthly price: blank shows (and uses) the one worked out from the annual price and discount.
     */
    protected static function monthlyInput(string $name, string $annualField, string $discountPath): TextInput
    {
        return TextInput::make($name)
            ->label(__('Monthly price'))
            ->hintIcon(self::HELP_ICON, __('Worked out from the annual price and discount. Fill it in to round it or set your own.'))
            ->maxLength(20)
            ->placeholder(function (Get $get) use ($annualField, $discountPath): ?string {
                $annual = self::amount($get($annualField));

                return $annual === null ? null : self::plainAmount(self::workedOutMonthly($annual, self::discount($get($discountPath))));
            });
    }

    /**
     * The name of a plan by its place (1 for the first), or null when there's no such plan.
     */
    public static function planName(mixed $plans, int $number): ?string
    {
        $plan = array_values(is_array($plans) ? $plans : [])[$number - 1] ?? null;

        if (! is_array($plan)) {
            return null;
        }

        return is_string($plan['name'] ?? null) && filled($plan['name']) ? $plan['name'] : __('Plan :number', ['number' => $number]);
    }

    public static function isLive(array $data): bool
    {
        return ($data['source'] ?? 'custom') === 'saasykit' && static::hasLivePlans();
    }

    /** Live plans show the visitor's own state (e.g. their current plan), so the page isn't cached. */
    public static function isCacheable(array $data): bool
    {
        return ! static::isLive($data);
    }

    public static function viewData(array $data, Page $page): array
    {
        if (static::isLive($data)) {
            return ['isLive' => true, 'plans' => [], 'choices' => null];
        }

        $currency = is_string($data['currency'] ?? null) ? $data['currency'] : '£';
        $choices = self::choices($data);
        $sizeRows = $choices['sizes'] === [] ? [] : self::sizeRows($data);

        // Plans keep their place (1 for the first) for the sizes' prices, before unnamed ones are left out.
        $plans = collect(array_values(is_array($data['plans'] ?? null) ? $data['plans'] : []))
            ->map(fn (mixed $plan, int $index): mixed => is_array($plan) ? [...$plan, 'number' => $index + 1] : $plan)
            ->filter(fn (mixed $plan): bool => is_array($plan) && is_string($plan['name'] ?? null) && filled($plan['name']))
            ->values()
            ->map(function (array $plan) use ($data, $currency, $choices, $sizeRows): array {
                $prices = self::planPrices($plan, $data, $currency, $choices['billings'], $sizeRows);
                $shown = $prices[0][$choices['billing']];

                return [
                    'name' => $plan['name'],
                    'badge' => is_string($plan['badge'] ?? null) ? $plan['badge'] : '',
                    'price' => $shown['amount'],
                    'note' => $shown['note'],
                    'isAsk' => $shown['isAsk'],
                    'prices' => $prices,
                    'period' => is_string($plan['period'] ?? null) ? $plan['period'] : '',
                    'description' => is_string($plan['description'] ?? null) ? $plan['description'] : '',
                    'features' => self::features($plan['features'] ?? null),
                    'isFeatured' => ($plan['featured'] ?? false) === true,
                    'buttons' => static::validButtons($plan['buttons'] ?? null),
                ];
            })
            ->all();

        return [
            'isLive' => false,
            'plans' => $plans,
            'choices' => $choices['sizes'] !== [] || count($choices['billings']) > 1 ? $choices : null,
        ];
    }

    /**
     * What a visitor can choose: the sizes (none unless priced by size), the billings (one, or annual and monthly),
     * the one shown first, the controls' wording, and each choice's link query (null unless it's added to links).
     *
     * @param  array<string, mixed>  $data
     * @return array{sizes: array<int, string>, sizeLabel: string, billings: array<int, string>, billing: string, labels: array<string, string>, saving: int, queries: array<int, array<string, string>>|null}
     */
    public static function choices(array $data): array
    {
        $text = fn (string $key, string $default): string => self::wording($data, $key, $default);
        $isBoth = ($data['billing'] ?? 'one') === 'both';
        $billings = $isBoth ? ['annual', 'monthly'] : ['one'];
        $sizes = ($data['by_size'] ?? false) === true ? array_column(self::sizeRows($data), 'label') : [];
        $queries = null;

        if (($data['pass_choice'] ?? false) === true && ($isBoth || $sizes !== [])) {
            foreach ($sizes === [] ? [null] : $sizes as $index => $size) {
                foreach ($billings as $billing) {
                    $queries[$index][$billing] = http_build_query(array_filter(['size' => $size, 'billing' => $isBoth ? $billing : null]), '', '&', PHP_QUERY_RFC3986);
                }
            }
        }

        return [
            'sizes' => $sizes,
            'sizeLabel' => $text('size_label', __('Company size')),
            'billings' => $billings,
            'billing' => $isBoth ? static::pick($data['default_billing'] ?? null, self::FIRST_BILLING, 'annual') : 'one',
            'labels' => ['annual' => $text('annual_label', __('Annually')), 'monthly' => $text('monthly_label', __('Monthly'))],
            'saving' => $isBoth ? (int) round(self::discount($data['annual_discount'] ?? null)) : 0,
            'queries' => $queries,
        ];
    }

    /**
     * The block's own wording for something, or the standard wording when it's blank.
     *
     * @param  array<string, mixed>  $data
     */
    private static function wording(array $data, string $key, string $default): string
    {
        return is_string($data[$key] ?? null) && trim($data[$key]) !== '' ? trim($data[$key]) : $default;
    }

    /**
     * The sizes with a label, each a row of prices by plan (price_1, monthly_1, …).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private static function sizeRows(array $data): array
    {
        return array_values(array_filter(
            is_array($data['sizes'] ?? null) ? $data['sizes'] : [],
            fn (mixed $size): bool => is_array($size) && is_string($size['label'] ?? null) && trim($size['label']) !== '',
        ));
    }

    /**
     * A plan's prices: by size (one row without sizes), then by billing, each as shown.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $billings
     * @param  array<int, array<string, mixed>>  $sizeRows
     * @return array<int, array<string, array{amount: string, note: string, isAsk: bool}>>
     */
    private static function planPrices(array $plan, array $data, string $currency, array $billings, array $sizeRows): array
    {
        $number = $plan['number'];
        $rows = $sizeRows === []
            ? [['annual' => $plan['price'] ?? null, 'monthly' => $plan['monthly_price'] ?? null, 'isSized' => false]]
            : array_map(fn (array $size): array => ['annual' => $size["price_{$number}"] ?? null, 'monthly' => $size["monthly_{$number}"] ?? null, 'isSized' => true], $sizeRows);
        $text = fn (string $key, string $default): string => self::wording($data, $key, $default);
        $discount = self::discount($data['annual_discount'] ?? null);

        return array_map(function (array $row) use ($billings, $currency, $text, $discount): array {
            $price = self::priceText($row['annual']);
            $annual = self::amount($price);
            $prices = [];

            foreach ($billings as $billing) {
                $prices[$billing] = match (true) {
                    $price === '' && $row['isSized'] => ['amount' => $text('ask_text', __('Let\'s talk')), 'note' => $text('ask_note', __('Tailored pricing for larger teams')), 'isAsk' => true],
                    // One price, as it's always been shown (the currency in front of a number), or a word for either billing.
                    $annual === null || ($billing === 'one' && ! $row['isSized']) => ['amount' => self::price($price, $currency), 'note' => '', 'isAsk' => false],
                    $billing === 'one' => ['amount' => self::money($annual, $currency), 'note' => '', 'isAsk' => false],
                    $billing === 'annual' => [
                        'amount' => self::money($annual, $currency),
                        'note' => str_replace(':total', self::money($annual * 12, $currency), $text('annual_note', __(':total billed annually'))),
                        'isAsk' => false,
                    ],
                    default => [
                        'amount' => self::money(self::amount($row['monthly']) ?? self::workedOutMonthly($annual, $discount), $currency),
                        'note' => $text('monthly_note', __('Billed monthly')),
                        'isAsk' => false,
                    ],
                };
            }

            return $prices;
        }, $rows);
    }

    /**
     * A price typed as a number (29, 9.50, 1,200), or null for a word or nothing.
     */
    public static function amount(mixed $price): ?float
    {
        $price = str_replace(',', '', self::priceText($price));

        return is_numeric($price) && (float) $price >= 0 ? (float) $price : null;
    }

    /** A saved price as text, trimmed ('' for anything else). */
    private static function priceText(mixed $price): string
    {
        return is_string($price) || is_int($price) || is_float($price) ? trim((string) $price) : '';
    }

    /** The annual discount, 0 to 90 (%). */
    public static function discount(mixed $discount): float
    {
        return is_numeric($discount) ? max(0, min(self::MAX_DISCOUNT, (float) $discount)) : self::DEFAULT_DISCOUNT;
    }

    /**
     * The monthly price for an annual price per month: what it would be without the discount, to the penny.
     */
    public static function workedOutMonthly(float $annual, float $discount): float
    {
        return round($annual / (1 - $discount / 100), 2);
    }

    /** A number as a price: 1,188, or 186.25 when it isn't whole. */
    public static function plainAmount(float $amount): string
    {
        return NumberFormat::format($amount, 'thousands', round($amount, 2) === floor($amount) ? 0 : 2);
    }

    /** A number as a price with its currency: £1,188, or £186.25. */
    public static function money(float $amount, string $currency): string
    {
        return $currency.self::plainAmount($amount);
    }

    /**
     * A link with the visitor's choice added to its query (before any #anchor), for links that can carry it. Kept in
     * step with the view's link().
     */
    public static function withChoice(string $url, ?string $query): string
    {
        if ($query === null || $query === '' || preg_match(self::CHOICE_LINK_PATTERN, $url) !== 1) {
            return $url;
        }

        [$base, $anchor] = array_pad(explode('#', $url, 2), 2, null);

        return $base.(str_contains($base, '?') ? '&' : '?').$query.($anchor !== null ? '#'.$anchor : '');
    }

    /**
     * The price as shown: the currency in front of a number ("£29", "£9.50"), a word as it is ("Free").
     */
    public static function price(mixed $price, string $currency): string
    {
        $price = self::priceText($price);

        return is_numeric($price) ? $currency.$price : $price;
    }

    /**
     * Feature lines: included unless they start with "-" ("+" in front is allowed too, and dropped).
     *
     * @return array<int, array{text: string, included: bool}>
     */
    public static function features(mixed $features): array
    {
        $lines = is_string($features) ? preg_split('/\R/', $features) : [];

        return collect($lines ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '' && ! in_array($line, ['-', '+'], true))
            ->map(fn (string $line): array => [
                'text' => ltrim(in_array($line[0], ['-', '+'], true) ? substr($line, 1) : $line),
                'included' => $line[0] !== '-',
            ])
            ->values()
            ->all();
    }
}
