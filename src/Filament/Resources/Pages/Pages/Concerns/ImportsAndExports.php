<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use ScBudgetWeb\SiteBuilder\Blocks\BlockTree;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\PageExport;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;
use ScBudgetWeb\SiteBuilder\Pages\PageImportResult;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The visual builder's import and export (PageExport, PageImport): Export in the toolbar and on the canvas menu,
 * Import… on the canvas menu, and Import page. The file field, the refusal and the "Imported" notice are shared with
 * the form builder and the lists.
 *
 * @mixin VisualEditor
 */
trait ImportsAndExports
{
    /**
     * Export in the toolbar: the page's blocks, the template's (with its settings) or the library block's, as a file
     * to import here or on another site (PageExport). What the builder shows, saved or not.
     */
    public function exportAction(): Action
    {
        return Action::make('export')
            ->label(__('Export'))
            ->tooltip(fn (): string => match (true) {
                $this->record() instanceof Template => __('Download this template as a file, to import here or on another site'),
                $this->record() instanceof SavedBlock => __('Download this library block as a file, to import here or on another site'),
                default => __('Download this page\'s content as a file, to import here or on another site'),
            })
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->iconButton()
            ->color('gray')
            ->action(function (): StreamedResponse {
                $record = $this->record();
                $blocks = $this->draft()->blocks ?? [];
                $export = app(PageExport::class);

                return $export->download(match (true) {
                    $record instanceof Template => $export->template($record, $blocks),
                    $record instanceof SavedBlock => $export->library($blocks),
                    default => $export->page($blocks),
                }, $record instanceof Page ? $record->title : $record->name);
            });
    }

    /**
     * Export on the canvas menu: one Section, block, row or column, as a file to import elsewhere (PageExport).
     */
    public function exportPart(string $uid): ?StreamedResponse
    {
        $blocks = $this->draft()->blocks ?? [];
        $file = app(PageExport::class)->part($blocks, $uid);

        if ($file === null) {
            $this->cantDoThat();

            return null;
        }

        // Named after the page, and the part's own name if it was given one in the layers panel.
        $node = BlockTree::locate($blocks, $uid)['node'] ?? [];
        $customName = $node['data']['admin_label'] ?? $node['admin_label'] ?? null;
        $record = $this->record();

        return app(PageExport::class)->download($file, trim(($record instanceof Page ? $record->title : $record->name).' '.(is_string($customName) ? $customName : '')));
    }

