<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\View;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Plans side by side: each with a name, a price and its period, feature lines included
 * or not ("-" in front), a button, and one featured with a badge. Or, where the app has SaaSykit's plans component,
 * the live plans themselves (with their checkout links), which change with each visitor so aren't cached.
 */
class PricingTableBlock extends PageBlock
{
    public const MAX_PLANS = 4;

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
                            ->label(__('Price'))
                            ->hintIcon(self::HELP_ICON, __('e.g. 29, or a word such as Free. The currency symbol goes in front of a number.'))
                            ->maxLength(20),
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
            return ['isLive' => true, 'plans' => []];
        }

        $currency = is_string($data['currency'] ?? null) ? $data['currency'] : '£';

        return [
            'isLive' => false,
            'plans' => collect(is_array($data['plans'] ?? null) ? $data['plans'] : [])
                ->filter(fn (mixed $plan): bool => is_array($plan) && is_string($plan['name'] ?? null) && filled($plan['name']))
                ->map(fn (array $plan): array => [
                    'name' => $plan['name'],
                    'badge' => is_string($plan['badge'] ?? null) ? $plan['badge'] : '',
                    'price' => self::price($plan['price'] ?? null, $currency),
                    'period' => is_string($plan['period'] ?? null) ? $plan['period'] : '',
                    'description' => is_string($plan['description'] ?? null) ? $plan['description'] : '',
                    'features' => self::features($plan['features'] ?? null),
                    'isFeatured' => ($plan['featured'] ?? false) === true,
                    'buttons' => static::validButtons($plan['buttons'] ?? null),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The price as shown: the currency in front of a number ("£29", "£9.50"), a word as it is ("Free").
     */
    public static function price(mixed $price, string $currency): string
    {
        $price = is_string($price) || is_int($price) || is_float($price) ? trim((string) $price) : '';

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
