<?php

namespace ScBudgetWeb\SiteBuilder\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The icons admins can pick for blocks and buttons: Boxicons 2.1.4 (MIT, the package's resources/icons/boxicons),
 * shipped in the package and drawn inline, so a page never loads icons from another server (GDPR, lesson 14).
 * Nothing is published: the SVGs are read from the package folder.
 *
 * A stored icon is "bx:{style}/{name}", e.g. "bx:regular/bx-calendar". Only files from the icon folder can be
 * output: the style and name are checked against a fixed pattern and the file must exist.
 */
class IconLibrary
{
    /** @var array<string, string> style folder => label */
    public const STYLES = [
        'regular' => 'Outline',
        'solid' => 'Filled',
        'logos' => 'Brand logo',
    ];

    /**
     * Shown before the admin starts typing: everyday icons for a business website.
     */
    public const POPULAR = [
        'bx:regular/bx-calendar-check', 'bx:regular/bx-home', 'bx:regular/bx-map', 'bx:regular/bx-buildings',
        'bx:regular/bx-briefcase', 'bx:regular/bx-search', 'bx:regular/bx-wifi', 'bx:regular/bx-car',
        'bx:regular/bx-group', 'bx:regular/bx-user-plus', 'bx:solid/bxs-graduation', 'bx:regular/bx-trophy',
        'bx:regular/bx-medal', 'bx:regular/bx-star', 'bx:regular/bx-heart', 'bx:regular/bx-rocket',
        'bx:regular/bx-bulb', 'bx:regular/bx-bar-chart-alt-2', 'bx:regular/bx-pound', 'bx:regular/bx-time',
        'bx:regular/bx-calendar', 'bx:regular/bx-badge-check', 'bx:regular/bx-check-shield', 'bx:regular/bx-bell',
        'bx:regular/bx-envelope', 'bx:regular/bx-chat', 'bx:regular/bx-file', 'bx:regular/bx-globe',
        'bx:regular/bx-like', 'bx:regular/bx-bolt-circle', 'bx:regular/bx-cog', 'bx:regular/bx-mobile',
        'bx:regular/bx-happy', 'bx:regular/bx-target-lock',
    ];

    private const MAX_SEARCH_RESULTS = 100;

    /** The first, unselectable line of the picker before a search, saying there's more than the popular icons. */
    public const SEARCH_HINT = '__search_hint';

    /**
     * Everyday words => the words used in icon names, so "money" finds the pound, dollar and wallet icons.
     * Icon names are already searchable as they are; these only add what the names don't say.
     */
    private const SEARCH_SYNONYMS = [
        'money' => ['money', 'pound', 'dollar', 'euro', 'wallet', 'coin', 'credit-card', 'bank'],
        'salary' => ['money', 'pound', 'dollar', 'euro', 'wallet', 'coin'],
        'pay' => ['money', 'pound', 'dollar', 'euro', 'wallet', 'coin', 'credit-card'],
        'job' => ['briefcase', 'id-card', 'building'],
        'work' => ['briefcase', 'id-card', 'building'],
        'career' => ['briefcase', 'trending', 'line-chart'],
        'person' => ['user', 'face', 'male', 'female'],
        'people' => ['group', 'user'],
        'team' => ['group', 'user'],
        'clock' => ['time', 'alarm', 'stopwatch'],
        'document' => ['file', 'clipboard', 'detail'],
        'cv' => ['file', 'id-card', 'detail'],
        'growth' => ['trending', 'line-chart', 'bar-chart', 'up-arrow'],
        'thumbs' => ['like', 'dislike'],
        'sport' => ['football', 'basketball', 'baseball', 'tennis', 'run', 'cycling', 'swim', 'trophy', 'medal', 'dumbbell'],
        'award' => ['trophy', 'medal', 'award', 'crown', 'certification', 'badge'],
        'education' => ['graduation', 'book', 'school', 'certification'],
        'training' => ['graduation', 'book', 'school', 'certification', 'chalkboard'],
        'learn' => ['graduation', 'book', 'school', 'brain'],
        'location' => ['map', 'pin', 'location', 'navigation', 'current-location'],
        'address' => ['map', 'pin', 'location', 'home', 'building'],
        'contact' => ['envelope', 'mail', 'phone', 'message', 'chat'],
        'email' => ['envelope', 'mail'],
        'travel' => ['plane', 'car', 'train', 'bus', 'map', 'hotel', 'trip'],
        'holiday' => ['plane', 'hotel', 'bed', 'sun', 'beach', 'map'],
        'outdoors' => ['tree', 'leaf', 'sun', 'cloud', 'water'],
        'idea' => ['bulb', 'brain'],
        'settings' => ['cog', 'wrench', 'slider'],
        'security' => ['shield', 'lock', 'key'],
        'safe' => ['shield', 'lock'],
        'help' => ['help', 'support', 'question'],
        'food' => ['restaurant', 'food', 'coffee', 'drink', 'dish'],
        'health' => ['plus-medical', 'first-aid', 'health', 'heart', 'clinic'],
        'favourite' => ['star', 'heart', 'bookmark'],
        'success' => ['check', 'trophy', 'medal', 'badge-check'],
        'fast' => ['bolt', 'rocket', 'timer'],
        'social' => ['share', 'like', 'group', 'chat'],
        'booking' => ['calendar', 'calendar-check', 'calendar-event', 'time'],
        'event' => ['calendar-event', 'party', 'music', 'drink'],
        'venue' => ['building', 'buildings', 'home', 'store'],
        'room' => ['door-open', 'home', 'building', 'chair'],
        'parking' => ['car', 'bus', 'cycling'],
        'accessible' => ['handicap', 'walk'],
        'kitchen' => ['restaurant', 'coffee', 'dish', 'fridge'],
    ];

    /** @var array<string, string> */
    private static array $svgCache = [];

    /**
     * The icon as inline SVG in the current text colour, or an empty string for no / an unknown icon.
     */
    public static function render(?string $value, string $class = 'h-6 w-6'): HtmlString
    {
        if (blank($value)) {
            return new HtmlString('');
        }

        $path = self::path($value);

        if ($path === null) {
            return new HtmlString('');
        }

        $svg = self::$svgCache[$path] ??= (string) file_get_contents($path);

        // The files are plain Boxicons SVGs (vetted when added): drop the fixed size, take the text colour.
        return new HtmlString(preg_replace(
            '/<svg\b[^>]*?viewBox="([^"]*)"[^>]*>/',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="$1" class="'.e($class).'" fill="currentColor" aria-hidden="true" focusable="false">',
            $svg,
            1,
        ) ?? '');
    }

    public static function isValid(?string $value): bool
    {
        return $value === null || $value === '' || self::path($value) !== null;
    }

    /**
     * Icons whose name matches the search, as value => option HTML (icon and name), for the admin picker.
     *
     * @return array<string, string>
     */
    public static function search(string $search): array
    {
        $terms = array_filter(explode(' ', Str::of($search)->lower()->replace(['-', '_'], ' ')->squish()->toString()));

        // Every term must match the icon's label or file name, directly or through an everyday-word synonym.
        $matches = collect(self::all())
            ->filter(function (string $label, string $value) use ($terms): bool {
                $label = Str::lower($label);
                $name = '-'.Str::after($value, '/').'-';

                // What's typed matches part of a word ("trop" finds trophy); synonyms match whole words in the
                // file name, so "car" (for travel) doesn't also find card and caret.
                return collect($terms)->every(fn (string $term): bool => str_contains($label, $term)
                    || collect(self::SEARCH_SYNONYMS[$term] ?? [])->contains(fn (string $word): bool => str_contains($name, '-'.$word.'-')));
            })
            ->take(self::MAX_SEARCH_RESULTS)
            ->keys()
            ->all();

        return self::options($matches);
    }

    /**
     * @param  array<string>  $values
     * @return array<string, string> value => option HTML
     */
    public static function options(array $values): array
    {
        return collect($values)
            ->filter(fn (string $value): bool => self::isValid($value))
            ->mapWithKeys(fn (string $value): array => [$value => self::optionHtml($value)])
            ->all();
    }

    /**
     * The picker's list before a search: a line saying how to find the rest, then the popular icons.
     *
     * @return array<string, string>
     */
    public static function initialOptions(): array
    {
        $hint = '<span style="font-size:0.8rem;opacity:0.7">'.e(__('Showing :shown popular icons. Type to search all :total (e.g. money, map, trophy, "filled star" or "logo linkedin").', [
            'shown' => count(self::POPULAR),
            'total' => number_format(count(self::all())),
        ])).'</span>';

        return [self::SEARCH_HINT => $hint, ...self::options(self::POPULAR)];
    }

    /**
     * An icon and its name, for the picker's options and its selected value.
     */
    public static function optionHtml(?string $value): ?string
    {
        if (blank($value) || ! self::isValid($value)) {
            return null;
        }

        return '<span style="display:inline-flex;align-items:center;gap:0.5rem">'
            .self::render($value, 'h-5 w-5 shrink-0')->toHtml()
            .'<span>'.e(self::label($value)).'</span></span>';
    }

    /**
     * A readable name, e.g. "Briefcase (Outline)".
     */
    public static function label(string $value): string
    {
        return self::all()[$value] ?? $value;
    }

    /**
     * Every icon as value => label, read from the icon folder once per request.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        static $icons = null;

        if ($icons !== null) {
            return $icons;
        }

        $icons = [];

        foreach (self::STYLES as $style => $styleLabel) {
            foreach (glob(self::directory()."/{$style}/*.svg") ?: [] as $file) {
                $name = basename($file, '.svg');
                $readable = Str::of($name)->after('-')->replace('-', ' ')->ucfirst()->toString();
                $icons["bx:{$style}/{$name}"] = $readable.' ('.__($styleLabel).')';
            }
        }

        return $icons;
    }

    public static function directory(): string
    {
        return dirname(__DIR__, 2).'/resources/icons/boxicons';
    }

    private static function path(string $value): ?string
    {
        if (preg_match('#^bx:(regular|solid|logos)/(bx[sl]?-[a-z0-9-]+)$#', $value, $match) !== 1) {
            return null;
        }

        $path = self::directory()."/{$match[1]}/{$match[2]}.svg";

        return is_file($path) ? $path : null;
    }
}
