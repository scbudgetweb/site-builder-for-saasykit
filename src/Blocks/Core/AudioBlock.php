<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * An uploaded sound file: a card with an optional cover image, the title, artist and a short
 * description, and the browser's own player, which loads nothing until it's played (preload="none"). The file is in
 * the media library, and can be chosen from it (PageBlock::audioUpload()). Design › Items styles the card. The types
 * and largest size taken are set in Website › Media › Audio (AudioUploads). Or SoundCloud's or Spotify's own player
 * for a link, with no key, shown after a click until marketing cookies are allowed, as Video's.
 */
class AudioBlock extends PageBlock
{
    /** Where it plays from: label and icon. */
    public const SOURCES = [
        'upload' => ['Upload a file', Heroicon::OutlinedArrowUpTray],
        'soundcloud' => ['SoundCloud', Heroicon::OutlinedCloud],
        'spotify' => ['Spotify', Heroicon::OutlinedMusicalNote],
    ];

    /** A track or playlist page on soundcloud.com: user/track, or user/sets/playlist (no query or extra parts). */
    private const SOUNDCLOUD_PATTERN = '~^https?://(?:www\.|m\.)?soundcloud\.com/([A-Za-z0-9_-]+/(?:sets/)?[A-Za-z0-9_-]+)/?(?:[?#].*)?$~';

    /** An episode, show, track, album or playlist on open.spotify.com, with an optional intl-xx part, by its id. */
    private const SPOTIFY_PATTERN = '~^https?://open\.spotify\.com/(?:intl-[a-z]{2}(?:-[a-z]{2})?/)?(episode|show|track|album|playlist)/([A-Za-z0-9]{22})(?:[/?#].*)?$~';

    public static function name(): string
    {
        return 'audio';
    }

    public static function label(): string
    {
        return __('Audio');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedMusicalNote;
    }

    public static function defaultWidth(): string
    {
        return 'narrow';
    }

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(__('Player'))
            ->text('title', __('Title text'))
            ->text('meta', __('Artist text'))
            ->text('text', __('Description text'));
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            ToggleButtons::make('source')
                ->label(__('Where it plays from'))
                ->options(array_map(fn (array $source): string => __($source[0]), self::SOURCES))
                ->icons(array_map(fn (array $source): Heroicon => $source[1], self::SOURCES))
                ->default('upload')
                ->inline()
                ->live(),
            static::audioUpload('audio', __('Sound file'))
                ->required(fn (Get $get): bool => self::sourceOf($get('source')) === 'upload')
                ->visible(fn (Get $get): bool => self::sourceOf($get('source')) === 'upload'),
            TextInput::make('embed_url')
                ->label(fn (Get $get): string => self::sourceOf($get('source')) === 'spotify' ? __('Spotify link') : __('SoundCloud link'))
                ->helperText(fn (Get $get): string => self::sourceOf($get('source')) === 'spotify'
                    ? __('Share › Copy link on a Spotify episode, show, track, album or playlist. No Spotify account or key needed.')
                    : __('The address of a SoundCloud track or playlist, e.g. https://soundcloud.com/…/…. No SoundCloud account or key needed.'))
                ->placeholder(fn (Get $get): string => self::sourceOf($get('source')) === 'spotify' ? 'https://open.spotify.com/episode/…' : 'https://soundcloud.com/…')
                ->maxLength(500)
                ->required(fn (Get $get): bool => self::sourceOf($get('source')) !== 'upload')
                ->visible(fn (Get $get): bool => self::sourceOf($get('source')) !== 'upload')
                ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                    if (filled($value) && static::embed($get('source'), $value) === null) {
                        $fail(self::sourceOf($get('source')) === 'spotify'
                            ? __('Paste a Spotify link to an episode, show, track, album or playlist.')
                            : __('Paste a SoundCloud link to a track or playlist.'));
                    }
                }),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('title')
                    ->label(__('Title'))
                    ->helperText(__('Shown above the player, and read out by screen readers.'))
                    ->required()
                    ->maxLength(150),
                TextInput::make('artist')
                    ->label(__('Artist or speaker (optional)'))
                    ->maxLength(150)
                    ->visible(fn (Get $get): bool => self::sourceOf($get('source')) === 'upload'),
            ]),
            Textarea::make('description')
                ->label(__('Description (optional)'))
                ->rows(2)
                ->maxLength(500)
                ->visible(fn (Get $get): bool => self::sourceOf($get('source')) === 'upload'),
            static::imageUpload('cover', __('Cover image (optional)'))
                ->helperText(__('A square picture beside the title, such as album art.'))
                ->visible(fn (Get $get): bool => self::sourceOf($get('source')) === 'upload'),
            Toggle::make('download')
                ->label(__('Offer a download link'))
                ->default(false)
                ->visible(fn (Get $get): bool => self::sourceOf($get('source')) === 'upload'),
        ];
    }

    /**
     * SoundCloud's and Spotify's own players depend on the visitor's cookie choices, so a page with one isn't cached.
     */
    public static function isCacheable(array $data): bool
    {
        return self::sourceOf($data['source'] ?? null) === 'upload';
    }

    /**
     * The player for a SoundCloud or Spotify link: the provider, the embed's address (built here from the link's parts,
     * so nothing else can be slipped in) and its height. Null for anything else.
     *
     * @return array{provider: string, url: string, playUrl: string, height: int}|null
     */
    public static function embed(mixed $source, mixed $link): ?array
    {
        $link = is_string($link) ? trim($link) : '';

        if (self::sourceOf($source) === 'soundcloud' && preg_match(self::SOUNDCLOUD_PATTERN, $link, $matches)) {
            $page = 'https://soundcloud.com/'.$matches[1];
            $url = 'https://w.soundcloud.com/player/?'.http_build_query(['url' => $page, 'visual' => 'false', 'show_comments' => 'false', 'show_reposts' => 'false'], '', '&', PHP_QUERY_RFC3986);

            return ['provider' => 'SoundCloud', 'url' => $url, 'playUrl' => $url.'&auto_play=true', 'height' => str_contains($matches[1], '/sets/') ? 450 : 166];
        }

        if (self::sourceOf($source) === 'spotify' && preg_match(self::SPOTIFY_PATTERN, $link, $matches)) {
            $url = 'https://open.spotify.com/embed/'.$matches[1].'/'.$matches[2];

            return ['provider' => 'Spotify', 'url' => $url, 'playUrl' => $url, 'height' => in_array($matches[1], ['track', 'episode'], true) ? 152 : 352];
        }

        return null;
    }

    private static function sourceOf(mixed $source): string
    {
        return is_string($source) && isset(self::SOURCES[$source]) ? $source : 'upload';
    }

    public static function viewData(array $data, Page $page): array
    {
        $text = fn (string $key): string => is_string($data[$key] ?? null) ? trim($data[$key]) : '';
        $source = self::sourceOf($data['source'] ?? null);

        if ($source !== 'upload') {
            return [
                'embed' => static::embed($source, $data['embed_url'] ?? null),
                'audioUrl' => null,
                'title' => $text('title'),
                // Click-to-play unless the app's cookie banner says marketing cookies are allowed, as Video's.
                'showsPlayerStraightAway' => app(SiteBuilder::class)->consentAllows('marketing'),
            ];
        }

        return [
            'embed' => null,
            'audioUrl' => static::imageUrl($data['audio'] ?? null),
            'coverUrl' => static::imageUrl($data['cover'] ?? null),
            'title' => $text('title'),
            'artist' => $text('artist'),
            'description' => $text('description'),
            'offersDownload' => (bool) ($data['download'] ?? false),
        ];
    }
}
