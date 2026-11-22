<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use ScBudgetWeb\SiteBuilder\Blocks\CardStyle;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Podcasts\PodcastFeeds;

/**
 * A podcast's episodes, from its RSS feed (any host; an Audioboom podcast page works too), drawn in the site's own
 * styles: a list or a grid of cards, each with a play button, title, date, length and description, played by one
 * shared player on the page (preload="none": nothing downloads until play is pressed). Optionally the podcast's cover
 * and name above them (links to the podcast host only if turned on), and Show more for older episodes.
 *
 * The feed is read at most once an hour (PodcastFeeds), and the block is drawn on each request from that copy, so
 * the page around it stays cached and new episodes show within the hour. There's no frame from the podcast host: the
 * audio only comes from it once play is pressed, and the cover is kept on the site.
 */
class PodcastBlock extends PageBlock
{
    public const MAX_EPISODES = 50;

    /** What each episode can show besides its title and play button. */
    public const PARTS = ['date' => 'Date', 'length' => 'Length', 'description' => 'Description', 'artwork' => 'Episode artwork'];

    public const ORDERS = ['newest' => 'Newest first', 'oldest' => 'Oldest first'];

    public static function name(): string
    {
        return 'podcast';
    }

    public static function label(): string
    {
        return __('Podcast');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedMicrophone;
    }

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(__('Episodes'))
            ->icon(__('Play button'))
            ->text('title', __('Title text'))
            ->text('meta', __('Date and length'))
            ->text('text', __('Description text'));
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            TextInput::make('feed_url')
                ->label(__('Podcast feed'))
                ->helperText(__('The podcast\'s RSS feed, from any host, or its Audioboom page (audioboom.com/channels/…). New episodes show within the hour.'))
                ->placeholder('https://audioboom.com/channels/…')
                ->required()
                ->maxLength(500)
                ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                    $url = PodcastFeeds::feedUrl($value);

