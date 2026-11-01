<?php

namespace ScBudgetWeb\SiteBuilder\Settings;

use Illuminate\Support\Facades\Image;
use Intervention\Image\ImageManager;

/**
 * What image fields on pages take (Website › Media › Images): picture formats, and SVG drawings, which are
 * checked on upload (SafeSvg). With Laravel's image tools (Laravel 13 and intervention/image 4, using PHP's GD), photos
 * can also be shrunk to a largest width and saved without their location data when they're uploaded (MediaLibrary).
 */
class ImageUploads extends UploadRules
{
    public const TYPES = [
        'jpg' => ['JPG', ['jpg', 'jpeg'], ['image/jpeg']],
        'png' => ['PNG', ['png'], ['image/png']],
        'webp' => ['WebP', ['webp'], ['image/webp']],
        'avif' => ['AVIF', ['avif'], ['image/avif']],
        'gif' => ['GIF', ['gif'], ['image/gif']],
        'svg' => ['SVG', ['svg'], ['image/svg+xml']],
    ];

    /** As before there were settings. */
    public const DEFAULT_TYPES = ['jpg', 'png', 'webp', 'avif', 'svg'];

    public const DEFAULT_MEGABYTES = 2;

    public const MAX_MEGABYTES = 100;

    public const TYPES_SETTING = 'media.image_types';

    public const MEGABYTES_SETTING = 'media.image_max_megabytes';

    public const WIDTH_SETTING = 'media.image_max_width';

    public const STRIP_LOCATION_SETTING = 'media.image_strip_location';

    /** Largest widths a photo can be shrunk to, in pixels. */
    public const WIDTHS = [1600, 1920, 2560, 3840];

    /**
     * The MIME types an image field may take; without $withSvg (a share image, say), no SVG.
     *
     * @return array<int, string>
     */
    public function mimeTypesFor(bool $withSvg): array
    {
        return $withSvg ? $this->mimeTypes() : array_values(array_diff($this->mimeTypes(), self::TYPES['svg'][2]));
    }

    /** The types an image field takes, for people; without $withSvg, no SVG. */
    public function typeLabelsFor(bool $withSvg): string
    {
        return $this->typeLabels($withSvg ? $this->types() : array_values(array_diff($this->types(), ['svg'])));
    }

    public function allowsSvg(): bool
    {
        return in_array('svg', $this->types(), true);
    }

    /**
     * Whether photos can be processed on upload: Laravel's Image facade (Laravel 13), Intervention Image 4 behind it
     * (3 has no usingDriver()), and PHP's GD, which saves images without their metadata.
     */
    public function canProcess(): bool
    {
        return class_exists(Image::class)
            && class_exists(ImageManager::class)
            && method_exists(ImageManager::class, 'usingDriver')
            && function_exists('imagecreatetruecolor');
    }

    /** The width photos are shrunk to, or null to keep them as uploaded. */
    public function maxWidth(): ?int
    {
        $width = $this->settings->get(self::WIDTH_SETTING);

        return $this->canProcess() && is_numeric($width) && in_array((int) $width, self::WIDTHS, true) ? (int) $width : null;
    }

    /** Whether photos are saved again without their location data (and the rest of their camera details). */
    public function stripsLocation(): bool
    {
        return $this->canProcess() && (bool) $this->settings->get(self::STRIP_LOCATION_SETTING, false);
    }
}
