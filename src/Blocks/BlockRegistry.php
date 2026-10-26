<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\CreatePage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Every block, in the order they appear in the block picker: the core blocks, then the app's own
 * (SiteBuilder::blocks()). An app block with the same name as a core block replaces it.
 */
class BlockRegistry
{
    /**
     * @var array<int, class-string<PageBlock>>
     */
    public const CORE_BLOCKS = [
        // Layout and content
        Core\SectionBlock::class,
        Core\HeroBlock::class,
        Core\BannerBlock::class,
        Core\TextBlock::class,
        Core\ImageTextBlock::class,
        Core\ImageBlock::class,
        Core\FeaturesBlock::class,
        Core\ImageCardsBlock::class,
        Core\StepsBlock::class,
        Core\StatsBlock::class,
        Core\CallToActionBlock::class,
        Core\ButtonsBlock::class,
        Core\TestimonialsBlock::class,
        Core\TeamBlock::class,
        Core\FaqBlock::class,
        Core\TabsBlock::class,
        // Media
        Core\GalleryBlock::class,
        Core\VideoBlock::class,
        Core\LogoStripBlock::class,
        Core\SocialFollowBlock::class,
        Core\DividerBlock::class,
        // Block library
        Core\SavedBlockBlock::class,
    ];

    public function __construct(private SiteBuilder $siteBuilder) {}

    /**
     * @return array<string, class-string<PageBlock>> name => class
     */
    public function all(): array
    {
        $blocks = [];

        foreach ([...self::CORE_BLOCKS, ...$this->siteBuilder->registeredBlocks()] as $block) {
            $blocks[$block::name()] = $block;
        }

        return $blocks;
    }

    /**
     * @return class-string<PageBlock>|null
     */
    public function find(string $name): ?string
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * A block type's name for people, e.g. "Call to action" for "call_to_action".
     */
    public function labelFor(string $type): string
    {
        $block = $this->find($type);

        return $block === null ? $type : $block::label();
    }

    /**
     * @return array<int, Block>
     */
    public function builderBlocks(): array
    {
        return array_values(array_map(fn (string $block): Block => $block::make(), $this->all()));
    }

    /**
     * The blocks a Section's rows can hold (everything except Hero, Banner and Section), with section-friendly
     * defaults: no extra spacing, and the full width of their column.
     *
     * @return array<int, Block>
     */
    public function sectionBuilderBlocks(): array
    {
        return array_values(array_map(
            fn (string $block): Block => $block::make(isNested: true),
            array_filter($this->all(), fn (string $block): bool => ! in_array($block::name(), Core\SectionBlock::EXCLUDED_BLOCKS, true)),
        ));
    }

    /**
     * The page editor's Builder: block cards (previews) that open the block's settings in a slide-over titled with
     * the block, e.g. "Call to action: Get started". Adding a block opens its settings in the same panel. A block in
     * a Section's row opens a second panel over the section's. On the page editor, each block card also has Save to
     * library and, on a linked block, Make an editable copy.
     *
     * @param  array<int, Block>|null  $blocks  the blocks to offer (all of them by default)
     */
    public function editor(Builder $builder, ?array $blocks = null): Builder
    {
        return $builder
            ->blocks($blocks ?? $this->builderBlocks())
            ->blockPreviews()
            ->blockPickerColumns(['md' => 2, 'lg' => 3])
            ->blockPickerWidth('3xl')
            ->collapsible()
            ->cloneable()
            ->deleteAction(fn (Action $action): Action => $action->requiresConfirmation())
            ->editAction(fn (Action $action): Action => $this->slideOver($action)
                ->modalHeading(function (array $arguments, Builder $component): string {
                    $item = $component->getRawState()[$arguments['item']] ?? [];
                    $block = is_string($item['type'] ?? null) ? $this->find($item['type']) : null;

                    return $block ? $block::editorTitle(is_array($item['data'] ?? null) ? $item['data'] : []) : __('Edit block');
                }))
            ->addAction(fn (Action $action): Action => $this->slideOver($action)->modalHeading(fn (array $arguments): string => $this->addHeading($arguments)))
            ->addBetweenAction(fn (Action $action): Action => $this->slideOver($action)->modalHeading(fn (array $arguments): string => $this->addHeading($arguments)))
            ->extraItemActions($this->libraryItemActions())
            ->default([]);
    }

