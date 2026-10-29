{{--
    A row's columns in its panel (one for a one-column row): each opens its own settings in the row's place (VisualEditor::goToNode()
    saves the row first), and the column's panel has a back arrow to the row. Drawn from the draft, with the column
    structure chosen in the panel, so a column added by a new structure shows (it can be opened once it's saved).
--}}
@php
    use ScBudgetWeb\SiteBuilder\Blocks\BlockTree;
    use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;

    $livewire = $getLivewire();
    $uid = $livewire->mountedActions[0]['arguments']['uid'] ?? null;
    $found = is_string($uid) && method_exists($livewire, 'draft') ? BlockTree::find($livewire->draft()->blocks ?? [], $uid) : null;
    $layout = $livewire->mountedActions[0]['data']['layout'] ?? ($found['node']['layout'] ?? null);
    $columns = $found !== null
        ? SectionBlock::fitColumns(is_array($found['node']['columns'] ?? null) ? $found['node']['columns'] : [], SectionBlock::columnCount($layout))
        : [];
@endphp

<div class="sb-structure">
    @if ($found === null)
        <p class="sb-structure-empty">{{ __('Save the page first.') }}</p>
    @else
        @foreach ($columns as $column)
            @php
                $columnUid = is_string($column['uid'] ?? null) ? $column['uid'] : null;
                $blockCount = is_array($column['blocks'] ?? null) ? count($column['blocks']) : 0;
            @endphp

            <button
                type="button"
                class="sb-structure-column"
                @if ($columnUid) wire:click="goToNode({{ \Illuminate\Support\Js::from($columnUid) }})" @else disabled @endif
                title="{{ $columnUid ? __('Open this column\'s settings (saves the row)') : __('Save the row to set up this column') }}"
            >
                <x-filament::icon icon="heroicon-m-cog-6-tooth" class="sb-structure-icon" />
                <span class="sb-structure-label">{{ is_string($column['admin_label'] ?? null) && filled($column['admin_label']) ? $column['admin_label'] : __('Column :number', ['number' => $loop->iteration]) }}</span>
                <span class="sb-structure-muted">{{ $columnUid ? trans_choice('{0} No blocks|{1} 1 block|[2,*] :count blocks', $blockCount) : __('New') }}</span>
            </button>
        @endforeach
    @endif
</div>
