<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\BlockTree;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SavedBlockBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\PageRenderer;
use ScBudgetWeb\SiteBuilder\Blocks\StyleClipboard;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\PageDraft;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\PageRevisions;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;
use Throwable;

/**
 * Pages › Visual builder: the page in the site's own theme, in an iframe (the canvas), where clicking a block opens its
 * settings. Changes go into the person's draft (VisualDrafts), saved as they go; Publish copies the draft to the page.
 *
 * The server holds the state: the browser only names a uid, which is looked up in the stored draft, and every action
 * checks the page's update permission (the page's own access check runs on every request). After a change, the page
 * body's new HTML is sent to the editor (site-builder-canvas-updated), which swaps it into the canvas.
 */
class VisualEditor extends ResourcePage
{
    use InteractsWithRecord;

    protected static string $resource = PageResource::class;

    protected string $view = 'site-builder::filament.pages.visual-editor';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** The draft, read once per request. */
    private ?PageDraft $openDraft = null;

    /** Whether the canvas shows a panel's unsaved changes (previewBlock()), so closing the panel puts the draft back. */
    public bool $isPreviewing = false;

    /**
     * The row or column panel to open once the open one is saved (goToNode()). Only for the request it's set in.
     *
     * @var array{name: string, uid: string}|null
     */
    private ?array $nextPanel = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();

