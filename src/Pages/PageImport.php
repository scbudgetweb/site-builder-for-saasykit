<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Filament\Schemas\Schema;
use finfo;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Schemas\PageForm;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Support\SafeSvg;
use Throwable;

/**
 * Builds or updates a page from JSON (site-builder:page), so agents, scripts and template sites can make pages
 * reliably. The page goes through the page form itself, without a screen (PageImportForm): every block's data is
 * checked by its own fields and rules, as Save in the editor does, and saved the way the editor saves it. On top of
 * the form, it refuses what the form would quietly drop: unknown block types, settings that don't exist (a typo), a
 * number or a yes/no that isn't one, and an image that isn't an uploaded file. A list (repeater) holds only the rows
 * given: none come from its defaults.
 *
 * The JSON: {"title", "slug", "status" ("draft" by default, or "published"), "replaces", "show_page_banner",
 * "seo_title", "seo_description", "share_image", "noindex", "blocks": [{"type": "hero", "data": {…}}, …]}. A Section's
 * data has "rows": [{"layout": "2", "columns": [{"blocks": […]}, …]}]. An image is the path of a page upload (e.g.
 * from "Choose from uploads"), or "file:images/hero.jpg" to upload one, relative to the JSON file.
 */
final class PageImport
{
    /** The page settings an import can give. */
    public const KEYS = ['title', 'slug', 'status', 'replaces', 'show_page_banner', 'seo_title', 'seo_description', 'share_image', 'noindex', 'blocks'];

    /** Starts an image to upload from a file, e.g. "file:images/hero.jpg". */
    public const FILE_PREFIX = 'file:';

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<int, string> Files uploaded by this import, removed again if it isn't saved. */
    private array $storedFiles = [];

    public function __construct(private BlockRegistry $registry, private PageService $pages) {}

    /**
     * Checks the page and, unless there's a problem or it's a $dryRun, saves it. With $update, a page that already has
     * the slug gets the settings given (the rest stay as they are); without it, that's a problem.
     *
     * @param  string|null  $baseDirectory  where "file:" images are found (the JSON file's folder)
     */
    public function import(mixed $input, ?string $baseDirectory = null, bool $update = false, bool $dryRun = false): PageImportResult
    {
        $this->errors = $this->warnings = $this->storedFiles = [];

        if (! is_array($input) || ($input !== [] && array_is_list($input))) {
            return new PageImportResult([__('page: Expected a JSON object with the page\'s settings, e.g. {"title": …, "slug": …, "blocks": […]}.')]);
        }

        foreach (array_diff(array_keys($input), self::KEYS) as $key) {
            $this->error((string) $key, __('Not a page setting. A page has: :keys.', ['keys' => implode(', ', self::KEYS)]));
        }

        if (array_key_exists('blocks', $input)) {
            $this->checkBlocks($input['blocks'], 'blocks', inColumn: false);
        }

        $existing = is_string($input['slug'] ?? null) ? Page::withTrashed()->where('slug', $input['slug'])->first() : null;

        if ($existing?->trashed()) {
            $this->error('slug', __('The page with this slug is in the bin. Restore it first, or use another slug.'));
        } elseif ($existing !== null && ! $update) {
            $this->error('slug', __('A page already uses this slug. Use --update to change it, or choose another slug.'));
        }

        if ($this->errors !== []) {
            return $this->result();
        }

        $input = $this->withUploadedFiles($input, $baseDirectory);

        if ($this->errors !== []) {
            return $this->result(discardFiles: true);
        }

        [$state, $page] = $this->validated($input, $existing);

        if ($state === null || $this->errors !== [] || $dryRun) {
            return $this->result(discardFiles: true);
        }

        try {
            $existing !== null ? $existing->update($state) : $page = Page::query()->create($state);
        } catch (Throwable $exception) {
            $this->discardFiles();

            throw $exception;
        }

        return new PageImportResult([], $this->warnings, $existing ?? $page, $existing === null);
    }

