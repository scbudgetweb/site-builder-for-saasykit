{{--
    A column panel's title: a back arrow to its row's settings (VisualEditor::goToNode() saves the column
    first), then the title. $backUid: the row's uid, or null; $backLabel; $title.
--}}
<span class="sb-panel-trail">
    @if ($backUid)
        <button
            type="button"
            class="sb-panel-trail-back"
            wire:click="goToNode({{ \Illuminate\Support\Js::from($backUid) }})"
            title="{{ __('Back to :level (saves this one)', ['level' => $backLabel]) }}"
            aria-label="{{ __('Back to :level', ['level' => $backLabel]) }}"
        >
            <x-filament::icon icon="heroicon-m-arrow-uturn-left" class="sb-panel-trail-icon" />
        </button>
    @endif

    <span>{{ $title }}</span>
</span>
