<?php

namespace ScBudgetWeb\SiteBuilder\Settings;

/**
 * What Audio blocks take (Website › Media › Audio): plain sound formats only, since uploads are served from
 * the site's own address and nothing a browser could run as a page may be among them.
 */
class AudioUploads extends UploadRules
{
    public const TYPES = [
        'mp3' => ['MP3', ['mp3'], ['audio/mpeg', 'audio/mp3', 'audio/mpeg3', 'audio/x-mpeg-3']],
        'm4a' => ['M4A / AAC', ['m4a', 'aac'], ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'audio/aac', 'audio/x-aac', 'audio/aacp']],
        'ogg' => ['OGG / Opus', ['ogg', 'oga', 'opus'], ['audio/ogg', 'application/ogg', 'audio/opus', 'audio/x-ogg']],
        'wav' => ['WAV', ['wav'], ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave']],
        'flac' => ['FLAC', ['flac'], ['audio/flac', 'audio/x-flac']],
        'webm' => ['WebM audio', ['webm'], ['audio/webm', 'video/webm']],
    ];

    /** The four every current browser plays. */
    public const DEFAULT_TYPES = ['mp3', 'm4a', 'ogg', 'wav'];

    /** Enough for an hour's talk at 128 kbps. */
    public const DEFAULT_MEGABYTES = 100;

    public const MAX_MEGABYTES = 2048;

    public const TYPES_SETTING = 'media.audio_types';

    public const MEGABYTES_SETTING = 'media.audio_max_megabytes';
}
