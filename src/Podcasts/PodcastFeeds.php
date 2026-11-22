<?php

namespace ScBudgetWeb\SiteBuilder\Podcasts;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

/**
 * Podcasts read from their RSS feed, for the Podcast block: the podcast's name, page and cover, and its newest
 * episodes with their audio, date, length, description and artwork.
 *
 * A feed is read at most once an hour (FRESH_MINUTES) and the last good copy is kept for a week, so a feed that's down
 * still shows. A copy past its hour is shown as it is and refreshed after the response has gone, so visitors never
 * wait for the podcast host; only a feed never read before is read while they wait (with a short timeout).
 *
 * Every request goes to an https address on the public internet: each address (and each redirect's) is resolved,
 * refused if private, reserved or local, and the connection pinned to it, so a feed link can't reach the server's own
 * network. Feeds over MAX_BYTES are refused, and the XML is read without network access or external entities.
 */
class PodcastFeeds
{
    public const FRESH_MINUTES = 60;

    public const KEEP_DAYS = 7;

    /** After a failed refresh, how long before trying again. */
    public const RETRY_MINUTES = 10;

    public const MAX_EPISODES = 100;

    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_COVER_BYTES = 2 * 1024 * 1024;

    public const TIMEOUT_SECONDS = 5;

    private const MAX_REDIRECTS = 3;

    private const ITUNES = 'http://www.itunes.com/dtds/podcast-1.0.dtd';

    /** An Audioboom podcast's page, whose feed is the same address with .rss. */
    private const AUDIOBOOM_PAGE = '~^https?://(?:www\.)?audioboom\.com/channels/([0-9]{1,12})(?:[-/?#].*)?$~';

    /** @var array<string, array<string, mixed>|null> this request's podcasts */
    private array $read = [];

    /** The last download's Content-Type. */
    private string $lastType = '';

    /** @var (Closure(string): array<int, string>)|null looks a host's addresses up in place of DNS (tests) */
    private static ?Closure $resolver = null;

    /**
     * Looks hosts up with $resolver instead of DNS (null puts DNS back), for tests.
     *
     * @param  (Closure(string): array<int, string>)|null  $resolver
     */
    public static function resolveHostsUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * The feed address for a link: an Audioboom podcast page becomes its feed, anything else is used as it is.
     * Null for anything that isn't an https (or http, upgraded) address.
     */
    public static function feedUrl(mixed $link): ?string
    {
        $link = is_string($link) ? trim($link) : '';

        if (preg_match(self::AUDIOBOOM_PAGE, $link, $matches)) {
            return 'https://audioboom.com/channels/'.$matches[1].'.rss';
        }

        $link = (string) preg_replace('~^http://~i', 'https://', $link);

        return filter_var($link, FILTER_VALIDATE_URL) && str_starts_with(strtolower($link), 'https://') && strlen($link) <= 500 ? $link : null;
    }

    /**
     * The podcast for a feed link, from the cache (refreshed after the response when it's over an hour old), or read
     * now when it's never been read. Null when it can't be read.
     *
     * @return array{title: string, link: ?string, coverUrl: ?string, episodes: array<int, array<string, mixed>>}|null
     */
    public function podcast(mixed $link): ?array
    {
        $url = self::feedUrl($link);

        if ($url === null) {
            return null;
        }

        if (array_key_exists($url, $this->read)) {
            return $this->read[$url];
        }

        $stored = Cache::get($this->key($url));

        if (! is_array($stored) || ! is_array($stored['podcast'] ?? null)) {
            return $this->read[$url] = $this->refresh($url);
        }

        if ($this->isDue($stored)) {
            app()->terminating(fn () => $this->refresh($url));
        }

        return $this->read[$url] = $stored['podcast'];
    }

