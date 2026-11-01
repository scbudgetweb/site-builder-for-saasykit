<?php

namespace ScBudgetWeb\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file uploaded to pages (Website › Media's Library tab): an image, an SVG or a sound file. It stays until someone deletes
 * it in the library; blocks still store its path. Rows are scoped to the current upload folder (inUploadFolder()), so
 * an app with a folder per tenant only sees its own.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string $kind
 * @property string|null $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaFile extends Model
{
    public const IMAGE = 'image';

    public const SVG = 'svg';

    public const AUDIO = 'audio';

    /** Anything else found in the upload folder (from before the library, or an app's own field). */
    public const OTHER = 'other';

    protected $table = 'site_builder_media_files';

    protected $fillable = ['disk', 'path', 'name', 'kind', 'mime_type', 'size', 'width', 'height', 'alt', 'uploaded_by'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }

    /**
     * Files in this folder (the pages upload folder).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInUploadFolder(Builder $query, string $directory): Builder
    {
        // A plain LIKE with no escape characters works the same on SQLite and MySQL (lesson 13). An "_" in the folder's
        // name matches any character, which could only show a neighbouring folder's files, never hide this one's.
        return $query->where('path', 'like', $directory.'/%');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isImage(): bool
    {
        return in_array($this->kind, [self::IMAGE, self::SVG], true);
    }

    /**
     * The kinds, for labels and filters.
     *
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return [
            self::IMAGE => __('Images'),
            self::SVG => __('SVG drawings'),
            self::AUDIO => __('Sound files'),
            self::OTHER => __('Other files'),
        ];
    }
}
