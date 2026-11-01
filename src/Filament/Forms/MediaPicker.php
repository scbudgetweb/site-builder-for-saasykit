<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Renderless;
use ScBudgetWeb\SiteBuilder\Models\MediaFile;
use ScBudgetWeb\SiteBuilder\Pages\PageService;

/**
 * Picks one file from the media library (Website › Media): a grid of thumbnails, newest first, with a search on the
 * name and, when it offers more than one kind, a filter by kind, loading more as asked. Saves the file's path, and only
 * takes a path that's in the library, in this site's upload folder, of one of its kinds.
 */
class MediaPicker extends Field
{
    /** How many files a page of the grid holds. */
    public const PER_PAGE = 30;

    protected string $view = 'site-builder::filament.forms.media-picker';

    /** @var array<int, string> */
    protected array $kinds = [MediaFile::IMAGE, MediaFile::SVG];

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (filled($value) && ! $this->query()->where('path', $value)->exists()) {
                $fail(__('Choose a file from the library.'));
            }
        });
    }

    /**
     * @param  array<int, string>  $kinds  MediaFile::IMAGE, SVG, AUDIO
     */
    public function kinds(array $kinds): static
    {
        $this->kinds = array_values($kinds);

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getKinds(): array
    {
        return $this->kinds;
    }

    /**
     * The kinds to filter by, with their labels (none when there's only one).
     *
     * @return array<string, string>
     */
    public function getKindFilters(): array
    {
        return count($this->kinds) > 1 ? array_intersect_key(MediaFile::kinds(), array_flip($this->kinds)) : [];
    }

    /**
     * A page of the grid: the files matching the search and kind, newest first, and whether there are more.
     *
     * @return array{items: array<int, array{path: string, url: string, name: string, kind: string, summary: string}>, hasMore: bool}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getMediaForJs(string $search = '', string $kind = '', int $page = 1): array
    {
        $page = max(1, $page);
        $files = $this->query()
            ->when(in_array($kind, $this->kinds, true), fn ($query) => $query->where('kind', $kind))
            ->when(trim($search) !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.trim($search).'%')
                ->orWhere('path', 'like', '%'.trim($search).'%')))
            ->latest('created_at')
            ->latest('id')
            ->skip(($page - 1) * self::PER_PAGE)
            ->take(self::PER_PAGE + 1)
            ->get();

        return [
            'items' => $files->take(self::PER_PAGE)->map(fn (MediaFile $file): array => [
                'path' => $file->path,
                'url' => $file->url(),
                'name' => $file->name,
                'kind' => $file->kind,
                'summary' => implode(' · ', array_filter([
                    Str::upper(pathinfo($file->path, PATHINFO_EXTENSION)),
                    Number::fileSize($file->size, 1),
                    $file->width ? "{$file->width} × {$file->height}" : null,
                ])),
            ])->values()->all(),
            'hasMore' => $files->count() > self::PER_PAGE,
        ];
    }

    /**
     * @return Builder<MediaFile>
     */
    private function query(): Builder
    {
        return MediaFile::query()
            ->inUploadFolder(app(PageService::class)->uploadDirectory())
            ->whereIn('kind', $this->kinds);
    }
}