    /**
     * Block card actions for the block library, on the page editor only (not in the library's own editor): Save to
     * library (optionally swapping the block for a linked one) and, on a linked block, Make an editable copy.
     *
     * @return array<int, Action>
     */
    private function libraryItemActions(): array
    {
        $isPageEditor = fn (mixed $livewire): bool => $livewire instanceof EditPage || $livewire instanceof CreatePage;
        $itemType = fn (array $arguments, Builder $component): ?string => $component->getRawState()[$arguments['item'] ?? '']['type'] ?? null;

        return [
            Action::make('saveToLibrary')
                ->label(__('Save to library'))
                ->tooltip(__('Save to library'))
                ->icon(Heroicon::OutlinedBookmark)
                ->iconButton()
                ->color('gray')
                // Creating a library block needs the library's own create permission, not just the page's update one.
                ->authorize(fn (): bool => Gate::allows('create', SavedBlock::class))
                ->modalHeading(__('Save to the block library'))
                ->modalSubmitActionLabel(__('Save to library'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->helperText(__('How it shows in the library, e.g. "Contact form" or "Partner logos".'))
                        ->required()
                        ->maxLength(100),
                    Toggle::make('link')
                        ->label(__('Link this block to the library'))
                        ->helperText(__('Then editing it in the library updates it here and everywhere else it\'s used. Off: the library gets a copy and this block stays as it is.'))
                        ->default(true),
                ])
                ->fillForm(function (array $arguments, Builder $component): array {
                    $item = $component->getRawState()[$arguments['item']] ?? [];
                    $block = is_string($item['type'] ?? null) ? $this->find($item['type']) : null;

                    return ['name' => $block ? Str::limit($block::editorTitle(is_array($item['data'] ?? null) ? $item['data'] : []), 100, '') : '', 'link' => true];
                })
                ->action(function (array $arguments, array $data, Builder $component): void {
                    $key = $arguments['item'];
                    $type = $component->getRawState()[$key]['type'];
                    // Validated first (getItemState), so the library never holds a broken block.
                    $savedBlock = app(BlockLibrary::class)->save($data['name'], $type, $component->getItemState($key));

                    if ($data['link']) {
                        $this->replaceItem($component, $key, ['type' => Core\SavedBlockBlock::NAME, 'data' => ['saved_block_id' => $savedBlock->getKey()]]);
                    }

                    Notification::make()
                        ->title(__('Saved to the block library'))
                        ->body(__('Save the page to keep the change on this page.'))
                        ->success()
                        ->send();
                })
                ->visible(fn (array $arguments, Builder $component, mixed $livewire): bool => $isPageEditor($livewire)
                    && ! in_array($itemType($arguments, $component), [null, Core\SavedBlockBlock::NAME], true)),
            Action::make('copySavedBlock')
                ->label(__('Make an editable copy'))
                ->tooltip(__('Make an editable copy'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->iconButton()
                ->color('gray')
                ->requiresConfirmation()
                // Copying reads the library block's content.
                ->authorize(fn (): bool => Gate::allows('viewAny', SavedBlock::class))
                ->modalHeading(__('Make an editable copy?'))
                ->modalDescription(__('The block is replaced by a copy of the library block, which you can change for this page only. It stops updating from the library.'))
                ->modalSubmitActionLabel(__('Make a copy'))
                ->action(function (array $arguments, Builder $component): void {
                    $key = $arguments['item'];
                    $copy = app(BlockLibrary::class)->copy($component->getRawState()[$key]['data']['saved_block_id'] ?? null);

                    // In a Section's row, only blocks that can go in one (the link was offered on the same terms).
                    if ($copy === null || ($component->getBlock($copy['type']) === null)) {
                        Notification::make()->title(__('That library block no longer exists, or can\'t go here.'))->danger()->send();

                        return;
                    }

                    $this->replaceItem($component, $key, $copy);
                })
                ->visible(fn (array $arguments, Builder $component, mixed $livewire): bool => $isPageEditor($livewire)
                    && $itemType($arguments, $component) === Core\SavedBlockBlock::NAME),
        ];
    }

    /**
     * Swaps a builder item for another block in the same place, under a new key so Filament builds the new block's
     * form.
     *
     * @param  array{type: string, data: array<string, mixed>}  $item
     */
    private function replaceItem(Builder $component, string $key, array $item): void
    {
        $newKey = $component->generateUuid() ?? (string) Str::uuid();
        $items = [];

        foreach ($component->getRawState() as $existingKey => $existing) {
            $items[$existingKey === $key ? $newKey : $existingKey] = $existingKey === $key ? $item : $existing;
        }

        $component->rawState($items);
        $component->getChildSchema($newKey)->fill($item['data']);
        $component->callAfterStateUpdated();
    }

    /**
     * The block panel: a wide slide-over whose footer also has Save page and Preview (on the page editor, which
     * provides saveFromEditor()). They run in the browser, so they don't open another dialog.
     */
    private function slideOver(Action $action): Action
    {
        $isPageEditor = fn (mixed $livewire): bool => $livewire instanceof EditPage;

        return $action
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->extraModalFooterActions([
                Action::make('savePage')
                    ->label(__('Save page'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->alpineClickHandler('$wire.saveFromEditor()')
                    ->visible($isPageEditor),
                Action::make('previewPage')
                    ->label(__('Preview'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    // One named tab per page; the editor reloads it after each save.
                    ->alpineClickHandler(fn (EditPage $livewire): string => 'window.siteBuilderPreview = window.open('
                        .Js::from($livewire->getRecord()->previewUrl()).', '.Js::from('site-builder-preview-'.$livewire->getRecord()->getKey()).')')
                    ->visible($isPageEditor),
            ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function addHeading(array $arguments): string
    {
        $block = is_string($arguments['block'] ?? null) ? $this->find($arguments['block']) : null;

        return $block ? __('Add :block', ['block' => $block::label()]) : __('Add block');
    }
}
