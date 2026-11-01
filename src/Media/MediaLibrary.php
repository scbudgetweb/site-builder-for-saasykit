<?php

namespace ScBudgetWeb\SiteBuilder\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Models\MediaFile;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\PageDraft;
use ScBudgetWeb\SiteBuilder\Models\PageRevision;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Settings\AudioUploads;
use ScBudgetWeb\SiteBuilder\Settings\ImageUploads;
use Throwable;

/**
 * Website › Media's Library tab: every file uploaded to pages has a row (MediaFile), made as it's stored (store()), and stays
 * until someone deletes it there (delete()), which is refused while a page, library block, template or draft uses it.
 * Photos are shrunk and saved without their location data on the way in, when Website › Media › Images says so.
 */
class MediaLibrary
{
    /** Photos larger than this many pixels are stored as uploaded: decoding them could run PHP out of memory. */
    public const MAX_PROCESSED_PIXELS = 40_000_000;

    /** The quality photos are saved at when they're processed. */
    public const QUALITY = 85;

    /** @var array<string, true>|null Paths current content uses, once worked out in this request (usedPaths()). */
    private ?array $usedPaths = null;

    public function __construct(private PageService $pages, private ImageUploads $images) {}

    /**
     * Stores an uploaded file in the pages upload folder (processing a photo first, when Website › Media › Images says so) and adds
     * it to the library. Returns its path, or null when it couldn't be stored.
     */
    public function store(UploadedFile $file): ?string
    {
        $disk = $this->pages->uploadDisk();
        $directory = $this->pages->uploadDirectory();
        $path = $this->storeProcessedImage($file, $directory, $disk) ?? $file->storePublicly($directory, ['disk' => $disk]);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $this->record($path, $file->getClientOriginalName(), auth()->id());

        return $path;
    }

    /**
     * Adds a file already in the pages upload folder to the library (once), reading its type, size and dimensions.
     */
    public function record(string $path, ?string $name = null, mixed $uploadedBy = null): MediaFile
    {
        $existing = MediaFile::query()->where('path', $path)->first();

        if ($existing !== null) {
            return $existing;
        }

        $disk = Storage::disk($this->pages->uploadDisk());
        $kind = self::kindOf($path);
        [$width, $height] = $kind === MediaFile::IMAGE ? $this->dimensions($disk->get($path)) : [null, null];

        return MediaFile::query()->create([
            'disk' => $this->pages->uploadDisk(),
            'path' => $path,
            'name' => Str::limit(trim((string) $name) !== '' ? trim((string) $name) : basename($path), 250, ''),
            'kind' => $kind,
            'mime_type' => rescue(fn (): string|false => $disk->mimeType($path), null, false) ?: null,
            'size' => (int) rescue(fn (): int => $disk->size($path), 0, false),
            'width' => $width,
            'height' => $height,
            'uploaded_by' => is_numeric($uploadedBy) ? (int) $uploadedBy : null,
        ]);
    }

    /**
     * Adds every file in the pages upload folder that isn't in the library yet (files uploaded before it existed).
     * Returns how many were added.
     */
    public function recordExisting(): int
    {
        $disk = Storage::disk($this->pages->uploadDisk());
        $known = MediaFile::query()->inUploadFolder($this->pages->uploadDirectory())->pluck('path')->flip();
        $added = 0;

        foreach ($disk->allFiles($this->pages->uploadDirectory()) as $path) {
            if ($this->pages->isPageUpload($path) && ! isset($known[$path]) && ! str_starts_with(basename($path), '.')) {
                $file = $this->record($path);
                // Dated when it was uploaded, not when it joined the library.
                $uploadedAt = rescue(fn (): int => $disk->lastModified($path), null, false);

                if (is_int($uploadedAt)) {
                    $date = Carbon::createFromTimestamp($uploadedAt);
                    $file->timestamps = false;
                    $file->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
                }

                $added++;
            }
        }

        return $added;
    }

    /**
     * Which of these paths are in the library (and so are kept until someone deletes them).
     *
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    public function recordedPaths(array $paths): array
    {
        // In chunks, so a folder of thousands of files stays inside the database's limit on bound values.
        return collect($paths)->chunk(500)
            ->flatMap(fn ($chunk) => MediaFile::query()->whereIn('path', $chunk->values()->all())->pluck('path'))
            ->values()
            ->all();
    }

    /**
     * The page uploads that current content uses: pages (including the bin), library blocks, templates and visual
     * builder drafts (with their undo and redo). Kept revisions aren't counted: they're listed in usesOf(), and a file
     * only they use can be deleted.
     *
     * @return array<string, true>
     */
    public function usedPaths(): array
    {
        if ($this->usedPaths !== null) {
            return $this->usedPaths;
        }

        $used = [];
        $collect = function (mixed $value) use (&$used): void {
            foreach ($this->pages->uploadedPaths(is_array($value) ? $value : [$value]) as $path) {
                $used[$path] = true;
            }
        };

        Page::withTrashed()->select(['id', 'blocks', 'share_image'])->lazyById()->each(function (Page $page) use ($collect): void {
            $collect([$page->blocks ?? [], $page->share_image]);
        });
        SavedBlock::query()->select(['id', 'blocks'])->lazyById()->each(fn (SavedBlock $block) => $collect($block->blocks ?? []));
        Template::query()->select(['id', 'blocks'])->lazyById()->each(fn (Template $template) => $collect($template->blocks ?? []));
        PageDraft::query()->select(['id', 'blocks', 'history', 'future'])->lazyById()->each(fn (PageDraft $draft) => $collect([$draft->blocks ?? [], $draft->history ?? [], $draft->future ?? []]));

        return $this->usedPaths = $used;
    }