    /**
     * Reads the feed now (the block's link field checks it this way), keeping it for the page. Null, with the reason
     * logged, when it can't be read or isn't a podcast.
     *
     * @return array{title: string, link: ?string, coverUrl: ?string, episodes: array<int, array<string, mixed>>}|null
     */
    public function refresh(string $url): ?array
    {
        $key = $this->key($url);
        $lock = Cache::lock($key.':refresh', self::TIMEOUT_SECONDS * 4);

        // Someone else is reading it: use what there is.
        if (! $lock->get()) {
            $stored = Cache::get($key);

            return is_array($stored) && is_array($stored['podcast'] ?? null) ? $stored['podcast'] : null;
        }

        try {
            $stored = Cache::get($key);
            $previous = is_array($stored) && is_array($stored['podcast'] ?? null) ? $stored['podcast'] : null;
            $podcast = $this->parse($this->download($url, self::MAX_BYTES, ['application/rss+xml', 'application/xml', 'text/xml', 'application/atom+xml', 'text/plain', 'application/octet-stream']));

            if ($podcast === null) {
                throw new \RuntimeException('Not a podcast feed.');
            }

            $podcast['coverUrl'] = $this->storeCover($url, $podcast['coverUrl'], $previous['coverUrl'] ?? null);
            Cache::put($key, ['podcast' => $podcast, 'readAt' => now()->getTimestamp()], now()->addDays(self::KEEP_DAYS));

            return $podcast;
        } catch (Throwable $exception) {
            Log::warning('Site builder: a podcast feed couldn\'t be read.', ['feed' => $url, 'reason' => $exception->getMessage()]);

            if (isset($previous)) {
                Cache::put($key, ['podcast' => $previous, 'readAt' => $stored['readAt'] ?? 0, 'failedAt' => now()->getTimestamp()], now()->addDays(self::KEEP_DAYS));
            }

            return $previous ?? null;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function isDue(array $stored): bool
    {
        $now = now()->getTimestamp();

        return (int) ($stored['readAt'] ?? 0) < $now - self::FRESH_MINUTES * 60
            && (int) ($stored['failedAt'] ?? 0) < $now - self::RETRY_MINUTES * 60;
    }

    /**
     * The podcast in a feed's XML, or null when it isn't an RSS podcast feed with playable episodes.
     *
     * @return array{title: string, link: ?string, coverUrl: ?string, episodes: array<int, array<string, mixed>>}|null
     */
    public function parse(string $xml): ?array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $feed instanceof SimpleXMLElement || ! isset($feed->channel)) {
            return null;
        }

        $channel = $feed->channel;
        $episodes = [];

        foreach ($channel->item as $item) {
            $episode = $this->episode($item);

            if ($episode !== null) {
                $episodes[] = $episode;
            }
        }

        if ($episodes === []) {
            return null;
        }

        // Newest first, whatever order the feed lists them in.
        usort($episodes, fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));

        $itunes = $channel->children(self::ITUNES);

