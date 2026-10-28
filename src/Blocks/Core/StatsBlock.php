<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

class StatsBlock extends PageBlock
{
    /**
     * "custom" (a number typed in) plus the app's live figures (SiteBuilder::stats()), as key => label.
     *
     * @return array<string, string>
     */
    public static function sources(): array
    {
        $sources = ['custom' => __('A number I type in')];

        foreach (app(SiteBuilder::class)->statSources() as $key => [$label]) {
            $sources['live:'.$key] = __('Live: :figure', ['figure' => __($label)]);
        }

        return $sources;
    }

    public static function name(): string
    {
        return 'stats';
    }

    public static function label(): string
    {
        return __('Stats');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedChartBar;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            static::itemTooltips(Repeater::make('items'), __('number'))
                ->label(__('Numbers'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('source')
                            ->label(__('Number'))
                            ->hintIcon(self::HELP_ICON, __('A number you type in, or a live figure that keeps itself up to date.'))
                            ->options(fn (): array => self::sources())
                            ->default('custom')
                            ->selectablePlaceholder(false)
                            ->live(),
                        TextInput::make('value')
                            ->label(__('Value'))
                            ->numeric()
                            ->required()
                            ->visible(fn (Get $get): bool => $get('source') === 'custom'),
                        TextInput::make('prefix')->label(__('Before the number'))->maxLength(5)->placeholder('£')
                            ->hintIcon(self::HELP_ICON, __('Optional, e.g. £ to show £500, or + after it to show 500+.')),
                        TextInput::make('suffix')->label(__('After the number'))->maxLength(5)->placeholder('+'),
                    ]),
                    TextInput::make('label')->label(__('Label'))->required()->maxLength(80),
                    static::iconSelect('icon', __('Icon (optional)'))
                        ->helperText(__('Shown above the number.')),
                ])
                ->minItems(1)
                ->maxItems(6)
                ->defaultItems(3)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->addActionLabel(__('Add number')),
            Toggle::make('animate')->label(__('Count up when scrolled into view'))->default(true),
        ];
    }

    /**
     * Live figures change, so a block showing one isn't cached.
     */
    public static function isCacheable(array $data): bool
    {
        return ! collect(is_array($data['items'] ?? null) ? $data['items'] : [])
            ->contains(fn (mixed $item): bool => is_array($item) && is_string($item['source'] ?? null) && str_starts_with($item['source'], 'live:'));
    }

    public static function viewData(array $data, Page $page): array
    {
        $sources = app(SiteBuilder::class)->statSources();

        $items = collect(is_array($data['items'] ?? null) ? $data['items'] : [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use ($sources): array {
                $source = is_string($item['source'] ?? null) ? $item['source'] : 'custom';
                $key = str_starts_with($source, 'live:') ? substr($source, 5) : null;

                $item['number'] = match (true) {
                    $key !== null && isset($sources[$key]) => self::liveCount($key, $sources[$key][1]),
                    // A live figure the app no longer provides shows nothing rather than a wrong 0.
                    $key !== null => null,
                    default => is_numeric($item['value'] ?? null) ? (float) $item['value'] : 0,
                };

                return $item;
            })
            ->filter(fn (array $item): bool => $item['number'] !== null)
            ->values()
            ->all();

        return ['items' => $items];
    }

    /**
     * Live figures are cached briefly: they're decoration, not something that must be exact to the second. After ten
     * minutes the last figure is still shown while one request works out the new one after its response (so a busy
     * page doesn't run the app's count once per visitor). A figure that fails is reported and left out, rather than
     * breaking the page.
     */
    private static function liveCount(string $key, \Closure $count): int|float|null
    {
        $value = Cache::flexible("site-builder.stats.{$key}", [600, 900], function () use ($count): mixed {
            try {
                return $count();
            } catch (\Throwable $exception) {
                report($exception);

                return null;
            }
        });

        return is_numeric($value) ? $value + 0 : null;
    }
}