    /**
     * The page's settings through the page form: checked by its rules, then dehydrated as Save does. Null when the
     * form refused them (the reasons are in the errors).
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>|null, 1: Page}
     */
    private function validated(array $input, ?Page $existing): array
    {
        $form = new PageImportForm;
        $schema = PageForm::configure(Schema::make($form)
            ->statePath('data')
            ->model($existing ?? Page::class)
            ->operation($existing !== null ? 'edit' : 'create'));

        $current = $existing === null ? ['status' => PageStatus::Draft->value, 'blocks' => []] : [
            ...$existing->only(array_diff(self::KEYS, ['status'])),
            'status' => $existing->status->value,
        ];
        $schema->fill([...$current, ...$input]);
        $known = self::settingPaths($schema);
        $raw = $form->data ?? [];

        try {
            $state = $schema->getState();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->error(self::readablePath((string) $key, $form->data ?? []), $message);
                }
            }
            $this->compare($input, $raw, null, $known, '', 'data');

            return [null, $existing ?? new Page];
        }

        $state = self::listed($state);
        $this->compare($input, $raw, $state, $known, '', 'data');

        $page = ($existing !== null ? clone $existing : new Page)->fill($state);
        $requiredBlock = $page->slot()?->requiredBlock();

        if ($requiredBlock !== null && ! Page::blocksContain($state['blocks'] ?? [], $requiredBlock)) {
            $this->warnings[] = 'replaces: '.__('It replaces :page, which needs the ":block" block to keep working.', [
                'page' => $page->slot()?->label(),
                'block' => $this->registry->labelFor($requiredBlock),
            ]);
        }

        return [$state, $page];
    }

    /**
     * Each block has a known type that can go where it is (a Section's column takes fewer), and a Section's rows and
     * columns are lists. The form drops a block it doesn't know without a word, so this comes first.
     */
    private function checkBlocks(mixed $blocks, string $path, bool $inColumn): void
    {
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            $this->error($path, __('Expected a list of blocks, e.g. [{"type": "text", "data": {…}}].'));

            return;
        }

        $offered = $this->registry->offered();
        $allowed = $inColumn ? array_diff_key($offered, array_flip(SectionBlock::EXCLUDED_BLOCKS)) : $offered;

        foreach ($blocks as $index => $block) {
            $at = "{$path}.{$index}";

            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                $this->error($at, __('Expected a block, e.g. {"type": "text", "data": {…}}.'));

                continue;
            }

            foreach (array_diff(array_keys($block), ['type', 'data', 'uid']) as $key) {
                $this->error("{$at}.{$key}", __('Not part of a block. A block has "type" and "data" (and may keep its "uid").'));
            }

            if (! isset($allowed[$block['type']])) {
                $this->error("{$at}.type", isset($offered[$block['type']])
                    ? __('A :block can\'t go in a Section\'s column.', ['block' => $this->registry->labelFor($block['type'])])
                    : __('Unknown block type ":type". The types are: :types.', ['type' => $block['type'], 'types' => implode(', ', array_keys($allowed))]));

                continue;
            }

            if (array_key_exists('data', $block) && (! is_array($block['data']) || ($block['data'] !== [] && array_is_list($block['data'])))) {
                $this->error("{$at}.data", __('Expected an object of the block\'s settings.'));

                continue;
            }

            if ($block['type'] === SectionBlock::NAME) {
                $this->checkRows($block['data']['rows'] ?? [], "{$at}.data.rows");
            }
        }
    }

    private function checkRows(mixed $rows, string $path): void
    {
        if (! is_array($rows) || ! array_is_list($rows)) {
            $this->error($path, __('Expected a list of rows, e.g. [{"layout": "2", "columns": [{"blocks": […]}, …]}].'));

            return;
        }

        foreach ($rows as $rowIndex => $row) {
            $columns = is_array($row) ? ($row['columns'] ?? []) : null;

            if (! is_array($columns) || ! array_is_list($columns)) {
                $this->error("{$path}.{$rowIndex}.columns", __('Expected a list of columns, e.g. [{"blocks": […]}, …].'));

                continue;
            }

            foreach ($columns as $columnIndex => $column) {
                $at = "{$path}.{$rowIndex}.columns.{$columnIndex}";

                is_array($column)
                    ? $this->checkBlocks($column['blocks'] ?? [], "{$at}.blocks", inColumn: true)
                    : $this->error($at, __('Expected a column, e.g. {"blocks": […]}.'));
            }
        }
    }

    /**
     * Compares what was given with the form's state: a setting the form doesn't have is a problem (often a typo), one
     * it left out because another setting hides it is a warning, and a value it changed into something else (text
     * where a number goes, an image path that isn't an uploaded file) is a problem. Without $output (the form refused
     * the page), only the settings are checked.
     *
     * The form's state ($raw) keys its lists' items by uuids, so the nth item given is its nth key there.
     *
     * @param  array<int|string, mixed>  $input
     * @param  array<int|string, mixed>|null  $output
     * @param  array<string, true>  $known  every setting's state path, and the paths of the groups holding them
     */
    private function compare(array $input, mixed $raw, ?array $output, array $known, string $path, string $statePath): void
    {
        $rawKeys = is_array($raw) ? array_keys($raw) : [];
        $isList = $rawKeys !== [] && array_filter($rawKeys, fn (int|string $key): bool => ! is_string($key) || preg_match(self::UUID_PATTERN, $key) !== 1) === [];

        foreach ($input as $key => $value) {
            $at = ltrim("{$path}.{$key}", '.');

            // A block's type is checked first (checkBlocks()), and its uid is given a new one if it can't be kept.
            if ($key === 'uid' || ($key === 'type' && is_array($raw) && array_key_exists('data', $raw))) {
                continue;
            }

            $rawKey = $isList && is_int($key) ? ($rawKeys[$key] ?? null) : $key;
            $atState = "{$statePath}.{$rawKey}";

            if (! is_array($raw) || $rawKey === null || ! array_key_exists($rawKey, $raw) || (! $isList && ! isset($known[$atState]))) {
                $this->error($at, __('Not a setting here. Check its spelling, and that it belongs to this block.'));

                continue;
            }

            if ($output !== null && ! array_key_exists($key, $output)) {
                $this->warnings[] = "{$at}: ".__('Left out: it only applies when another setting turns it on.');

                continue;
            }

            $after = $output[$key] ?? null;

            if (is_array($value)) {
                if (is_array($raw[$rawKey])) {
                    $this->compare($value, $raw[$rawKey], $output !== null && is_array($after) ? $after : null, $known, $at, $atState);
                }

                continue;
            }

            if ($output === null) {
                continue;
            }

            if (is_string($value) && $value !== '' && ! is_numeric($value) && (is_int($after) || is_float($after))) {
                $this->error($at, __('Must be a number.'));
            } elseif (is_string($value) && is_bool($after) && ! in_array(Str::lower($value), ['0', '1', 'true', 'false'], true)) {
                $this->error($at, __('Must be true or false.'));
            } elseif ($value !== null && $value !== '' && $value !== false && ($after === null || $after === [])) {
                $this->error($at, is_string($value) && Str::contains($value, ['/', '.'])
                    ? __('Not accepted. An image must be a file already uploaded to :folder, or "file:…" to upload one.', ['folder' => $this->pages->uploadDirectory()])
                    : __('Not accepted.'));
            }
        }
    }

    /**
     * The input with each "file:" image uploaded to the pages folder, after the checks an upload in the editor gets.
     * A share image can't be an SVG: social sites don't show them.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function withUploadedFiles(array $input, ?string $baseDirectory): array
    {
        $walk = function (mixed $value, string $path, bool $allowsSvg) use (&$walk, $baseDirectory): mixed {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $value[$key] = $walk($item, "{$path}.{$key}", $allowsSvg);
                }

                return $value;
            }

            return is_string($value) && str_starts_with($value, self::FILE_PREFIX)
                ? $this->upload(Str::after($value, self::FILE_PREFIX), $path, $baseDirectory, $allowsSvg) ?? $value
                : $value;
        };

        foreach (['blocks' => true, 'share_image' => false] as $key => $allowsSvg) {
            if (array_key_exists($key, $input)) {
                $input[$key] = $walk($input[$key], $key, $allowsSvg);
            }
        }

        return $input;
    }

    private function upload(string $file, string $path, ?string $baseDirectory, bool $allowsSvg): ?string
    {
        $fullPath = str_starts_with($file, '/') ? $file : rtrim($baseDirectory ?? (string) getcwd(), '/').'/'.$file;
        $types = $allowsSvg ? [...PageBlock::IMAGE_FILE_TYPES, PageBlock::SVG_FILE_TYPE] : PageBlock::IMAGE_FILE_TYPES;
        $type = is_file($fullPath) ? (new finfo(FILEINFO_MIME_TYPE))->file($fullPath) : null;

        $problem = match (true) {
            ! is_file($fullPath) => __('No file at :path.', ['path' => $fullPath]),
            ! in_array($type, $types, true) => $allowsSvg
                ? __('Not an image the site takes: JPG, PNG, WebP, AVIF or SVG.')
                : __('Not an image the site takes here: JPG, PNG, WebP or AVIF.'),
            filesize($fullPath) > PageBlock::IMAGE_MAX_KILOBYTES * 1024 => __('Larger than :size MB.', ['size' => PageBlock::IMAGE_MAX_KILOBYTES / 1024]),
            $type === PageBlock::SVG_FILE_TYPE => SafeSvg::problem((string) file_get_contents($fullPath)),
            default => null,
        };

        if ($problem !== null) {
            $this->error($path, $problem);

            return null;
        }

        $stored = Storage::disk($this->pages->uploadDisk())->putFile($this->pages->uploadDirectory(), new File($fullPath), ['visibility' => 'public']);

        if (! is_string($stored)) {
            $this->error($path, __('The image couldn\'t be stored.'));

            return null;
        }

        return $this->storedFiles[] = $stored;
    }

    private function result(bool $discardFiles = false): PageImportResult
    {
        if ($discardFiles) {
            $this->discardFiles();
        }

        return new PageImportResult($this->errors, $this->warnings);
    }

    private function discardFiles(): void
    {
        if ($this->storedFiles !== []) {
            Storage::disk($this->pages->uploadDisk())->delete($this->storedFiles);
        }
    }

    private function error(string $path, string $message): void
    {
        $this->errors[] = "{$path}: {$message}";
    }

    /**
     * A form error's key ("data.blocks.{uuid}.data.items.{uuid}.title") as a path into the JSON
     * ("blocks.2.data.items.0.title"): each list item the form keys by a uuid becomes its position.
     *
     * @param  array<int|string, mixed>  $state
     */
    private static function readablePath(string $key, array $state): string
    {
        $node = $state;
        $path = [];

        foreach (explode('.', Str::after($key, 'data.')) as $segment) {
            if (is_array($node) && array_key_exists($segment, $node)) {
                $path[] = preg_match(self::UUID_PATTERN, $segment) === 1 ? (string) array_search($segment, array_keys($node), true) : $segment;
                $node = $node[$segment];
            } else {
                $path[] = $segment;
                $node = null;
            }
        }

        return implode('.', $path);
    }

    /**
     * The state path of every setting in the form, hidden ones too (e.g. "data.blocks.{uuid}.data.heading"), and of each
     * group or list holding them, so a key the form has no field for (a typo) can be told from one it hides.
     *
     * @return array<string, true>
     */
    private static function settingPaths(Schema $schema): array
    {
        $paths = [];

        foreach ($schema->getFlatFields(withHidden: true) as $field) {
            $path = $field->getStatePath();

            while (str_contains($path, '.')) {
                $paths[$path] = true;
                $path = Str::beforeLast($path, '.');
            }
        }

        return $paths;
    }

    /**
     * The form's state with its lists (keyed by uuids) as plain lists, to line up with the JSON.
     */
    private static function listed(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::listed(...), $value);
        $isKeyedByUuid = $value !== [] && array_filter(array_keys($value), fn (int|string $key): bool => ! is_string($key) || preg_match(self::UUID_PATTERN, $key) !== 1) === [];

        return $isKeyedByUuid ? array_values($value) : $value;
    }
}