    public function isUsed(string $path): bool
    {
        return isset($this->usedPaths()[$path]);
    }

    /**
     * Where a file is used, for the library's "Used on": each place's label and, where there is one, a link to edit it.
     *
     * @return array<int, array{label: string, url: string|null}>
     */
    public function usesOf(string $path): array
    {
        // Encoded the way the models store their JSON, as PageService::isUploadInUse() matches it.
        $pattern = '%'.json_encode($path, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'%';
        $uses = [];

        foreach (Page::withTrashed()->where(fn ($query) => $query->where('blocks', 'like', $pattern)->orWhere('share_image', $path))->orderBy('title')->get(['id', 'title', 'deleted_at']) as $page) {
            $uses[] = [
                'label' => $page->trashed() ? __('Page: :title (in the bin)', ['title' => $page->title]) : __('Page: :title', ['title' => $page->title]),
                'url' => self::urlOr(fn (): string => PageResource::getUrl('edit', ['record' => $page])),
            ];
        }

        foreach (SavedBlock::query()->where('blocks', 'like', $pattern)->orderBy('name')->get(['id', 'name']) as $block) {
            $uses[] = ['label' => __('Library block: :name', ['name' => $block->name]), 'url' => self::urlOr(fn (): string => SavedBlockResource::getUrl('edit', ['record' => $block]))];
        }

        foreach (Template::query()->where('blocks', 'like', $pattern)->orderBy('name')->get(['id', 'name']) as $template) {
            $uses[] = ['label' => __('Template: :name', ['name' => $template->name]), 'url' => self::urlOr(fn (): string => TemplateResource::getUrl('edit', ['record' => $template]))];
        }

        $drafts = PageDraft::query()->where(fn ($query) => $query->where('blocks', 'like', $pattern)->orWhere('history', 'like', $pattern)->orWhere('future', 'like', $pattern))->count();

        if ($drafts > 0) {
            $uses[] = ['label' => trans_choice('An unsaved visual builder draft (or its undo)|:count unsaved visual builder drafts (or their undo)', $drafts), 'url' => null];
        }

        $revisions = PageRevision::query()->where('snapshot', 'like', $pattern)->count();

        if ($revisions > 0) {
            $uses[] = ['label' => trans_choice('A kept revision (restoring it after deleting this would show nothing in its place)|:count kept revisions (restoring them after deleting this would show nothing in its place)', $revisions), 'url' => null];
        }

        return $uses;
    }

    /**
     * Deletes the file and its row, unless current content uses it. Returns whether it was deleted.
     */
    public function delete(MediaFile $file): bool
    {
        if ($this->isUsed($file->path)) {
            return false;
        }

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        return true;
    }

    /**
     * The library kind for a path, from its extension.
     */
    public static function kindOf(string $path): string
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        return match (true) {
            $extension === 'svg' => MediaFile::SVG,
            in_array($extension, array_merge(...array_column(ImageUploads::TYPES, 1)), true) => MediaFile::IMAGE,
            in_array($extension, array_merge(...array_column(AudioUploads::TYPES, 1)), true) => MediaFile::AUDIO,
            default => MediaFile::OTHER,
        };
    }

    /**
     * Shrinks a photo to Website › Media › Images' largest width and saves it again without its location data, when either is
     * on and the photo needs it. Null (store the upload as it is) for anything else, or if processing fails.
     */
    private function storeProcessedImage(UploadedFile $file, string $directory, string $disk): ?string
    {
        $maxWidth = $this->images->maxWidth();
        $stripsLocation = $this->images->stripsLocation();

        if ($maxWidth === null && ! $stripsLocation) {
            return null;
        }

        $type = $file->getMimeType();
        $size = @getimagesize($file->getRealPath());

        // GD saves without metadata. GIFs (which may move) and formats GD can't read stay as uploaded.
        if (! in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true) || ! is_array($size) || $size[0] * $size[1] > self::MAX_PROCESSED_PIXELS) {
            return null;
        }

        $isWider = $maxWidth !== null && $size[0] > $maxWidth;
        $hasMetadata = $stripsLocation && $type !== 'image/png';

        if (! $isWider && ! $hasMetadata) {
            return null;
        }

        try {
            $path = Image::fromUpload($file)
                ->usingGd()
                ->orient()
                ->when($isWider, fn ($image) => $image->scale(width: $maxWidth))
                ->quality(self::QUALITY)
                ->storePublicly($directory, $disk);

            return is_string($path) ? $path : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(?string $contents): array
    {
        $size = is_string($contents) && $contents !== '' ? @getimagesizefromstring($contents) : false;

        return is_array($size) ? [(int) $size[0], (int) $size[1]] : [null, null];
    }

    /**
     * @param  \Closure(): string  $url
     */
    private static function urlOr(\Closure $url): ?string
    {
        try {
            return $url();
        } catch (Throwable) {
            return null;
        }
    }
}