        return [
            'title' => self::text((string) $channel->title, 200) ?: __('Podcast'),
            'link' => self::webLink((string) $channel->link),
            'coverUrl' => self::webLink((string) ($itunes->image?->attributes()['href'] ?? '')) ?? self::webLink((string) ($channel->image->url ?? '')),
            'episodes' => array_slice($episodes, 0, self::MAX_EPISODES),
        ];
    }

    /**
     * @return array{title: string, link: ?string, audioUrl: string, date: ?string, seconds: ?int, description: string, imageUrl: ?string}|null
     */
    private function episode(SimpleXMLElement $item): ?array
    {
        $enclosure = $item->enclosure?->attributes();
        $audioUrl = self::webLink((string) ($enclosure['url'] ?? ''), httpsOnly: true);
        $type = strtolower((string) ($enclosure['type'] ?? ''));

        // Only audio this site can play: an https file of an audio type (or one with no type that ends like audio).
        if ($audioUrl === null || ! (str_starts_with($type, 'audio/') || ($type === '' && preg_match('~\.(mp3|m4a|aac|ogg|oga|opus|wav)(\?|$)~i', $audioUrl)))) {
            return null;
        }

        $itunes = $item->children(self::ITUNES);
        $title = self::text((string) $item->title, 300);

        try {
            $date = filled((string) $item->pubDate) ? Carbon::parse((string) $item->pubDate)->toIso8601String() : null;
        } catch (Throwable) {
            $date = null;
        }

        return [
            'title' => $title !== '' ? $title : __('Episode'),
            'link' => self::webLink((string) $item->link),
            'audioUrl' => $audioUrl,
            'date' => $date,
            'seconds' => self::seconds((string) $itunes->duration),
            'description' => self::text((string) ($itunes->summary ?? '') ?: (string) $item->description, 600),
            'imageUrl' => self::webLink((string) ($itunes->image?->attributes()['href'] ?? ''), httpsOnly: true),
        ];
    }

    /**
     * An episode's length in seconds, from "1:02:03", "45:10" or "2710".
     */
    public static function seconds(string $duration): ?int
    {
        $duration = trim($duration);

        if (! preg_match('/^\d+(?::\d{1,2}){0,2}$/', $duration)) {
            return null;
        }

        $seconds = 0;

        foreach (explode(':', $duration) as $part) {
            $seconds = $seconds * 60 + (int) $part;
        }

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * Plain text: tags removed, entities decoded, spaces tidied, at most $limit characters.
     */
    private static function text(string $html, int $limit): string
    {
        $text = html_entity_decode(strip_tags((string) preg_replace('~<(br|/p|/div|/li)\b[^>]*>~i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), $limit);
    }

    /**
     * A link visitors can follow: http(s) only (https only for what the page loads).
     */
    private static function webLink(string $url, bool $httpsOnly = false): ?string
    {
        $url = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return filter_var($url, FILTER_VALIDATE_URL) && ($scheme === 'https' || (! $httpsOnly && $scheme === 'http')) && strlen($url) <= 2000 ? $url : null;
    }

    /**
     * The podcast's cover, kept on the site's own storage so visitors' browsers don't ask the host for it. Read again
     * only when its address changes; the host's address when it can't be kept, and none when it isn't https.
     */
    private function storeCover(string $feedUrl, ?string $coverUrl, ?string $previousCoverUrl): ?string
    {
        if ($coverUrl === null || ! str_starts_with($coverUrl, 'https://')) {
            return null;
        }

        $disk = Storage::disk(config('site-builder.uploads.disk', 'public'));
        $base = trim((string) config('site-builder.uploads.directory', 'site-builder'), '/').'/podcasts/'.sha1($feedUrl.'|'.$coverUrl);

        foreach (['jpg', 'png', 'webp', 'gif'] as $extension) {
            if ($disk->exists("{$base}.{$extension}")) {
                return $disk->url("{$base}.{$extension}");
            }
        }

        try {
            $response = $this->download($coverUrl, self::MAX_COVER_BYTES, ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][self::mime($this->lastType)] ?? 'jpg';

            if (@getimagesizefromstring($response) === false) {
                return $previousCoverUrl;
            }

            $disk->put("{$base}.{$extension}", $response, 'public');

            return $disk->url("{$base}.{$extension}");
        } catch (Throwable) {
            return $previousCoverUrl;
        }
    }

    /**
     * Downloads an https address on the public internet (following up to MAX_REDIRECTS, each checked the same way),
     * refusing anything over $maxBytes or of another type.
     *
     * @param  array<int, string>  $types
     */
    private function download(string $url, int $maxBytes, array $types): string
    {
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $response = $this->request($url, $maxBytes);

            if ($response->redirect()) {
                $url = self::resolveRedirect($url, (string) $response->header('Location'));

                continue;
            }

            if (! $response->successful()) {
                throw new \RuntimeException("The host answered {$response->status()}.");
            }

            $this->lastType = $response->header('Content-Type');
            $type = self::mime($this->lastType);

            if ($type !== '' && ! in_array($type, $types, true)) {
                throw new \RuntimeException("Unexpected content type {$type}.");
            }

            $body = $response->body();

            if (strlen($body) > $maxBytes) {
                throw new \RuntimeException('Too large.');
            }

            return $body;
        }

        throw new \RuntimeException('Too many redirects.');
    }

    /**
     * @throws ConnectionException
     */
    private function request(string $url, int $maxBytes): Response
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new \RuntimeException('Only https addresses on the standard port are read.');
        }

        $address = self::publicAddress($host);

        return Http::withUserAgent('SiteBuilder-Podcast/1.0')
            ->accept('application/rss+xml, application/xml;q=0.9, */*;q=0.5')
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::TIMEOUT_SECONDS)
            ->withOptions([
                'allow_redirects' => false,
                // Connect to the address that was checked, not one a second DNS lookup might give.
                'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$address}"]],
                'progress' => function (int $total, int $downloaded) use ($maxBytes): void {
                    if ($total > $maxBytes || $downloaded > $maxBytes) {
                        throw new \RuntimeException('Too large.');
                    }
                },
            ])
            ->get($url);
    }

    /**
     * The host's address when every address it has is on the public internet.
     */
    private static function publicAddress(string $host): string
    {
        $host = trim($host, '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (self::$resolver !== null ? (self::$resolver)($host) : array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        ));

        if ($addresses === []) {
            throw new \RuntimeException('The host can\'t be found.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \RuntimeException('The host is on a private network.');
            }
        }

        return str_contains($addresses[0], ':') ? "[{$addresses[0]}]" : $addresses[0];
    }

    private static function resolveRedirect(string $from, string $location): string
    {
        if ($location === '') {
            throw new \RuntimeException('A redirect without an address.');
        }

        if (preg_match('~^https?://~i', $location)) {
            return $location;
        }

        $parts = parse_url($from);

        return 'https://'.($parts['host'] ?? '').(str_starts_with($location, '/') ? $location : '/'.$location);
    }

    private static function mime(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType)[0]));
    }

    private function key(string $url): string
    {
        return 'site-builder:podcast:'.sha1($url);
    }
}
