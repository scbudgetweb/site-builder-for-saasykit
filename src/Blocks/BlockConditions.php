<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use Throwable;

/**
 * Display conditions (Advanced › Conditions): show a block only to some visitors or at some times. Saved in the
 * block's data as "conditions" (a list of rules) and "conditions_match" (all or any).
 *
 * A block with conditions depends on who's looking and when, so it's never part of a cached page: the page is cached
 * around it and the block is checked and rendered on each request (a "hole", PageRenderer). The canvas always shows
 * it, so it can be edited.
 */
class BlockConditions
{
    public const RULES = [
        'signed_in' => 'Visitor is signed in',
        'signed_out' => 'Visitor is signed out',
        'role' => 'Visitor has a role',
        'dates' => 'Between dates',
        'days' => 'On days of the week',
        'times' => 'Between times of day',
        'url_parameter' => 'Web address has a parameter',
        'cookie' => 'Visitor has a cookie',
    ];

    public const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    private const NAME_PATTERN = '/^[A-Za-z0-9_.\-\[\]]{1,100}$/';

    public static function toggle(): PanelToggle
    {
        $type = fn (Get $get): ?string => $get('type');

        return PanelToggle::advanced('conditions', __('Conditions'), [
            Repeater::make('conditions')
                ->hiddenLabel()
                ->schema([
                    Select::make('type')
                        ->label(__('Show when'))
                        ->options(array_map(fn (string $label): string => __($label), self::RULES))
                        ->required()
                        ->live(),
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        TextInput::make('role')
                            ->label(__('Role'))
                            ->helperText(__('As it\'s named in Users › Roles, e.g. admin.'))
                            ->maxLength(100)
                            ->requiredIf('type', 'role')
                            ->visible(fn (Get $get): bool => $type($get) === 'role'),
                        DateTimePicker::make('from')
                            ->label(__('From'))
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => $type($get) === 'dates'),
                        DateTimePicker::make('until')
                            ->label(__('Until'))
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => $type($get) === 'dates'),
                        CheckboxList::make('days')
                            ->label(__('Days'))
                            ->options(array_map(fn (string $label): string => __($label), self::DAYS))
                            ->columns(4)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $type($get) === 'days'),
                        TimePicker::make('from_time')
                            ->label(__('From'))
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => $type($get) === 'times'),
                        TimePicker::make('until_time')
                            ->label(__('Until'))
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => $type($get) === 'times'),
                        TextInput::make('name')
                            ->label(fn (Get $get): string => $type($get) === 'cookie' ? __('Cookie name') : __('Parameter name'))
                            ->helperText(fn (Get $get): string => $type($get) === 'cookie' ? '' : __('E.g. "utm_source" in ?utm_source=newsletter.'))
                            ->regex(self::NAME_PATTERN)
                            ->maxLength(100)
                            ->visible(fn (Get $get): bool => in_array($type($get), ['url_parameter', 'cookie'], true)),
                        TextInput::make('value')
                            ->label(__('Equal to (optional)'))
                            ->helperText(__('Blank: any value.'))
                            ->maxLength(200)
                            ->visible(fn (Get $get): bool => in_array($type($get), ['url_parameter', 'cookie'], true)),
                    ]),
                ])
                ->itemLabel(fn (array $state): ?string => isset(self::RULES[$state['type'] ?? '']) ? __(self::RULES[$state['type']]) : null)
                ->collapsible()
                ->defaultItems(0)
                ->addActionLabel(__('Add a condition')),
            Select::make('conditions_match')
                ->label(__('Show it when'))
                ->options(['all' => __('All of these are true'), 'any' => __('Any of these is true')])
                ->default('all')
                ->selectablePlaceholder(false),
        ], 98);
    }

    /**
     * Whether the block has any conditions (so it's rendered on each request).
     *
     * @param  array<string, mixed>  $data
     */
    public static function has(array $data): bool
    {
        return self::rules($data) !== [];
    }

    /**
     * Whether the block shows for this visitor now. No conditions: always.
     *
     * @param  array<string, mixed>  $data
     */
    public static function passes(array $data, ?Authenticatable $user = null, ?Request $request = null, ?CarbonImmutable $now = null): bool
    {
        $rules = self::rules($data);

        if ($rules === []) {
            return true;
        }

        $request ??= request();
        $now ??= CarbonImmutable::now();
        $results = array_map(fn (array $rule): bool => self::check($rule, $user, $request, $now), $rules);

        return ($data['conditions_match'] ?? 'all') === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private static function rules(array $data): array
    {
        return array_values(array_filter(
            is_array($data['conditions'] ?? null) ? $data['conditions'] : [],
            fn (mixed $rule): bool => is_array($rule) && isset(self::RULES[$rule['type'] ?? '']),
        ));
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private static function check(array $rule, ?Authenticatable $user, Request $request, CarbonImmutable $now): bool
    {
        $text = fn (string $key): ?string => is_string($rule[$key] ?? null) && $rule[$key] !== '' ? $rule[$key] : null;

        return match ($rule['type']) {
            'signed_in' => $user !== null,
            'signed_out' => $user === null,
            // Spatie Permission's hasRole() when the user model has it; otherwise no one has a role.
            'role' => $user !== null && $text('role') !== null && method_exists($user, 'hasRole') && (bool) $user->hasRole($text('role')),
            'dates' => self::isAfter($now, $text('from')) && self::isBefore($now, $text('until')),
            'days' => in_array($now->dayOfWeekIso, array_map('intval', is_array($rule['days'] ?? null) ? $rule['days'] : []), true),
            'times' => self::isBetweenTimes($now, $text('from_time'), $text('until_time')),
            'url_parameter' => self::matches($text('name'), $text('value'), fn (string $name): mixed => $request->query($name)),
            'cookie' => self::matches($text('name'), $text('value'), fn (string $name): mixed => $request->cookie($name)),
            default => false,
        };
    }

    private static function isAfter(CarbonImmutable $now, ?string $from): bool
    {
        return $from === null || self::moment($from)?->lessThanOrEqualTo($now) !== false;
    }

    private static function isBefore(CarbonImmutable $now, ?string $until): bool
    {
        return $until === null || self::moment($until)?->greaterThan($now) !== false;
    }

    /**
     * Between two times of day; "22:00 until 06:00" runs over midnight.
     */
    private static function isBetweenTimes(CarbonImmutable $now, ?string $from, ?string $until): bool
    {
        $time = $now->format('H:i');
        $from = $from !== null ? substr($from, 0, 5) : '00:00';
        $until = $until !== null ? substr($until, 0, 5) : '24:00';

        return $from <= $until ? $time >= $from && $time < $until : $time >= $from || $time < $until;
    }

    /**
     * @param  callable(string): mixed  $read
     */
    private static function matches(?string $name, ?string $value, callable $read): bool
    {
        if ($name === null || preg_match(self::NAME_PATTERN, $name) !== 1) {
            return false;
        }

        $actual = $read($name);

        return is_string($actual) && ($value === null || hash_equals($value, $actual));
    }

    private static function moment(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
