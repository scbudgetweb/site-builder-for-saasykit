<?php

namespace ScBudgetWeb\SiteBuilder\Settings;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * What one kind of page upload takes (Website › Media's Images and Audio tabs): the file types chosen, from a fixed list (TYPES), and
 * the largest file. The size chosen can't go past the server's own limit (PHP's upload_max_filesize and post_max_size),
 * so the limit used is the lower of the two. LivewireLimits raises Livewire's temporary upload limit to match.
 */
abstract class UploadRules
{
    /** Each type: its label, its file extensions and the names browsers and servers give it. */
    public const TYPES = [];

    /** @var array<int, string> The types taken until an admin chooses. */
    public const DEFAULT_TYPES = [];

    /** The largest file until an admin chooses, in MB. */
    public const DEFAULT_MEGABYTES = 2;

    /** The largest an admin can choose, in MB. */
    public const MAX_MEGABYTES = 2048;

    public const TYPES_SETTING = '';

    public const MEGABYTES_SETTING = '';

    public function __construct(protected Settings $settings) {}

    /**
     * The types chosen (keys of TYPES), in TYPES' order. Never empty: with none saved, the standard ones.
     *
     * @return array<int, string>
     */
    public function types(): array
    {
        $chosen = $this->settings->get(static::TYPES_SETTING);
        $types = is_array($chosen) ? array_values(array_intersect(array_keys(static::TYPES), $chosen)) : [];

        return $types === [] ? static::DEFAULT_TYPES : $types;
    }

    /**
     * The MIME types an upload may have, for the file field.
     *
     * @return array<int, string>
     */
    public function mimeTypes(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (string $type): array => static::TYPES[$type][2], $this->types()))));
    }

    /**
     * The file extensions taken.
     *
     * @return array<int, string>
     */
    public function extensions(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (string $type): array => static::TYPES[$type][1], $this->types()))));
    }

    /**
     * The chosen types' labels (or those of $types), e.g. "MP3, M4A / AAC, OGG / Opus or WAV".
     *
     * @param  array<int, string>|null  $types
     */
    public function typeLabels(?array $types = null): string
    {
        $labels = array_map(fn (string $type): string => static::TYPES[$type][0], $types ?? $this->types());
        $last = array_pop($labels);

        return $labels === [] ? (string) $last : implode(', ', $labels).' '.__('or').' '.$last;
    }

    /** The size chosen in Website › Media, in MB. */
    public function chosenMegabytes(): int
    {
        $chosen = $this->settings->get(static::MEGABYTES_SETTING);

        return is_numeric($chosen) && (int) $chosen >= 1 ? min((int) $chosen, static::MAX_MEGABYTES) : static::DEFAULT_MEGABYTES;
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

    /** The largest file taken, in kilobytes: the size chosen, or the server's limit if that's lower. */
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