                    if ($url === null) {
                        $fail(__('Paste the podcast\'s feed address (https://…) or its Audioboom page.'));
                    } elseif (app(PodcastFeeds::class)->refresh($url) === null) {
                        $fail(__('No podcast could be read from that address. Check it\'s the RSS feed and that it lists episodes with audio.'));
                    }
                }),
            Grid::make(['default' => 1, 'sm' => 3])->schema([
                TextInput::make('count')
                    ->label(__('Episodes shown'))
                    ->hintIcon(self::HELP_ICON, __('Show more adds as many again, up to the newest :count.', ['count' => PodcastFeeds::MAX_EPISODES]))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(self::MAX_EPISODES)
                    ->default(10),
                Select::make('order')
                    ->label(__('Order'))
                    ->options(array_map(fn (string $label): string => __($label), self::ORDERS))
                    ->default('newest')
                    ->selectablePlaceholder(false),
                Toggle::make('show_more')
                    ->label(__('Show more button'))
                    ->default(true)
                    ->inline(false),
            ]),
            CheckboxList::make('show')
                ->label(__('Each episode shows'))
                ->hintIcon(self::HELP_ICON, __('Its title and play button, and any of these. With artwork, the image is on the left and the play button on the right; an episode without its own image shows the podcast\'s cover. Episode images load from the podcast host.'))
                ->options(array_map(fn (string $label): string => __($label), self::PARTS))
                ->default(['date', 'length', 'description', 'artwork'])
                ->in(array_keys(self::PARTS))
                ->columns(2)
                ->live(),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Select::make('description_lines')
                    ->label(__('Description lines'))
                    ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                    ->default(2)
                    ->selectablePlaceholder(false)
                    ->visible(fn (Get $get): bool => in_array('description', (array) $get('show'), true)),
                Toggle::make('show_header')
                    ->label(__('Show the podcast\'s cover and name'))
                    ->helperText(__('Above the episodes.'))
                    ->default(true),
                Toggle::make('link_to_host')
                    ->label(__('Link to the podcast host'))
                    ->helperText(__('The podcast\'s name (All episodes) and each episode\'s title link to their pages on the podcast host, in a new tab. Off, everything plays here and visitors stay on your site.'))
                    ->default(false),
            ]),
            static::whenEmptyFields(),
        ];
    }

    protected static function styleToggleTitle(): string
    {
        return __('Episode layout');
    }

    protected static function styleFields(): array
    {
        return [
            Group::make()
                ->statePath('card')
                ->columns(2)
                ->schema([
                    Toggle::make('background')
                        ->label(__('Episode background'))
                        ->hintIcon(self::HELP_ICON, __('A background and border make each episode a card. Without them, episodes are rows with a line between. Their colours, spacing and text, and the play button\'s shape and colours, are in Episodes below.'))
                        ->default(false),
                    Toggle::make('border')->label(__('Episode border'))->default(false),
                ]),
        ];
    }

    protected static function layoutFields(): array
    {
        return [Grid::make(2)->schema([static::columnsSelect(1, [1, 2, 3])])];
    }

    /** New episodes arrive without the page being saved, so the block is drawn on each request (from the hour's copy). */
    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function pullsContent(): bool
    {
        return true;
    }

    public static function isEmpty(array $data, array $viewData): bool
    {
        return ($viewData['episodes'] ?? []) === [];
    }

    public static function viewData(array $data, Page $page): array
    {
        $podcast = app(PodcastFeeds::class)->podcast($data['feed_url'] ?? null);
        $show = is_array($data['show'] ?? null) ? $data['show'] : ['date', 'length', 'description', 'artwork'];
        $count = is_numeric($data['count'] ?? null) ? max(1, min(self::MAX_EPISODES, (int) $data['count'])) : 10;
        $episodes = $podcast['episodes'] ?? [];

        if (static::pick($data['order'] ?? null, self::ORDERS, 'newest') === 'oldest') {
            $episodes = array_reverse($episodes);
        }

        // Without Show more only the first batch is sent; with it, the rest are in the page, hidden until asked for.
        $episodes = array_slice($episodes, 0, (bool) ($data['show_more'] ?? true) ? PodcastFeeds::MAX_EPISODES : $count);
        $linkToHost = (bool) ($data['link_to_host'] ?? false);
        $style = CardStyle::resolve(['icon_position' => 'left', 'background' => false, 'border' => false, ...(is_array($data['card'] ?? null) ? $data['card'] : [])], $data['design']['items'] ?? null);

        return [
            'podcast' => $podcast === null || ! (bool) ($data['show_header'] ?? true) ? null : [
                'title' => $podcast['title'],
                'link' => $linkToHost ? $podcast['link'] : null,
                'coverUrl' => $podcast['coverUrl'],
            ],
            'episodes' => array_map(fn (array $episode): array => [
                'title' => (string) $episode['title'],
                'link' => $linkToHost ? ($episode['link'] ?? null) : null,
                'audioUrl' => (string) $episode['audioUrl'],
                'date' => in_array('date', $show, true) && is_string($episode['date'] ?? null) ? Carbon::parse($episode['date'])->translatedFormat('j M Y') : '',
                'datetime' => is_string($episode['date'] ?? null) ? Carbon::parse($episode['date'])->toDateString() : null,
                'length' => in_array('length', $show, true) ? self::length($episode['seconds'] ?? null) : '',
                'seconds' => is_int($episode['seconds'] ?? null) ? $episode['seconds'] : null,
                'description' => in_array('description', $show, true) ? (string) ($episode['description'] ?? '') : '',
                'imageUrl' => in_array('artwork', $show, true) ? ($episode['imageUrl'] ?? $podcast['coverUrl'] ?? null) : null,
            ], $episodes),
            'withArtwork' => in_array('artwork', $show, true),
            'count' => $count,
            'columns' => static::columns($data, 1, [1, 2, 3]),
            'lines' => is_numeric($data['description_lines'] ?? null) ? max(1, min(5, (int) $data['description_lines'])) : 2,
            'style' => $style,
            // The card's box only: the episode lays out its own row.
            'cardClass' => trim(implode(' ', array_diff(explode(' ', $style['card']), ['flex', 'flex-col', 'flex-row', 'items-start', 'items-center', 'text-center', 'gap-4', 'p-6'])).' p-5'),
        ];
    }

    /**
     * "45 min", "1 hr 2 min", or "" without a length.
     */
    public static function length(mixed $seconds): string
    {
        if (! is_int($seconds) || $seconds <= 0) {
            return '';
        }

        $minutes = max(1, (int) round($seconds / 60));

        return $minutes < 60
            ? __(':minutes min', ['minutes' => $minutes])
            : __(':hours hr :minutes min', ['hours' => intdiv($minutes, 60), 'minutes' => $minutes % 60]);
    }

    protected static function itemLabel(array $state): string
    {
        return filled($state['heading'] ?? null) && is_string($state['heading']) ? static::label().': '.$state['heading'] : static::label();
    }
}