    /**
     * Import on the canvas menu: a file from Export, placed by what was right-clicked (importedBlocks()). Its blocks
     * are checked as Save checks them; images that aren't on this site are left out. One change, so undo takes it back.
     */
    public function importPartAction(): Action
    {
        return Action::make('importPart')
            ->modalHeading(__('Import'))
            ->modalDescription(fn (array $arguments): string => match (BlockTree::locate($this->draft()->blocks ?? [], (string) ($arguments['uid'] ?? ''))['kind'] ?? null) {
                'row' => __('A row from Export goes after this row.'),
                'column' => __('Blocks, or a column\'s blocks, from Export go at the end of this column.'),
                default => __('A Section or blocks from Export go after this. A row goes into a Section (at its end), and in a column, a column\'s blocks join it.'),
            })
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('Import'))
            ->schema([self::exportFileField()])
            ->action(function (array $arguments, array $data, Action $action): void {
                $export = self::readUploadedExport($data['file'] ?? null);
                $result = is_string($export) ? $export : $this->importedBlocks($this->draft()->blocks ?? [], (string) ($arguments['uid'] ?? ''), $export);

                if (is_string($result) || $result->failed()) {
                    self::importRefused($result);
                    $action->halt();
                }

                $this->changeBlocks($result->blocks);
                self::imported($result);
            });
    }

    /**
     * Import in the toolbar: a page (or a template, Section or blocks) from Export, replacing what's here (the default,
     * with a warning) or added at the end. Into the draft as one change, so undo and Revisions bring the old back.
     * Not in the library's editor: a library item is one block (its list has Import).
     */
    public function importPageAction(): Action
    {
        $isTemplate = fn (): bool => $this->record() instanceof Template;

        return Action::make('importPage')
            ->label(__('Import'))
            ->tooltip(fn (): string => $isTemplate()
                ? __('Import a template from a file made with Export')
                : __('Import a page from a file made with Export'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => $this->allowsTopLevelChanges())
            ->modalHeading(fn (): string => $isTemplate() ? __('Import a template') : __('Import a page'))
            ->modalDescription(__('A file from Export, on this site or another. Only the content comes in: never the title, address or SEO. Images that aren\'t on this site are left out.'))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('Import'))
            ->schema(fn (): array => [
                self::exportFileField(),
                Toggle::make('replace')
                    ->label(fn (): string => $isTemplate() ? __('Replace the template\'s content') : __('Replace the page\'s content'))
                    ->helperText(__('Off: what\'s in the file is added at the end.'))
                    ->default(true)
                    ->live(),
                Callout::make(fn (): string => $isTemplate() ? __('Everything in this template will be replaced') : __('Everything on this page will be replaced'))
                    ->description($isTemplate()
                        ? __('Undo brings it back until you save. A header\'s settings change straight away.')
                        : __('Undo or Revisions bring it back. Visitors see nothing until you publish.'))
                    ->danger()
                    ->visible(fn (Get $get): bool => (bool) $get('replace')),
            ])
            ->action(function (array $data, Action $action): void {
                $export = self::readUploadedExport($data['file'] ?? null);
                $result = is_string($export) ? $export : $this->importedPage($export, (bool) ($data['replace'] ?? true));

                if (is_string($result) || $result->failed()) {
                    self::importRefused($result);
                    $action->halt();
                }

                // A template's settings (a header's behaviour) come with it when its content is replaced.
                if (($data['replace'] ?? true) && is_array($export) && isset($export['template'])) {
                    $this->importTemplateSettings($export['template']);
                }

                $this->changeBlocks($result->blocks);
                self::imported($result);
            });
    }

    /**
     * The draft's blocks with an export's blocks in place of them ($replace) or after them, or why it can't be used.
     *
     * @param  array<string, mixed>  $export
     */
    protected function importedPage(array $export, bool $replace): PageImportResult|string
    {
        if (in_array($export['kind'], ['row', 'column'], true)) {
            return __('This file is a :part. To import it, right-click where it should go (a Section, a row or a column) and choose Import.', ['part' => $export['kind'] === 'row' ? __('row') : __('column')]);
        }

        $problem = $this->importRefusal($export);

        if ($problem !== null) {
            return $problem;
        }

        if ($export['blocks'] === []) {
            return __('There are no blocks in this file.');
        }

        $result = app(PageImport::class)->blocks($export['blocks']);

        if ($result->failed() || $replace) {
            return $result;
        }

        $blocks = $this->draft()->blocks ?? [];

        foreach ($result->blocks as $block) {
            $blocks = BlockTree::insert($blocks, $block) ?? $blocks;
        }

        return new PageImportResult([], $result->warnings, blocks: $blocks);
    }

    /**
     * Why an export can't come into what's being edited, if it can't (a template of another type, say).
     *
     * @param  array<string, mixed>  $export
     */
    protected function importRefusal(array $export): ?string
    {
        return null;
    }

    /**
     * A template export's settings, for a template being edited.
     *
     * @param  array<string, mixed>  $template
     */
    protected function importTemplateSettings(array $template): void {}

    /**
     * The file field of each Import: a JSON file made with Export.
     */
    public static function exportFileField(): FileUpload
    {
        return FileUpload::make('file')
            ->label(__('Export file'))
            ->helperText(__('A .json file made with Export, on this site or another.'))
            ->acceptedFileTypes(['application/json', 'text/plain'])
            ->maxSize(5120)
            ->storeFiles(false)
            ->required();
    }

    /**
     * The uploaded file's export (PageImport::readExport()), or why it can't be used.
     *
     * @return array<string, mixed>|string
     */
    public static function readUploadedExport(mixed $file): array|string
    {
        $file = is_array($file) ? Arr::first($file) : $file;

        return $file instanceof TemporaryUploadedFile
            ? PageImport::readExport((string) $file->get())
            : __('Choose a file made with Export.');
    }

    /**
     * Nothing imported: why, the first few problems listed.
     */
    public static function importRefused(PageImportResult|string $result): void
    {
        $problems = is_string($result) ? [$result] : array_map(PageImport::forPeople(...), $result->errors);
        $more = count($problems) - 6;

        Notification::make()
            ->title(__('Nothing was imported'))
            ->body(new HtmlString(implode('<br>', array_map(e(...), [...array_slice($problems, 0, 6), ...($more > 0 ? [trans_choice('…and :count more.|…and :count more.', $more, ['count' => $more])] : [])]))))
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * Imported: how to take it back, and anything left out (an image from another site).
     */
    public static function imported(PageImportResult $result, ?string $hint = null): void
    {
        $notification = Notification::make()
            ->title(__('Imported'))
            ->body(new HtmlString(implode('<br>', array_map(e(...), [
                $hint ?? __('Undo (Ctrl+Z, ⌘Z on a Mac) takes it back.'),
                ...array_map(PageImport::forPeople(...), array_slice($result->warnings, 0, 6)),
            ]))));

        ($result->warnings === [] ? $notification->success() : $notification->warning()->persistent())->send();
    }

    /**
     * The draft's blocks with an export placed by what was right-clicked ($uid):
     * - a Section or blocks: after a block, or at the end of a column (only blocks that can go in one);
     * - a row: after a row, at the end of a Section, or after the row of a block in a column;
     * - a column: its blocks, as blocks are, but never at the page's top level.
     * The result's blocks are the whole draft's, or its problems; a string when it can't go there at all.
     *
     * @param  array<int|string, mixed>  $blocks
     * @param  array<string, mixed>  $export
     */
    protected function importedBlocks(array $blocks, string $uid, array $export): PageImportResult|string
    {
        $target = BlockTree::locate($blocks, $uid);
        $kind = $export['kind'];

        if ($target === null) {
            return __('That part of the page is no longer there.');
        }

        $isSection = $target['kind'] === 'block' && ($target['node']['type'] ?? null) === SectionBlock::NAME;
        $isInColumn = $target['kind'] === 'column' || ($target['kind'] === 'block' && $target['path'] !== []);

        if ($kind === 'row') {
            [$position, $at] = match (true) {
                $target['kind'] === 'row' => ['after', $uid],
                $isSection => ['in', $uid],
                $isInColumn && $target['kind'] === 'block' => ['after', collect(BlockTree::trail($blocks, $uid))->where('kind', 'row')->last()['node']['uid'] ?? null],
                default => [null, null],
            };

            if ($position === null || ! is_string($at)) {
                return __('A row goes in a Section. Right-click a Section, a row, or a block in a Section.');
            }

            $result = app(PageImport::class)->row($export['row']);
            $nodes = $result->blocks;
        } else {
            if ($target['kind'] === 'row') {
                return $kind === 'column'
                    ? __('A column\'s blocks go into a column. Right-click a column, or a block in one.')
                    : __('Blocks and Sections go before or after a block. Right-click a block, or a column to add to its end.');
            }

            if ($kind === 'column' && ! $isInColumn) {
                return __('A column\'s blocks go into a column. Right-click a column, or a block in one.');
            }

            [$position, $at] = $target['kind'] === 'column' ? ['in', $uid] : ['after', $uid];
            $incoming = $kind === 'column' ? (is_array($export['column']['blocks'] ?? null) ? array_values($export['column']['blocks']) : []) : $export['blocks'];

            if ($incoming === []) {
                return __('There are no blocks in this file.');
            }

            $result = app(PageImport::class)->blocks($incoming, inColumn: $isInColumn);
            $nodes = $result->blocks;
        }

        if ($result->failed()) {
            return $result;
        }

        if ($this->isTopLevelChangeRefused(null, $at, $position)) {
            return __('Only what\'s inside this can change, so the import goes inside it: right-click a column, or a block in one.');
        }

        foreach ($nodes as $node) {
            $blocks = BlockTree::insert($blocks, $node, $at, $position);

            if ($blocks === null) {
                return __('That can\'t go there.');
            }

            // The next one after this one, in order; into a column, each at its end.
            if ($position !== 'in' || $kind === 'row') {
                [$at, $position] = [$node['uid'], 'after'];
            }
        }

        return new PageImportResult([], $result->warnings, blocks: $blocks);
    }
}