        // A page in the bin is edited as a form, where its status is locked until it's restored.
        if ($this->record() instanceof Page && $this->record()->trashed()) {
            $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record()]));
        }
    }

    public function hydrate(): void
    {
        $this->authorizeAccess();
    }

    /**
     * On every request, the block pickers offer the blocks for what's being edited (a template's own blocks only in
     * a template of their type).
     */
    public function booted(): void
    {
        app(BlockRegistry::class)->editingTemplate($this->templateTypeBeingEdited());
    }

    /**
     * The Theme Builder template type being edited, or null for a page or library item.
     */
    protected function templateTypeBeingEdited(): ?string
    {
        return null;
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->page()->title;
    }

    /**
     * The canvas's page: this page with $blocks in place of its own (the library's editor shows its item on its own).
     *
     * @param  array<int|string, mixed>  $blocks
     */
    protected function canvasPage(array $blocks): Page
    {
        $page = clone $this->page();
        $page->forceFill(['blocks' => $blocks]);

        return $page;
    }

    /**
     * Whether blocks can be added, removed or moved at the top level. A library item is one block (often a Section), so
     * its editor only changes what's inside it.
     */
    public function allowsTopLevelChanges(): bool
    {
        return true;
    }

    /**
     * Whether a change would add, remove or move a top-level block when that isn't allowed (allowsTopLevelChanges()).
     */
    private function isTopLevelChangeRefused(?string $uid, ?string $targetUid = null, ?string $position = null): bool
    {
        if ($this->allowsTopLevelChanges()) {
            return false;
        }

        $blocks = $this->draft()->blocks ?? [];
        $isTopLevelBlock = fn (?string $nodeUid): bool => $nodeUid === null
            || ((($found = BlockTree::locate($blocks, $nodeUid))['kind'] ?? null) === 'block' && $found['path'] === []);

        return ($uid !== null && $isTopLevelBlock($uid)) || ($position !== 'in' && $position !== null && $isTopLevelBlock($targetUid));
    }

    /**
     * What Publish says: the button, the warning before it (null for none) and the message after.
     *
     * @return array{label: string, warning: ?string, title: string, body: string}
     */
    protected function publishTexts(): array
    {
        $isPublished = $this->page()->isPublished();

        return [
            // A draft page isn't public yet, so its changes are only saved.
            'label' => $isPublished ? __('Publish') : __('Save'),
            'warning' => app(VisualDrafts::class)->isOutOfDate($this->draft(), $this->page())
                ? __('Someone saved this page (:time) since you started. Publishing replaces their changes with yours.', ['time' => $this->page()->updated_at?->diffForHumans() ?? ''])
                : null,
            'title' => $isPublished ? __('Published') : __('Saved'),
            'body' => $isPublished ? __('Visitors now see your changes.') : __('The page is still a draft: publish it from its settings (Edit as a form) when it\'s ready.'),
        ];
    }

    /**
     * Full screen: the page's own toolbar (the view) takes the place of the heading, breadcrumbs and buttons.
     */
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * The toolbar's note on the draft: unpublished changes, or the page saved elsewhere since it started.
     */
    public function statusText(): string
    {
        $drafts = app(VisualDrafts::class);
        $draft = $this->draft();

        if ($drafts->isOutOfDate($draft, $this->record())) {
            return __('This page has been saved somewhere else since you started. Publishing will replace those changes.');
        }

        return $drafts->hasChanges($draft, $this->record())
            ? __('Unpublished changes, saved as you go. Visitors see them once you publish.')
            : __('Click a block to change it. Changes are saved as you go, and visitors see them once you publish.');
    }

    public function getBreadcrumb(): string
    {
        return __('Visual builder');
    }

    /**
     * The person's draft, or a new one (stored once something changes).
     */
    public function draft(): PageDraft
    {
        return $this->openDraft ??= app(VisualDrafts::class)->open($this->record(), auth()->user());
    }

    public function canvasUrl(): string
    {
        return $this->draft()->canvasUrl();
    }

    /**
     * What the canvas may select, by uid: what it is and its name for the outline's label. Blocks inside a linked
     * library block aren't listed, so clicking one selects the link.
     *
     * @return array<string, array{kind: string, label: string, hasStyle?: bool}>
     */
    public function canvasNodes(): array
    {
        return self::nodesOf($this->draft()->blocks ?? []);
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return array<string, array{kind: string, label: string, hasStyle?: bool}>
     */
    private static function nodesOf(array $blocks): array
    {
        $registry = app(BlockRegistry::class);
        $nodes = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! is_string($block['uid'] ?? null) || ! is_string($block['type'] ?? null)) {
                continue;
            }

            $blockClass = $registry->find($block['type']);
            $nodes[$block['uid']] = [
                'kind' => 'block',
                'label' => $blockClass ? $blockClass::label() : $block['type'],
                // A linked library block is styled in the library, so the canvas menu offers it no styles.
                'hasStyle' => $blockClass !== null && $block['type'] !== SavedBlockBlock::NAME,
                'isSection' => $block['type'] === SectionBlock::NAME,
            ];

            if ($block['type'] !== SectionBlock::NAME) {
                continue;
            }

            foreach (array_values(SectionBlock::rows(is_array($block['data'] ?? null) ? $block['data'] : [])) as $rowIndex => $row) {
                if ($row['uid'] !== null) {
                    $nodes[$row['uid']] = ['kind' => 'row', 'label' => $row['adminLabel'] ?? __('Row :number', ['number' => $rowIndex + 1])];
                }

                foreach ($row['columns'] as $columnIndex => $column) {
                    if ($column['uid'] !== null) {
                        $nodes[$column['uid']] = ['kind' => 'column', 'label' => $column['adminLabel'] ?? __('Column :number', ['number' => $columnIndex + 1])];
                    }
                }
            }

            // The blocks in the columns, as stored (rows() leaves out ones with an unexpected shape).
            foreach ($block['data']['rows'] ?? [] as $row) {
                foreach (is_array($row) ? ($row['columns'] ?? []) : [] as $column) {
                    $nodes += self::nodesOf(is_array($column) && is_array($column['blocks'] ?? null) ? $column['blocks'] : []);
                }
            }
        }

        return $nodes;
    }

    /**
     * The page body with the draft's blocks (or $blocks), marked up for the canvas.
     *
     * @param  array<int|string, mixed>|null  $blocks
     */
    public function canvasHtml(?array $blocks = null): string
    {
        $page = $this->canvasPage($blocks ?? $this->draft()->blocks ?? []);

        return view('site-builder::pages.content', ['page' => $page, 'marksNodes' => true])->render();
    }

    /**
     * The changed top-level block (or the Section holding the changed block) alone, marked up for the canvas, to swap in
     * for its old HTML: a big page isn't rendered again for every change. Null when it can't be done that way (the
     * block isn't on the page as rendered, e.g. a library link to a deleted block), so the whole page body is sent.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array{uid: string, html: string}|null
     */
    private function canvasBlockHtml(array $blocks, string $uid): ?array
    {
        $topUid = null;

        foreach ($blocks as $block) {
            if (is_array($block) && BlockTree::find([$block], $uid) !== null) {
                $topUid = is_string($block['uid'] ?? null) ? $block['uid'] : null;

                break;
            }
        }

        $page = $this->canvasPage($blocks);
        // As the page renders them, so the block keeps its position (only the first can hold the page's h1).
        $list = $page->blockList();
        $position = array_search($topUid, array_column($list, 'uid'), true);

        if ($topUid === null || $position === false) {
            return null;
        }

        $html = implode('', app(PageRenderer::class)->renderBlocks($list, $page, marksNodes: true, onlyIndex: $position));

        // A block that no longer shows has nothing to swap in.
        return $html === '' ? null : ['uid' => $topUid, 'html' => $html];
    }

    /**
     * Saves new blocks into the draft and sends the canvas the changed block's new HTML.
     *
     * @param  array<int|string, mixed>  $blocks
     */
    private function changeBlocks(array $blocks, ?string $uid = null): void
    {
        app(VisualDrafts::class)->change($this->draft(), $blocks);
        $this->isPreviewing = false;

        $this->closePanelsOfRemovedParts();
        $this->sendToCanvas($blocks, $uid);
    }

    /**
     * Closes the open panel when what it edits is no longer in the draft (deleted from the canvas menu, or undone).
     * Its action only shows for a part that's there, so it would otherwise stay mounted out of sight: it can't be saved
     * or closed, and the canvas, which saves the open panel before opening another, would stop responding.
     */
    private function closePanelsOfRemovedParts(): void
    {
        $panel = $this->mountedActions[0] ?? null;
        $uid = $panel['arguments']['uid'] ?? null;

        if (! in_array($panel['name'] ?? null, ['editBlock', 'editRow', 'editColumn'], true) || ! is_string($uid)
            || BlockTree::find($this->draft()->blocks ?? [], $uid) !== null) {
            return;
        }

        $this->isPreviewing = false;

        $this->unmountAction(cancelParentActions: true);
    }

    /**
     * Sends the canvas the changed block's HTML when it can (see canvasBlockHtml()), otherwise the whole page body.
     *
     * @param  array<int|string, mixed>  $blocks
     */
    private function sendToCanvas(array $blocks, ?string $uid, bool $isPreview = false): void
    {
        $block = $uid === null ? null : $this->canvasBlockHtml($blocks, $uid);

        $this->dispatch(
            'site-builder-canvas-updated',
            html: $block['html'] ?? $this->canvasHtml($blocks),
            uid: $block['uid'] ?? null,
            nodes: self::nodesOf($blocks),
            fonts: BlockDesign::fontStylesheets($blocks),
            isPreview: $isPreview,
        );
    }

    public function refreshCanvas(): void
    {
        $this->closePanelsOfRemovedParts();
        $this->sendToCanvas($this->draft()->blocks ?? [], null);
    }

    /**
     * Live preview: the open panel's unsaved values on the canvas, called by the editor shortly after
     * something in the panel changes (which also sends any values the panel hasn't sent yet). Nothing is saved. A
     * block's own panel previews, and so does a block's panel stacked over its Section's (the Section's values with
     * that block's unsaved ones in place), only for a block in the draft. A new image shows once the panel is saved,
     * when its upload is stored.
     */
    public function previewBlock(): void
    {
        $panel = $this->mountedActions[0] ?? null;
        $uid = $panel['arguments']['uid'] ?? null;

        // A row's or column's panel previews too.
        if (count($this->mountedActions) === 1 && in_array($panel['name'] ?? null, ['editRow', 'editColumn'], true) && is_string($uid) && is_array($panel['data'] ?? null)) {
            $blocks = $this->draft()->blocks ?? [];
            $change = $panel['name'] === 'editRow'
                ? fn (array $row): array => self::rowWith($row, $panel['data'])
                : fn (array $column): array => self::columnWith($column, $panel['data']);

            try {
                $this->sendToCanvas(BlockTree::update($blocks, $uid, $change), $uid, isPreview: true);
                $this->isPreviewing = true;
            } catch (Throwable) {
                // Values half way through being typed: the canvas keeps the last preview.
            }

            return;
        }

        if (! in_array(count($this->mountedActions), [1, 2], true) || ($panel['name'] ?? null) !== 'editBlock' || ! is_string($uid) || ! is_array($panel['data'] ?? null)) {
            return;
        }

        $data = count($this->mountedActions) === 1 ? $panel['data'] : self::withStackedPanel($panel['data'], $this->mountedActions[1]);

        if ($data === null) {
            return;
        }

        $blocks = $this->draft()->blocks ?? [];

        if ((BlockTree::find($blocks, $uid)['kind'] ?? null) !== 'block') {
            return;
        }

        [$data, $uploads] = self::withUploadPreviews($data);

        try {
            PageBlock::previewingImages($uploads, fn () => $this->sendToCanvas(BlockTree::update($blocks, $uid, fn (array $block): array => [...$block, 'data' => self::withDraftRows($block, $data)]), $uid, isPreview: true));
        } catch (Throwable) {
            // Values half way through being typed can't always be shown; the canvas keeps the last preview.
            return;
        }

        $this->isPreviewing = true;
    }

    /**
     * A Section's panel values with the unsaved values of the block panel stacked over it, or null when that panel
     * isn't a block's. The stacked panel is the Section's own Builder action, whose Builder sits at
     * rows.{row}.columns.{column}.blocks: Edit (that item), Add (a new item at the end) or Add between (after an item).
     *
     * @param  array<string, mixed>  $sectionData
     * @param  array<string, mixed>  $stacked
     * @return array<string, mixed>|null
     */
    private static function withStackedPanel(array $sectionData, array $stacked): ?array
    {
        $builder = Str::after((string) ($stacked['context']['schemaComponent'] ?? ''), 'mountedActionSchema0.');
        $arguments = is_array($stacked['arguments'] ?? null) ? $stacked['arguments'] : [];
        $items = Arr::get($sectionData, $builder);

        if (! preg_match('/^rows\.[\w-]+\.columns\.[\w-]+\.blocks$/', $builder) || ! is_array($items) || ! is_array($stacked['data'] ?? null)) {
            return null;
        }

        $isKey = fn (mixed $key): bool => is_string($key) && preg_match('/^[\w-]+$/', $key) === 1;

        if (($stacked['name'] ?? null) === 'edit') {
            $item = $arguments['item'] ?? null;

            if (! $isKey($item) || ! is_array($items[$item] ?? null)) {
                return null;
            }

            $items[$item]['data'] = $stacked['data'];
        } elseif (in_array($stacked['name'] ?? null, ['add', 'addBetween'], true) && $isKey($arguments['block'] ?? null)) {
            $new = ['type' => $arguments['block'], 'data' => $stacked['data']];
            $after = $arguments['afterItem'] ?? null;
            $position = $isKey($after) && array_key_exists($after, $items) ? array_search($after, array_keys($items), true) + 1 : count($items);

            $items = array_slice($items, 0, $position, true) + ['sb-preview-new' => $new] + array_slice($items, $position, null, true);
        } else {
            return null;
        }

        Arr::set($sectionData, $builder, $items);

        return $sectionData;
    }

    /**
     * The panel's values with each image uploaded but not saved yet (a Livewire temporary upload) swapped for a key,
     * and each key's temporary preview URL, so the live preview shows it before it's stored.
     *
     * @param  array<int|string, mixed>  $data
     * @return array{0: array<int|string, mixed>, 1: array<string, string>}
     */
    private static function withUploadPreviews(array $data): array
    {
        $urls = [];

        $walk = function (array $values) use (&$walk, &$urls): array {
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    $values[$key] = $walk($value);

                    continue;
                }

                $file = match (true) {
                    $value instanceof TemporaryUploadedFile => $value,
                    is_string($value) && str_starts_with($value, 'livewire-file:') => TemporaryUploadedFile::unserializeFromLivewireRequest($value),
                    default => null,
                };

                if (! $file instanceof TemporaryUploadedFile) {
                    continue;
                }

                try {
                    $previewKey = 'site-builder-preview:'.$file->getFilename();
                    $urls[$previewKey] = $file->temporaryUrl();
                    $values[$key] = $previewKey;
                } catch (Throwable) {
                    // Not an image Livewire can preview: it shows once the panel is saved.
                }
            }

            return $values;
        };

        return [$walk($data), $urls];
    }

    /**
     * Closing the panel without saving puts the canvas back to the draft, after a live preview. Closing a block's panel
     * stacked over its Section's shows the Section's panel values again.
     */
    public function unmountAction(bool|string|null $cancelParentActions = null): void
    {
        parent::unmountAction($cancelParentActions);

        if ($this->isPreviewing && $this->mountedActions === []) {
            $this->isPreviewing = false;
            $this->refreshCanvas();

            return;
        }

        // Back from a block's panel stacked over its Section's: the canvas shows the Section's panel, with that block's
        // saved values (or the block just added), even if nothing was previewed before.
        if (count($this->mountedActions) === 1) {
            $this->previewBlock();
        }
    }

    /**
     * A block's settings, opened by clicking it on the canvas, in a floating panel: it leaves the canvas
     * visible and clickable, can be dragged by its header and resized, and opens where it was left (the view's script
     * and styles, .sb-floating-panel). A Section's panel starts wider, for its rows and columns.
     */
    /**
     * Adding a block: where (before or after a block, "in" a column, or at the end of the page with no target), then
     * which, from the blocks that can go there; its settings open straight away.
     */
    public function addBlockAction(): Action
    {
        $types = fn (array $arguments): array => $this->blockTypesFor($arguments);

        return Action::make('addBlock')
            ->modalHeading(__('Add a block'))
            ->modalDescription(__('Choose what to add. Its settings open next, and you can move it afterwards.'))
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('Add'))
            ->schema(fn (array $arguments): array => [
                ToggleButtons::make('type')
                    ->hiddenLabel()
                    ->options(array_map(fn (string $blockClass): string => $blockClass::label(), $types($arguments)))
                    ->icons(array_map(fn (string $blockClass): string|\BackedEnum => $blockClass::icon(), $types($arguments)))
                    ->columns(['default' => 2, 'md' => 3])
                    ->required()
                    ->in(array_keys($types($arguments))),
            ])
            ->action(function (array $arguments, array $data) use ($types): void {
                if (! isset($types($arguments)[$data['type'] ?? '']) || $this->isTopLevelChangeRefused(null, $arguments['target'] ?? null, isset($arguments['target']) ? self::position($arguments['position'] ?? null) : 'after')) {
                    $this->cantDoThat();

                    return;
                }

                $uid = Str::lower((string) Str::ulid());
                $target = is_string($arguments['target'] ?? null) ? $arguments['target'] : null;
                $blocks = BlockTree::insert($this->draft()->blocks ?? [], ['uid' => $uid, 'type' => $data['type'], 'data' => []], $target, self::position($arguments['position'] ?? null));

                if ($blocks === null) {
                    Notification::make()->title(__('That block can\'t go there.'))->warning()->send();

                    return;
                }

                $this->changeBlocks($blocks);
                $this->replaceMountedAction('editBlock', ['uid' => $uid]);
            });
    }

    /**
     * The block types that can go where a new block is being added: in a Section's column, only those a Section takes.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, class-string<PageBlock>>
     */
    private function blockTypesFor(array $arguments): array
    {
        $target = is_string($arguments['target'] ?? null) ? BlockTree::locate($this->draft()->blocks ?? [], $arguments['target']) : null;
        $inColumn = $target !== null && ($target['kind'] === 'column' || $target['path'] !== []);
        $types = app(BlockRegistry::class)->offered();

        return $inColumn ? array_diff_key($types, array_flip(SectionBlock::EXCLUDED_BLOCKS)) : $types;
    }

    /**
     * @return 'before'|'after'|'in'
     */
    private static function position(mixed $position): string
    {
        return in_array($position, ['before', 'after', 'in'], true) ? $position : 'after';
    }

    /**
     * Copies a block or row just after it (new uids), from the canvas menu.
     */
    public function duplicateNode(string $uid): void
    {
        $result = $this->isTopLevelChangeRefused($uid) ? null : BlockTree::duplicate($this->draft()->blocks ?? [], $uid);

        $result === null ? $this->cantDoThat() : $this->changeBlocks($result[0]);
    }

    /**
     * Removes a block or row, from the canvas menu. No question asked: undo puts it back.
     */
    public function deleteNode(string $uid): void
    {
        $blocks = $this->isTopLevelChangeRefused($uid) ? null : BlockTree::remove($this->draft()->blocks ?? [], $uid);

        if ($blocks === null) {
            $this->cantDoThat();

            return;
        }

        $this->changeBlocks($blocks);

        Notification::make()->title(__('Deleted'))->body(__('Undo (Ctrl+Z, ⌘Z on a Mac) puts it back.'))->success()->send();
    }

    /**
     * Moves a block or row one step (up or down, or a block in a column to the column on its left or right).
     */
    public function stepNode(string $uid, string $direction): void
    {
        $blocks = in_array($direction, ['up', 'down', 'left', 'right'], true) && ! $this->isTopLevelChangeRefused($uid) ? BlockTree::step($this->draft()->blocks ?? [], $uid, $direction) : null;

        $blocks === null ? $this->cantDoThat() : $this->changeBlocks($blocks);
    }

    /**
     * A drop on the canvas: the block or row before or after another, or into a column (a row: into a Section).
     */
    public function dropNode(string $uid, string $targetUid, string $position): void
    {
        $blocks = $this->isTopLevelChangeRefused($uid, $targetUid, self::position($position)) ? null : BlockTree::move($this->draft()->blocks ?? [], $uid, $targetUid, self::position($position));

        $blocks === null ? $this->cantDoThat() : $this->changeBlocks($blocks);
    }

    /**
     * Adds an empty row (one column) after a row, or at the end of a Section.
     */
    public function addRow(string $uid): void
    {
        $target = BlockTree::locate($this->draft()->blocks ?? [], $uid);
        $row = ['uid' => Str::lower((string) Str::ulid()), 'layout' => 'stacked', 'columns' => [['blocks' => []]]];
        $blocks = match ($target['kind'] ?? null) {
            'row' => BlockTree::insert($this->draft()->blocks ?? [], $row, $uid, 'after'),
            'block' => BlockTree::insert($this->draft()->blocks ?? [], $row, $uid, 'in'),
            default => null,
        };

        $blocks === null ? $this->cantDoThat() : $this->changeBlocks($blocks);
    }

    private function cantDoThat(): void
    {
        Notification::make()->title(__('That can\'t be done there.'))->warning()->send();
    }

    /**
     * Between a row's panel and its columns': saves the open panel into the draft (undo still works), then
     * opens the row's or column's settings in its place. From the row's column list and the column's back arrow.
     */
    public function goToNode(string $uid): void
    {
        $name = match (BlockTree::find($this->draft()->blocks ?? [], $uid)['kind'] ?? null) {
            'row' => 'editRow',
            'column' => 'editColumn',
            default => null,
        };

        if ($name === null) {
            return;
        }

        if ($this->mountedActions === []) {
            $this->mountAction($name, ['uid' => $uid]);

            return;
        }

        // The open panel's own action saves it, then calls continueToNextPanel(). A panel that fails validation stays
        // open with its errors (the exception ends the request here).
        $this->nextPanel = ['name' => $name, 'uid' => $uid];
        $this->callMountedAction();

        // A panel of the same kind can't be swapped in place (see continueToNextPanel()): opened now the saved one has closed.
        if ($this->nextPanel !== null && $this->mountedActions === []) {
            $this->mountAction($this->nextPanel['name'], ['uid' => $this->nextPanel['uid']]);
        }

        $this->nextPanel = null;
    }

    /**
     * At the end of a row's or column's save: opens the panel goToNode() asked for, in its place.
     */
    private function continueToNextPanel(): void
    {
        // Filament can't swap a panel for another of the same kind (it closes the new one as the old one finishes), so
        // that one is opened by goToNode() once this one has closed.
        if ($this->nextPanel === null || $this->nextPanel['name'] === ($this->mountedActions[0]['name'] ?? null)) {
            return;
        }

        $next = $this->nextPanel;
        $this->nextPanel = null;
        $this->replaceMountedAction($next['name'], ['uid' => $next['uid']]);
    }

    /**
     * A column's panel title, with a back arrow to its row's settings.
     */
    public function columnPanelTitle(?string $uid): Htmlable
    {
        $trail = is_string($uid) ? BlockTree::trail($this->draft()->blocks ?? [], $uid) : [];
        $row = count($trail) >= 2 && $trail[count($trail) - 2]['kind'] === 'row' ? $trail[count($trail) - 2]['node'] : null;

        return new HtmlString(view('site-builder::filament.pages.panel-trail', [
            'backUid' => is_string($row['uid'] ?? null) ? $row['uid'] : null,
            'backLabel' => __('Row settings'),
            'title' => __('Column settings'),
        ])->render());
    }

    /**
     * A row's settings (column structure, background, line-up, style) and a list of its columns to open, from the canvas.
     * Changing the layout fits the columns: a column that no longer fits passes its blocks to the last one kept.
     */
    public function editRowAction(): Action
    {
        $row = fn (array $arguments): ?array => is_string($arguments['uid'] ?? null) && (($found = BlockTree::find($this->draft()->blocks ?? [], $arguments['uid']))['kind'] ?? null) === 'row'
            ? $found['node']
            : null;

        return Action::make('editRow')
            ->visible(fn (array $arguments): bool => $row($arguments) !== null)
            ->modalHeading(__('Row settings'))
            ->modalWidth(Width::FourExtraLarge)
            ->extraModalWindowAttributes(['class' => 'sb-floating-panel', 'data-sb-level' => 'row'])
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->closeModalByClickingAway(false)
            ->modalSubmitActionLabel(__('Save changes'))
            ->fillForm(fn (array $arguments): array => array_diff_key($row($arguments) ?? [], ['columns' => true, 'uid' => true]))
            ->schema(fn (): array => SectionBlock::rowSettingsPanel())
            ->action(function (array $arguments, array $data): void {
                $this->changeBlocks(BlockTree::update($this->draft()->blocks ?? [], $arguments['uid'], fn (array $row): array => self::rowWith($row, $data)), $arguments['uid']);
                $this->continueToNextPanel();
            });
    }

    /**
     * A column's settings (background, line-up, space inside, border), from the canvas menu.
     */
    public function editColumnAction(): Action
    {
        $column = fn (array $arguments): ?array => is_string($arguments['uid'] ?? null) && (($found = BlockTree::find($this->draft()->blocks ?? [], $arguments['uid']))['kind'] ?? null) === 'column'
            ? $found['node']
            : null;

        return Action::make('editColumn')
            ->visible(fn (array $arguments): bool => $column($arguments) !== null)
            ->modalHeading(fn (array $arguments): Htmlable => $this->columnPanelTitle($arguments['uid'] ?? null))
            ->modalWidth(Width::FourExtraLarge)
            ->extraModalWindowAttributes(['class' => 'sb-floating-panel', 'data-sb-level' => 'column'])
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->closeModalByClickingAway(false)
            ->modalSubmitActionLabel(__('Save changes'))
            ->fillForm(fn (array $arguments): array => array_diff_key($column($arguments) ?? [], ['blocks' => true, 'uid' => true]))
            ->schema(fn (): array => SectionBlock::columnSettingsPanel())
            ->action(function (array $arguments, array $data): void {
                $this->changeBlocks(BlockTree::update($this->draft()->blocks ?? [], $arguments['uid'], fn (array $column): array => self::columnWith($column, $data)), $arguments['uid']);
                $this->continueToNextPanel();
            });
    }

    public function editBlockAction(): Action
    {
        $found = fn (array $arguments): ?array => is_string($arguments['uid'] ?? null) ? BlockTree::find($this->draft()->blocks ?? [], $arguments['uid']) : null;
        $blockClass = function (array $arguments) use ($found): ?string {
            $node = $found($arguments);

            return $node !== null && $node['kind'] === 'block' && is_string($node['node']['type'] ?? null)
                ? app(BlockRegistry::class)->find($node['node']['type'])
                : null;
        };

        return Action::make('editBlock')
            // Only a block in the draft opens (rows and columns get their own settings later).
            ->visible(fn (array $arguments): bool => $blockClass($arguments) !== null)
            ->modalWidth(fn (array $arguments): Width => ($found($arguments)['node']['type'] ?? null) === SectionBlock::NAME ? Width::SevenExtraLarge : Width::FourExtraLarge)
            ->extraModalWindowAttributes(fn (array $arguments): array => [
                'class' => 'sb-floating-panel',
                'data-sb-wide' => ($found($arguments)['node']['type'] ?? null) === SectionBlock::NAME ? 'true' : null,
                // Its level's colour along the panel's top (SettingsPanel::LEVEL_COLOURS).
                'data-sb-level' => ($found($arguments)['node']['type'] ?? null) === SectionBlock::NAME ? 'section' : 'block',
            ])
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->closeModalByClickingAway(false)
            ->modalHeading(function (array $arguments, Action $action) use ($found, $blockClass): string {
                $class = $blockClass($arguments);
                // The panel's own values once it's open, with its rows from the draft (a Section's title counts them).
                $data = [...(($found($arguments)['node']['data'] ?? null) ?: []), ...($action->getRawData() ?: [])];

                return $class ? $class::editorTitle($data) : __('Edit block');
            })
            ->modalSubmitActionLabel(__('Save changes'))
            // A Section's rows stay out of its panel (they have their own), and they're kept from the draft on save.
            ->fillForm(fn (array $arguments): array => is_array($found($arguments)['node']['data'] ?? null)
                ? (($found($arguments)['node']['type'] ?? null) === SectionBlock::NAME ? array_diff_key($found($arguments)['node']['data'], ['rows' => true]) : $found($arguments)['node']['data'])
                : [])
            ->schema(function (array $arguments) use ($found, $blockClass): array {
                $class = $blockClass($arguments);

                if ($class === null) {
                    return [];
                }

                // A Section's panel holds the Section's own settings only: its rows have their own panels.
                $components = is_a($class, SectionBlock::class, true)
                    ? SectionBlock::visualPanelSchema()
                    : $class::make(isNested: $found($arguments)['isNested'] ?? false)->getDefaultChildComponents();

                return is_array($components) ? $components : [];
            })
            ->action(function (array $arguments, array $data): void {
                $this->changeBlocks(BlockTree::update(
                    $this->draft()->blocks ?? [],
                    $arguments['uid'],
                    fn (array $block): array => [...$block, 'data' => self::withDraftRows($block, $data)],
                ), $arguments['uid']);
            });
    }

    /**
     * A row with its panel's settings. Changing the layout fits the columns: a column that no longer fits passes its
     * blocks to the last one kept.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function rowWith(array $row, array $data): array
    {
        return [
            ...$row,
            ...array_diff_key($data, ['columns' => true, 'uid' => true]),
            'columns' => SectionBlock::fitColumns(is_array($row['columns'] ?? null) ? $row['columns'] : [], SectionBlock::columnCount($data['layout'] ?? $row['layout'] ?? null)),
        ];
    }

    /**
     * A column with its panel's settings (its blocks stay as they are).
     *
     * @param  array<string, mixed>  $column
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function columnWith(array $column, array $data): array
    {
        return [...$column, ...array_diff_key($data, ['blocks' => true, 'uid' => true])];
    }

    /**
     * A Section's panel data with its rows as they are in the draft: the panel doesn't hold them, and they change on
     * their own (add, move, delete, and their own panels).
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withDraftRows(array $block, array $data): array
    {
        return ($block['type'] ?? null) === SectionBlock::NAME
            ? [...$data, 'rows' => is_array($block['data']['rows'] ?? null) ? $block['data']['rows'] : []]
            : $data;
    }

    /**
     * Undo and redo: the draft's last change (VisualDrafts keeps 30), from the toolbar or Ctrl+Z / Ctrl+Y (⌘Z / ⇧⌘Z).
     */
    public function undo(): void
    {
        app(VisualDrafts::class)->undo($this->draft());
        $this->isPreviewing = false;
        $this->refreshCanvas();
    }

    public function redo(): void
    {
        app(VisualDrafts::class)->redo($this->draft());
        $this->isPreviewing = false;
        $this->refreshCanvas();
    }

    public function undoAction(): Action
    {
        return Action::make('undo')
            ->label(__('Undo'))
            ->tooltip(__('Undo the last change (Ctrl+Z, or ⌘Z on a Mac)'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->iconButton()
            ->color('gray')
            ->disabled(fn (): bool => ! app(VisualDrafts::class)->canUndo($this->draft()))
            ->action(fn () => $this->undo());
    }

    public function redoAction(): Action
    {
        return Action::make('redo')
            ->label(__('Redo'))
            ->tooltip(__('Redo what you undid (Ctrl+Y, or ⇧⌘Z on a Mac)'))
            ->icon(Heroicon::OutlinedArrowUturnRight)
            ->iconButton()
            ->color('gray')
            ->disabled(fn (): bool => ! app(VisualDrafts::class)->canRedo($this->draft()))
            ->action(fn () => $this->redo());
    }

    /**
     * Pages › Revisions in the visual builder: each saved version, with Preview and Restore. Restoring puts that
     * version's blocks into the draft (one change, so undo takes it back); nothing is live until Publish.
     */
    public function revisionsAction(): Action
    {
        return Action::make('revisions')
            ->label(__('Revisions'))
            ->tooltip(__('Earlier versions of this page'))
            ->icon(Heroicon::OutlinedClock)
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => $this->record() instanceof Page)
            ->slideOver()
            ->modalHeading(__('Revisions'))
            ->modalDescription(__('Each save that changes the page is kept, newest first (the last :count). Restore puts a version\'s blocks into your draft, to check before you publish; undo takes it back. Its title and search settings are restored in Edit as a form.', ['count' => app(PageRevisions::class)->keep()]))
            ->modalContent(fn (): View => view('site-builder::filament.pages.revisions', [
                'revisions' => $this->page()->revisions()->latest('id')->get(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    public function restoreRevision(int $revisionId): void
    {
        $blocks = $this->page()->revisions()->find($revisionId)?->attributesToRestore()['blocks'] ?? null;

        $this->unmountAction();

        if (! is_array($blocks)) {
            Notification::make()->title(__('That revision no longer exists.'))->danger()->send();

            return;
        }

        $this->changeBlocks($blocks);

        Notification::make()
            ->title(__('Revision loaded'))
            ->body(__('Its blocks are in your draft. Publish to keep them, or undo (Ctrl+Z, ⌘Z on a Mac) to go back.'))
            ->success()
            ->send();
    }

    /**
     * What the style clipboard holds, for the canvas menu, e.g. "Heading text (Call to action)", or null.
     */
    public function styleClipboardLabel(): ?string
    {
        $clipboard = session(StyleClipboard::SESSION_KEY);

        if (! is_array($clipboard) || ! isset(StyleClipboard::GROUPS[$clipboard['group'] ?? ''])) {
            return null;
        }

        $blockClass = is_string($clipboard['type'] ?? null) ? app(BlockRegistry::class)->find($clipboard['type']) : null;

        return __(StyleClipboard::GROUPS[$clipboard['group']]).($blockClass ? ' ('.$blockClass::label().')' : '');
    }

    /**
     * Copies a block's style (all of it or one group) to the style clipboard (StyleClipboard).
     */
    public function copyStyle(string $uid, string $group): void
    {
        $block = $this->styledBlock($uid);

        if ($block === null || ! isset(StyleClipboard::GROUPS[$group])) {
            return;
        }

        session()->put(StyleClipboard::SESSION_KEY, [
            'group' => $group,
            'type' => $block['type'],
            'values' => StyleClipboard::extract($block['data'], StyleClipboard::paths($group, $block['class'])),
        ]);

        $this->dispatch('site-builder-style-copied', label: $this->styleClipboardLabel());

        Notification::make()
            ->title(__('Copied: :style', ['style' => $this->styleClipboardLabel()]))
            ->body(__('Right-click another block and choose Paste style.'))
            ->success()
            ->send();
    }

    /**
     * Pastes the style clipboard on a block: one change, so undo takes it back. "All styles" includes the copied
     * block's own settings only on a block of the same type.
     */
    public function pasteStyle(string $uid): void
    {
        $clipboard = session(StyleClipboard::SESSION_KEY);
        $block = $this->styledBlock($uid);

        if ($block === null || ! is_array($clipboard) || ! isset(StyleClipboard::GROUPS[$clipboard['group'] ?? '']) || ! is_array($clipboard['values'] ?? null)) {
            return;
        }

        $paths = StyleClipboard::paths($clipboard['group'], ($clipboard['type'] ?? null) === $block['type'] ? $block['class'] : null);

        $this->changeBlocks(BlockTree::update(
            $this->draft()->blocks ?? [],
            $uid,
            fn (array $node): array => [...$node, 'data' => StyleClipboard::apply(is_array($node['data'] ?? null) ? $node['data'] : [], $clipboard['values'], $paths)],
        ), $uid);

        Notification::make()
            ->title(__('Pasted: :style', ['style' => $this->styleClipboardLabel()]))
            ->body(__('Undo (Ctrl+Z, ⌘Z on a Mac) takes it back.'))
            ->success()
            ->send();
    }

    /**
     * Gives every other block of the same type on the page this block's style (all of it or one group), e.g. after
     * changing one heading: one change, so undo takes them all back.
     */
    public function applyStyleToAll(string $uid, string $group): void
    {
        $block = $this->styledBlock($uid);

        if ($block === null || ! isset(StyleClipboard::GROUPS[$group])) {
            return;
        }

        $paths = StyleClipboard::paths($group, $block['class']);
        $values = StyleClipboard::extract($block['data'], $paths);
        $count = 0;

        $blocks = BlockTree::map($this->draft()->blocks ?? [], function (array $node) use ($block, $uid, $values, $paths, &$count): array {
            if (($node['type'] ?? null) !== $block['type'] || ($node['uid'] ?? null) === $uid) {
                return $node;
            }

            $count++;

            return [...$node, 'data' => StyleClipboard::apply(is_array($node['data'] ?? null) ? $node['data'] : [], $values, $paths)];
        });

        if ($count === 0) {
            Notification::make()->title(__('There are no other :type blocks on this page.', ['type' => $block['class']::label()]))->info()->send();

            return;
        }

        $this->changeBlocks($blocks);

        Notification::make()
            ->title(trans_choice(':style applied to :count other :type block|:style applied to :count other :type blocks', $count, [
                'style' => __(StyleClipboard::GROUPS[$group]),
                'type' => $block['class']::label(),
            ]))
            ->body(__('Undo (Ctrl+Z, ⌘Z on a Mac) takes it back.'))
            ->success()
            ->send();
    }

    /**
     * A block in the draft whose style can be copied or pasted, with its class. A linked library block is styled in
     * the library, so it isn't one.
     *
     * @return array{type: string, data: array<string, mixed>, class: class-string<PageBlock>}|null
     */
    private function styledBlock(string $uid): ?array
    {
        $found = BlockTree::find($this->draft()->blocks ?? [], $uid);
        $type = $found['node']['type'] ?? null;
        $blockClass = $found !== null && $found['kind'] === 'block' && is_string($type) && $type !== SavedBlockBlock::NAME
            ? app(BlockRegistry::class)->find($type)
            : null;

        return $blockClass === null ? null : [
            'type' => $type,
            'data' => is_array($found['node']['data'] ?? null) ? $found['node']['data'] : [],
            'class' => $blockClass,
        ];
    }

    public function editAsFormAction(): Action
    {
        return Action::make('editAsForm')
            ->label(__('Edit as a form'))
            ->icon(Heroicon::OutlinedListBullet)
            ->color('gray')
            ->tooltip(__('The same page as a list of blocks and their settings. Your unpublished changes stay here until you publish or discard them.'))
            ->url(fn (): string => static::getResource()::getUrl('edit', ['record' => $this->record()]));
    }

    public function discardAction(): Action
    {
        $drafts = app(VisualDrafts::class);

        return Action::make('discard')
            ->label(__('Discard changes'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (): bool => $drafts->hasChanges($this->draft(), $this->record()))
            ->requiresConfirmation()
            ->modalHeading(__('Discard your unpublished changes?'))
            ->modalDescription(__('The page goes back to how it\'s published now. This can\'t be undone.'))
            ->modalSubmitActionLabel(__('Discard changes'))
            ->action(function () use ($drafts): void {
                $drafts->discard($this->draft());
                $this->openDraft = null;

                $this->redirect(static::getResource()::getUrl('visual', ['record' => $this->record()]));
            });
    }

    public function publishAction(): Action
    {
        $drafts = app(VisualDrafts::class);

        return Action::make('publish')
            ->label(fn (): string => $this->publishTexts()['label'])
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->disabled(fn (): bool => ! $drafts->hasChanges($this->draft(), $this->record()))
            ->tooltip(fn (): ?string => $drafts->hasChanges($this->draft(), $this->record()) ? null : __('Nothing to publish yet: make a change first.'))
            ->requiresConfirmation(fn (): bool => $this->publishTexts()['warning'] !== null)
            ->modalHeading(fn (): string => $this->publishTexts()['label'].'?')
            ->modalDescription(fn (): ?string => $this->publishTexts()['warning'])
            ->modalSubmitActionLabel(fn (): string => $this->publishTexts()['label'])
            ->action(function () use ($drafts): void {
                $drafts->publish($this->draft(), $this->record());
                $this->openDraft = null;
                $texts = $this->publishTexts();

                Notification::make()->title($texts['title'])->body($texts['body'])->success()->send();
            });
    }

    /**
     * The page (or, in the library's editor, the library item) being edited.
     */
    protected function record(): Page|SavedBlock|Template
    {
        $record = $this->getRecord();
        abort_unless($record instanceof Page || $record instanceof SavedBlock || $record instanceof Template, 404);

        return $record;
    }

    protected function page(): Page
    {
        $page = $this->getRecord();
        abort_unless($page instanceof Page, 404);

        return $page;
    }
}
