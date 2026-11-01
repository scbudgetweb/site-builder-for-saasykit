<?php

namespace ScBudgetWeb\SiteBuilder\Settings;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * What Audio blocks take (Website › Media › Audio): the file types chosen, from a fixed list of plain sound formats
 * (nothing a browser could run as a page, since uploads are served from the site's own address), and the largest file.
 * The size chosen can't go past the server's own limit (PHP's upload_max_filesize and post_max_size), so the limit
 * used is the lower of the two. LivewireLimits raises Livewire's temporary upload limit to match.
 */
class AudioUploads
{
    /** Each type: its label, its file extensions and the names browsers and servers give it. */
    public const TYPES = [
        'mp3' => ['MP3', ['mp3'], ['audio/mpeg', 'audio/mp3', 'audio/mpeg3', 'audio/x-mpeg-3']],
        'm4a' => ['M4A / AAC', ['m4a', 'aac'], ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'audio/aac', 'audio/x-aac', 'audio/aacp']],
        'ogg' => ['OGG / Opus', ['ogg', 'oga', 'opus'], ['audio/ogg', 'application/ogg', 'audio/opus', 'audio/x-ogg']],
        'wav' => ['WAV', ['wav'], ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave']],
        'flac' => ['FLAC', ['flac'], ['audio/flac', 'audio/x-flac']],
        'webm' => ['WebM audio', ['webm'], ['audio/webm', 'video/webm']],
    ];

    /** The types taken until an admin chooses: the four every current browser plays. */
    public const DEFAULT_TYPES = ['mp3', 'm4a', 'ogg', 'wav'];

    /** The largest file until an admin chooses, in MB (enough for an hour's talk at 128 kbps). */
    public const DEFAULT_MEGABYTES = 100;

    /** The largest an admin can choose, in MB. */
    public const MAX_MEGABYTES = 2048;

    public const TYPES_SETTING = 'media.audio_types';

    public const MEGABYTES_SETTING = 'media.audio_max_megabytes';

    public function __construct(private Settings $settings) {}

    /**
     * The types chosen (keys of TYPES), in TYPES' order. Never empty: with none saved, the standard ones.
     *
     * @return array<int, string>
     */
    public function types(): array
    {
        $chosen = $this->settings->get(self::TYPES_SETTING);
        $types = is_array($chosen) ? array_values(array_intersect(array_keys(self::TYPES), $chosen)) : [];

        return $types === [] ? self::DEFAULT_TYPES : $types;
    }

    /**
     * The MIME types an upload may have, for the file field.
     *
     * @return array<int, string>
     */
    public function mimeTypes(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (string $type): array => self::TYPES[$type][2], $this->types()))));
    }

    /** The chosen types' labels, e.g. "MP3, M4A / AAC, OGG / Opus or WAV". */
    public function typeLabels(): string
    {
        $labels = array_map(fn (string $type): string => self::TYPES[$type][0], $this->types());
        $last = array_pop($labels);

        return $labels === [] ? (string) $last : implode(', ', $labels).' '.__('or').' '.$last;
    }

    /** The size chosen in Website › Media, in MB. */
    public function chosenMegabytes(): int
    {
        $chosen = $this->settings->get(self::MEGABYTES_SETTING);

        return is_numeric($chosen) && (int) $chosen >= 1 ? min((int) $chosen, self::MAX_MEGABYTES) : self::DEFAULT_MEGABYTES;
    }

    /** The largest upload PHP takes, in kilobytes, or null for no limit. */
    public function serverMaxKilobytes(): ?int
    {
        $bytes = $this->phpUploadLimit();

        return $bytes > 0 && $bytes < PHP_INT_MAX ? intdiv((int) $bytes, 1024) : null;
    }

    /** PHP's upload limit in bytes (the lower of upload_max_filesize and post_max_size), as Symfony reads it. */
    protected function phpUploadLimit(): int|float
    {
        return UploadedFile::getMaxFilesize();
    }

    /** The largest file an Audio block takes, in kilobytes: the size chosen, or the server's limit if that's lower. */
    public function maxKilobytes(): int
    {
        $chosen = $this->chosenMegabytes() * 1024;
        $server = $this->serverMaxKilobytes();

        return $server === null ? $chosen : max(1, min($chosen, $server));
    }

    /** Whether the server's limit is lower than the size chosen. */
    public function isLimitedByServer(): bool
    {
        return $this->maxKilobytes() < $this->chosenMegabytes() * 1024;
    }

    /** A size in kilobytes as megabytes for people, e.g. "100 MB" or "1.5 MB". */
    public static function megabytes(int $kilobytes): string
    {
        return __(':size MB', ['size' => rtrim(rtrim(number_format($kilobytes / 1024, 1, '.', ''), '0'), '.')]);
    }
}
