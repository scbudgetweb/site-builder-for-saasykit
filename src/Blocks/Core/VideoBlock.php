<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * A YouTube, Vimeo or Adilo video. Video players set their own (marketing) cookies, so a visitor who
 * hasn't allowed marketing cookies sees a click-to-play poster first: nothing is requested from the
 * provider until they press play (GDPR). With marketing cookies allowed (or the cookie banner off),
 * the player shows straight away. The banner reloads the page when choices change.
 */
class VideoBlock extends PageBlock
{
    private const YOUTUBE_PATTERN = '~^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

    private const VIMEO_PATTERN = '~^(?:https?://)?(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d+)~';

    /** Adilo's embed code puts the share link (…/watch/{id}) straight in the player's iframe. */
    private const ADILO_PATTERN = '~^(?:https?://)?adilo\.bigcommand\.com/watch/([A-Za-z0-9_-]{4,40})(?:[/?#]|$)~';

    public static function name(): string
    {
        return 'video';
    }

    public static function label(): string
    {
        return __('Video');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedPlayCircle;
    }

    public static function defaultWidth(): string
    {
        return 'narrow';
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            TextInput::make('url')
                ->label(__('Video link'))
                ->helperText(__('A YouTube, Vimeo or Adilo link, e.g. https://adilo.bigcommand.com/watch/…'))
                ->required()
                ->maxLength(500)
                ->placeholder('https://www.youtube.com/watch?v=...')
                ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                    if (static::embed($value) === null) {
                        $fail(__('Paste a YouTube, Vimeo or Adilo video link.'));
                    }
                }),
            TextInput::make('title')
                ->label(__('Video title'))
                ->helperText(__('Read out by screen readers, and shown on the play button.'))
                ->required()
                ->maxLength(150),
            static::imageUpload('poster', __('Cover image (optional)'))
                ->helperText(__('Shown before the video plays. Without one, a plain panel in the site colour is shown.')),
        ];
    }

    /**
     * embedUrl starts playing (after the visitor pressed our play button); playerUrl waits for them.
     *
     * @return array{provider: string, id: string, embedUrl: string, playerUrl: string}|null
     */
    public static function embed(mixed $url): ?array
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        if (preg_match(self::YOUTUBE_PATTERN, $url, $matches)) {
            return [
                'provider' => 'YouTube',
                'id' => $matches[1],
                'embedUrl' => 'https://www.youtube-nocookie.com/embed/'.$matches[1].'?autoplay=1&rel=0',
                'playerUrl' => 'https://www.youtube-nocookie.com/embed/'.$matches[1].'?rel=0',
            ];
        }

        if (preg_match(self::VIMEO_PATTERN, $url, $matches)) {
            return [
                'provider' => 'Vimeo',
                'id' => $matches[1],
                'embedUrl' => 'https://player.vimeo.com/video/'.$matches[1].'?autoplay=1&dnt=1',
                'playerUrl' => 'https://player.vimeo.com/video/'.$matches[1].'?dnt=1',
            ];
        }

        // Adilo's own autoplay setting (in the Adilo account) decides whether it starts straight away.
        if (preg_match(self::ADILO_PATTERN, $url, $matches)) {
            return [
                'provider' => 'Adilo',
                'id' => $matches[1],
                'embedUrl' => 'https://adilo.bigcommand.com/watch/'.$matches[1],
                'playerUrl' => 'https://adilo.bigcommand.com/watch/'.$matches[1],
            ];
        }

        return null;
    }

    /**
     * The player depends on the visitor's cookie choices, so a page with a video isn't cached.
     */
    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'embed' => static::embed($data['url'] ?? null),
            'posterUrl' => static::imageUrl($data['poster'] ?? null),
            // Click-to-play unless the app's cookie banner says marketing cookies are allowed (lesson 14).
            'showsPlayerStraightAway' => app(SiteBuilder::class)->consentAllows('marketing'),
        ];
    }
}
