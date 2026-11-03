<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\BlockSchema;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Schemas\PageForm;
use ScBudgetWeb\SiteBuilder\Media\MediaLibrary;
use ScBudgetWeb\SiteBuilder\Models\MediaFile;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Settings\ImageUploads;
use ScBudgetWeb\SiteBuilder\Support\SafeSvg;
use ScBudgetWeb\SiteBuilder\Templates\Templates;
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

    /** A 1 × 1 PNG: the stand-in for an image that isn't on this site while imported blocks are checked. */
    private const STAND_IN_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<int, string> Files uploaded by this import, removed again if it isn't saved. */
    private array $storedFiles = [];

    /** Whether an image that isn't uploaded here is left out with a warning (an export from another site), not refused. */
    private bool $leavesOutMissingImages = false;

    /** Whether problems are worded for people importing a file (the builders), not for someone writing JSON. */
    private bool $forPeople = false;

    public function __construct(private BlockRegistry $registry, private PageService $pages) {}

    /**
     * An export file's content (PageExport) with its blocks in today's shape, or why it can't be used: not JSON, not
     * an export, or from a newer site builder.
     *
     * @return array{kind: string, blocks?: array<int, mixed>, row?: array<string, mixed>, column?: array<string, mixed>, template?: array<string, mixed>}|string
     */
    public static function readExport(string $json): array|string
    {
        try {
            $file = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return __('This isn\'t a site builder export: it isn\'t a JSON file.');
        }

        if (! is_array($file) || ! is_int($file['site_builder'] ?? null) || ! in_array($file['kind'] ?? null, PageExport::KINDS, true)) {
            return __('This isn\'t a site builder export. Use a file made with Export.');
        }

        $schema = is_int($file['schema'] ?? null) ? $file['schema'] : 1;

        if ($file['site_builder'] > PageExport::FORMAT || $schema > BlockSchema::VERSION) {
            return __('This file was exported from a newer version of the site builder. Update the site builder here to import it.');
        }

        $key = match ($file['kind']) {
            'row', 'column' => $file['kind'],
            default => 'blocks',
        };
        $content = $file[$key] ?? null;

        if (! is_array($content) || ($key === 'blocks') !== array_is_list($content) || ($key !== 'blocks' && $content === [])) {
            return __('This export is damaged: it has no :part.', ['part' => $key]);
        }

        // Blocks saved in an older shape are upgraded as stored ones are: a row or column inside a Section.
        $content = match ($key) {
            'row' => BlockSchema::upgrade([self::sectionOf([$content])], $schema)[0]['data']['rows'][0],
            'column' => BlockSchema::upgrade([self::sectionOf([['layout' => 'stacked', 'columns' => [$content]]])], $schema)[0]['data']['rows'][0]['columns'][0],
            default => BlockSchema::upgrade($content, $schema),
        };

        return [
            'kind' => $file['kind'],
            $key => $content,
            ...($file['kind'] === 'template' && is_array($file['template'] ?? null) ? ['template' => $file['template']] : []),
        ];
    }

    /**
     * Blocks from an export, for the visual builder: checked as the page form checks them, with new uids. An image
     * that isn't uploaded on this site (an export from another one) is left out with a warning instead of stopping
     * the import, and "file:" images aren't uploaded. With $inColumn, only blocks that can go in a Section's column.
     *
     * @param  array<int, mixed>  $blocks
     */
    public function blocks(array $blocks, bool $inColumn = false): PageImportResult
    {
        $this->errors = $this->warnings = $this->storedFiles = [];
        $this->forPeople = true;
        $this->checkBlocks($blocks, 'blocks', $inColumn);

        if ($this->errors !== []) {
            return $this->result();
        }

        $checked = $this->checkedParts($inColumn ? [self::sectionOf([['layout' => 'stacked', 'columns' => [['blocks' => $blocks]]]])] : $blocks, $inColumn ? 'blocks.0.data.rows.0.columns.0.blocks' : null);

        return $checked === null
            ? $this->result()
            : new PageImportResult([], $this->warnings, blocks: BlockSchema::withNewUids($inColumn ? $checked[0]['data']['rows'][0]['columns'][0]['blocks'] : $checked));
    }

    /**
     * A row from an export, checked in a Section, with new uids (PageImportResult's blocks: the row on its own).
     *
     * @param  array<string, mixed>  $row
     */
    public function row(array $row): PageImportResult
    {
        return $this->part(self::sectionOf([$row]), 'blocks.0.data.rows.0', 'row', fn (array $section): array => $section['data']['rows'][0]);
    }

    /**
     * A column from an export, checked in a one-column row (PageImportResult's blocks: the column on its own).
     *
     * @param  array<string, mixed>  $column
     */
    public function column(array $column): PageImportResult
    {
        return $this->part(self::sectionOf([['layout' => 'stacked', 'columns' => [$column]]]), 'blocks.0.data.rows.0.columns.0', 'column', fn (array $section): array => $section['data']['rows'][0]['columns'][0]);
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  Closure(array<string, mixed>): array<string, mixed>  $unwrap
     */
    private function part(array $section, string $prefix, string $name, Closure $unwrap): PageImportResult
    {
        $this->errors = $this->warnings = $this->storedFiles = [];
        $this->forPeople = true;
        $this->checkBlocks([$section], 'blocks', inColumn: false);

        $checked = $this->errors === [] ? $this->checkedParts([$section], $prefix, $name) : null;

        if ($checked === null) {
            $this->errors = array_map(fn (string $error): string => self::withoutWrapper($error, $prefix, $name), $this->errors);

            return $this->result();
        }

        return new PageImportResult([], $this->warnings, blocks: [$unwrap(BlockSchema::withNewUids($checked)[0])]);
    }

    /**
     * The blocks through the page form (with a stand-in title and address), or null when it refused them. Problems and
     * warnings name places from the part the person imported: inside $prefix, as $name.
     *
     * @param  array<int, mixed>  $blocks
     * @return array<int, array<string, mixed>>|null
     */
    private function checkedParts(array $blocks, ?string $prefix, string $name = 'blocks'): ?array
    {
        // An upload that isn't on this site points at a stand-in while the form checks the blocks, so a block whose
        // image is required still comes in: afterwards it has no image, as a block just added has none.
        $disk = Storage::disk($this->pages->uploadDisk());
        $standIn = $this->pages->uploadDirectory().'/import-'.Str::lower(Str::random(24)).'.png';
        $missing = [];
        $blocks = $this->withStandIns($blocks, $standIn, 'blocks', $missing);
        $this->leavesOutMissingImages = true;

        try {
            if ($missing !== []) {
                $disk->put($standIn, (string) base64_decode(self::STAND_IN_PNG));
            }

            [$state] = $this->validated(['title' => 'Import', 'slug' => 'site-builder-import-'.Str::lower(Str::random(16)), 'blocks' => $blocks], null);
        } finally {
            $this->leavesOutMissingImages = false;
            $missing !== [] && $disk->delete($standIn);
        }

        foreach ($missing as $path) {
            $this->warnings[] = "{$path}: ".__('An image that isn\'t uploaded on this site, so it was left out. Choose another in its settings.');
        }

        $state = $state === null ? null : self::withoutStandIns($state, $standIn);

        if ($prefix !== null) {
            $this->errors = array_map(fn (string $error): string => self::withoutWrapper($error, $prefix, $name), $this->errors);
            $this->warnings = array_map(fn (string $warning): string => self::withoutWrapper($warning, $prefix, $name), $this->warnings);
        }

        return $state === null || $this->errors !== [] ? null : array_values($state['blocks'] ?? []);
    }

    /**
     * The value with each page upload that isn't on this site, or "file:" image (not uploaded from the visual
     * builder), swapped for $standIn, and their paths in $missing.
     *
     * @param  array<int, string>  $missing
     */
    private function withStandIns(mixed $value, string $standIn, string $path, array &$missing): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->withStandIns($item, $standIn, "{$path}.{$key}", $missing);
            }

            return $value;
        }

        $isMissing = is_string($value) && (str_starts_with($value, self::FILE_PREFIX)
            || ($this->pages->isPageUpload($value) && ! Storage::disk($this->pages->uploadDisk())->exists($value)));

        if (! $isMissing) {
            return $value;
        }

        $missing[] = $path;

        return $standIn;
    }

    private static function withoutStandIns(mixed $value, string $standIn): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => self::withoutStandIns($item, $standIn), $value);
        }

        return $value === $standIn ? null : $value;
    }

    /**
     * "blocks.0.data.rows.0.columns.0.blocks.2.data…: …" as "blocks.2.data…: …" for blocks imported into a column.
     */
    private static function withoutWrapper(string $message, string $prefix, string $name): string
    {
        return str_starts_with($message, $prefix) ? $name.Str::after($message, $prefix) : $message;
    }

    /**
     * A Section holding these rows.
     *
     * @param  array<int, mixed>  $rows
     * @return array{type: string, data: array{rows: array<int, mixed>}}
     */
    private static function sectionOf(array $rows): array
    {
        return ['type' => SectionBlock::NAME, 'data' => ['rows' => $rows]];
    }

    /**
     * Checks the page and, unless there's a problem or it's a $dryRun, saves it. With $update, a page that already has
     * the slug gets the settings given (the rest stay as they are); without it, that's a problem.
     *
     * @param  string|null  $baseDirectory  where "file:" images are found (the JSON file's folder)
     */
    public function import(mixed $input, ?string $baseDirectory = null, bool $update = false, bool $dryRun = false): PageImportResult
    {
        $this->errors = $this->warnings = $this->storedFiles = [];
        $this->forPeople = false;

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
                $this->error("{$at}.type", $this->notAllowedHere($block['type'], isset($offered[$block['type']]), $allowed));

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

    /**
     * Why a block type can't go where it is: not in a Section's column, only made for another kind of template (a
     * footer's links in a header), or not a block this site has.
     *
     * @param  array<string, class-string<PageBlock>>  $allowed
     */
    private function notAllowedHere(string $type, bool $isOffered, array $allowed): string
    {
        $label = $this->registry->labelFor($type);

        if ($isOffered) {
            return __('A :block can\'t go in a Section\'s column.', ['block' => $label]);
        }

        $block = $this->registry->find($type);
        $templateTypes = $block === null ? null : $block::templateTypes();

        if ($templateTypes !== null) {
            $names = app(Templates::class)->types();
            $kinds = implode(__(' or '), array_map(fn (string $kind): string => Str::lower($names[$kind] ?? $kind), $templateTypes));
            $editing = $this->registry->editingTemplateType();

            return $editing === null
                ? __('The ":block" block is only for :kinds templates, so it can\'t go on a page or in the block library.', ['block' => $label, 'kinds' => $kinds])
                : __('The ":block" block is only for :kinds templates, so it can\'t go in a :here template.', ['block' => $label, 'kinds' => $kinds, 'here' => Str::lower($names[$editing] ?? $editing)]);
        }

        return $this->forPeople
            ? __('This site has no ":type" block. The file may be from a newer version of the site builder, or use a block from an add-on this site doesn\'t have.', ['type' => $type])
            : __('Unknown block type ":type". The types are: :types.', ['type' => $type, 'types' => implode(', ', array_keys($allowed))]);
    }

    /**
     * A problem or warning ("blocks.0.data.rows.0.columns.1.blocks.0.type: …") with its place in words, for people:
     * "Block 1 › Row 1 › Column 2 › Block 1: …". Settings are named from their keys ("Button text").
     */
    public static function forPeople(string $problem): string
    {
        if (! str_contains($problem, ': ')) {
            return $problem;
        }

        [$path, $message] = explode(': ', $problem, 2);

        if (preg_match('/^[\w.]+$/', $path) !== 1) {
            return $problem;
        }

        $segments = explode('.', $path);
        $place = [];

        foreach ($segments as $index => $segment) {
            $next = $segments[$index + 1] ?? null;

            if (ctype_digit($segment) || in_array($segment, ['data', 'type', 'replaces'], true)) {
                continue;
            }

            $name = match ($segment) {
                'blocks' => __('Block'),
                'rows' => __('Row'),
                'columns' => __('Column'),
                'row' => __('The row'),
                'column' => __('The column'),
                default => Str::headline($segment),
            };
            $place[] = $next !== null && ctype_digit($next) ? Str::singular($name).' '.((int) $next + 1) : $name;
        }

        return $place === [] ? $message : implode(' › ', $place).': '.$message;
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

            $this->checkNodeSettings(array_diff_key($row, ['columns' => true, 'uid' => true]), SectionBlock::rowSettingsPanel(inForm: true), "{$path}.{$rowIndex}");

            foreach ($columns as $columnIndex => $column) {
                $at = "{$path}.{$rowIndex}.columns.{$columnIndex}";

                if (! is_array($column)) {
                    $this->error($at, __('Expected a column, e.g. {"blocks": […]}.'));

                    continue;
                }

                $this->checkNodeSettings(array_diff_key($column, ['blocks' => true, 'uid' => true]), SectionBlock::columnSettingsPanel(), $at);
                $this->checkBlocks($column['blocks'] ?? [], "{$at}.blocks", inColumn: true);
            }
        }
    }

    /**
     * A row's or column's settings, checked by its own panel (the visual builder's Row or Column settings): the page
     * form only carries them along, hidden, so this is where a typo or a bad value is caught.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<int, Component>  $panel
     */
    private function checkNodeSettings(array $settings, array $panel, string $path): void
    {
        if ($settings === []) {
            return;
        }

        $form = new PageImportForm;
        $schema = Schema::make($form)->statePath('data')->components($panel);
        $schema->fill($settings);
        $known = self::settingPaths($schema);
        $raw = $form->data ?? [];

        try {
            $state = self::listed($schema->getState());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->error($path.'.'.self::readablePath((string) $key, $raw), $message);
                }
            }

            $state = null;
        }

        $this->compare($settings, $raw, is_array($state) ? $state : null, $known, $path, 'data');
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
     * @param  array<string, bool>  $known  every setting's state path, and the paths of the groups holding them
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
                // A field that holds a whole value (a row's settings, carried along hidden) isn't looked inside.
                if (is_array($raw[$rawKey]) && ! self::holdsWholeValue($known, $atState)) {
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
            } elseif ($this->leavesOutMissingImages && is_string($value) && Str::contains($value, ['/', '.']) && ($after === null || $after === [])) {
                $this->warnings[] = "{$at}: ".__('An image that isn\'t uploaded on this site, so it was left out. Choose another in its settings.');
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
        $images = app(ImageUploads::class);
        $types = $images->mimeTypesFor($allowsSvg);
        $type = is_file($fullPath) ? (new finfo(FILEINFO_MIME_TYPE))->file($fullPath) : null;

        $problem = match (true) {
            ! is_file($fullPath) => __('No file at :path.', ['path' => $fullPath]),
            ! in_array($type, $types, true) => __('Not an image the site takes here: :types (Website › Media › Images).', ['types' => $images->typeLabelsFor($allowsSvg)]),
            filesize($fullPath) > $images->maxKilobytes() * 1024 => __('Larger than :size.', ['size' => ImageUploads::megabytes($images->maxKilobytes())]),
            $type === PageBlock::SVG_FILE_TYPE => SafeSvg::problem((string) file_get_contents($fullPath)),
            default => null,
        };

        if ($problem !== null) {
            $this->error($path, $problem);

            return null;
        }

        // Stored and added to the media library as an upload in the editor is (photos processed as Website › Media › Images says).
        $stored = app(MediaLibrary::class)->store(new UploadedFile($fullPath, basename($fullPath), $type, null, true));

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
            MediaFile::query()->whereIn('path', $this->storedFiles)->delete();
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
     * Whether the field at this state path keeps its value whole: a field with nothing of the form's below it.
     *
     * @param  array<string, bool>  $known
     */
    private static function holdsWholeValue(array $known, string $statePath): bool
    {
        return ($known[$statePath] ?? null) === false;
    }

    /**
     * The state path of every setting in the form, hidden ones too (e.g. "data.blocks.{uuid}.data.heading"), and of each
     * group or list holding them, so a key the form has no field for (a typo) can be told from one it hides. True for
     * a path with settings under it, false for a field holding its value whole.
     *
     * @return array<string, bool>
     */
    private static function settingPaths(Schema $schema): array
    {
        $paths = [];

        foreach ($schema->getFlatFields(withHidden: true) as $field) {
            $path = $field->getStatePath();

            if (str_contains($path, '.')) {
                $paths[$path] ??= false;
            }

            while (str_contains($path = Str::beforeLast($path, '.'), '.')) {
                $paths[$path] = true;
            }
        }

        return $paths;
    }

    /**
     * The form's state with its lists (keyed by uuids) as plain lists, to line up with the JSON.
     */
    public static function listed(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::listed(...), $value);
        $isKeyedByUuid = $value !== [] && array_filter(array_keys($value), fn (int|string $key): bool => ! is_string($key) || preg_match(self::UUID_PATTERN, $key) !== 1) === [];

        return $isKeyedByUuid ? array_values($value) : $value;
    }
}
