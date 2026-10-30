<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use Throwable;

/**
 * Counts down to a date and time (Divi's Countdown Timer module): days, hours, minutes and seconds, each an item for
 * Design › Items. The end is a moment in the site's time zone, given to the page as a timestamp, so a cached page
 * still counts right; the numbers in the HTML are only where it starts. When it ends: hide the block, show a
 * message, or stay at zero.
 */
class CountdownBlock extends PageBlock
{
    /** When it ends => label. */
    public const WHEN_ENDED = [
        'message' => 'Show a message',
        'hide' => 'Hide the block',
        'zero' => 'Stay at zero',
    ];

    /** Unit => its length in seconds. */
    public const UNITS = ['days' => 86400, 'hours' => 3600, 'minutes' => 60, 'seconds' => 1];

    public static function name(): string
    {
        return 'countdown';
    }

    public static function label(): string
    {
        return __('Countdown');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedClock;
    }

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(__('Units'))
            ->text('value', __('Number text'))
            ->text('label', __('Label text'));
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                DateTimePicker::make('ends_at')
                    ->label(__('Counts down to'))
                    ->helperText(__('In the site\'s time zone (:zone).', ['zone' => config('app.timezone')]))
                    ->seconds(false)
                    ->required(),
                Toggle::make('show_seconds')
                    ->label(__('Show seconds'))
                    ->default(true)
                    ->inline(false),
                Select::make('when_ended')
                    ->label(__('When it ends'))
                    ->options(array_map(fn (string $label): string => __($label), self::WHEN_ENDED))
                    ->default('message')
                    ->selectablePlaceholder(false)
                    ->live(),
                Textarea::make('ended_message')
                    ->label(__('Message'))
                    ->placeholder(__('This has now ended.'))
                    ->rows(2)
                    ->maxLength(300)
                    ->visible(fn (Get $get): bool => ($get('when_ended') ?? 'message') === 'message'),
            ]),
            static::buttonsRepeater(2),
        ];
    }

    /**
     * The moment it counts down to, or null when there's none (or it can't be read).
     */
    public static function endsAt(array $data): ?CarbonImmutable
    {
        $value = $data['ends_at'] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, (string) config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    /** Nothing to count down to, or ended with "Hide the block": nothing to show. */
    public static function isVisible(array $data): bool
    {
        $endsAt = static::endsAt($data);

        return $endsAt !== null && ! (static::pick($data['when_ended'] ?? null, self::WHEN_ENDED, 'message') === 'hide' && $endsAt->isPast());
    }

    public static function viewData(array $data, Page $page): array
    {
        $endsAt = static::endsAt($data) ?? CarbonImmutable::now();
        $left = max(0, $endsAt->getTimestamp() - CarbonImmutable::now()->getTimestamp());
        $units = ($data['show_seconds'] ?? true) === false ? array_slice(self::UNITS, 0, 3) : self::UNITS;

        return [
            'endsAt' => $endsAt->getTimestamp() * 1000,
            'endsAtText' => $endsAt->translatedFormat('j F Y, H:i'),
            'units' => array_map(fn (string $unit): array => [
                'unit' => $unit,
                'value' => self::unitValue($left, $unit),
                'labels' => self::unitLabels($unit),
            ], array_keys($units)),
            'hasEnded' => $left === 0,
            'whenEnded' => static::pick($data['when_ended'] ?? null, self::WHEN_ENDED, 'message'),
            'endedMessage' => is_string($data['ended_message'] ?? null) && filled($data['ended_message']) ? $data['ended_message'] : __('This has now ended.'),
            'buttons' => static::validButtons($data['buttons'] ?? null),
        ];
    }

    /**
     * A unit's number with $seconds left: whole days, then what's left in hours, minutes and seconds (two digits).
     */
    public static function unitValue(int $seconds, string $unit): string
    {
        $value = $unit === 'days' ? intdiv($seconds, 86400) : intdiv($seconds % ($unit === 'hours' ? 86400 : ($unit === 'minutes' ? 3600 : 60)), self::UNITS[$unit]);

        return $unit === 'days' ? (string) $value : str_pad((string) $value, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{0: string, 1: string} one, and more than one
     */
    private static function unitLabels(string $unit): array
    {
        return match ($unit) {
            'days' => [__('Day'), __('Days')],
            'hours' => [__('Hour'), __('Hours')],
            'minutes' => [__('Minute'), __('Minutes')],
            default => [__('Second'), __('Seconds')],
        };
    }
}
