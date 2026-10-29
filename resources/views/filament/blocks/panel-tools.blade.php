{{--
    The top of a block's panel: search the options (every tab, opening the toggles that
    match), show only the toggles with changed settings, and in the visual builder's floating panel, expand it or snap
    it to the left or right of the window. Dynamic content puts a tag ({{ page_title }}, DynamicContent) into the text
    setting last clicked; rich text has its own tags button. wire:ignore keeps it (and what's typed) through Livewire updates; the filter
    is put back on the panel after each one.
--}}
<div
    wire:ignore
    class="sb-panel-tools"
    x-data="{
        query: '',
        onlyModified: false,
        expanded: false,
        unhook: null,
        lastField: null,
        init() {
            this.$nextTick(() => this.apply());
            this.panel().addEventListener('focusin', (event) => {
                if (event.target.matches?.('input[type=text]:not([type=search]), textarea')) {
                    this.lastField = event.target;
                }
            });
            this.unhook = window.Livewire?.hook?.('commit', ({ succeed }) => succeed(() => window.requestAnimationFrame(() => this.apply())));
        },
        destroy() {
            typeof this.unhook === 'function' && this.unhook();
        },
        panel() {
            return this.$el.closest('.fi-modal-window') ?? this.$el.parentElement;
        },
        apply() {
            // Its panel has closed: nothing to filter (and nothing to fail on).
            if (! this.$el.isConnected) {
                return;
            }

            const query = this.query.trim().toLowerCase();
            const panel = this.panel();

            this.$el.toggleAttribute('data-searching', query !== '');
            this.$el.toggleAttribute('data-only-modified', this.onlyModified);

            panel.querySelectorAll('[data-sb-toggle]').forEach((toggle) => {
                const title = (toggle.querySelector('.fi-section-header-heading')?.textContent ?? '').toLowerCase();
                const isTitleMatch = query !== '' && title.includes(query);
                let hasMatch = false;

                // Each setting (its grid cell, so no gap is left), then any layout around settings that has none left.
                toggle.querySelectorAll('.fi-fo-field').forEach((field) => {
                    const label = (field.querySelector('.fi-fo-field-label-content')?.textContent ?? '').toLowerCase();
                    const isMatch = query === '' || isTitleMatch || label.includes(query);

                    (field.closest('.fi-grid-col') ?? field).toggleAttribute('data-sb-no-match', ! isMatch);
                    hasMatch ||= isMatch;
                });

                [...toggle.querySelectorAll('.fi-grid-col, .fi-section')].reverse().forEach((element) => {
                    if (element.closest('[data-sb-toggle]') !== toggle || ! element.querySelector('.fi-fo-field')) {
                        return;
                    }

                    const hasVisible = [...element.querySelectorAll('.fi-fo-field')].some((field) => ! field.closest('[data-sb-no-match]'));

                    element.toggleAttribute('data-sb-no-match', query !== '' && ! hasVisible);
                });

                toggle.toggleAttribute('data-sb-no-match', query !== '' && ! hasMatch && ! isTitleMatch);
            });
        },
        insertTag(name) {
            // Built in parts: two braces together here would be read as Blade.
            const tag = '{' + '{ ' + name + ' }' + '}';
            const field = this.lastField;

            if (! field?.isConnected) {
                navigator.clipboard?.writeText(tag);
                new FilamentNotification().title(@js(__('Tag copied. Paste it into a text setting.'))).success().send();

                return;
            }

            const start = field.selectionStart ?? field.value.length;
            const end = field.selectionEnd ?? start;

            field.value = field.value.slice(0, start) + tag + field.value.slice(end);
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            field.focus();
            field.setSelectionRange?.(start + tag.length, start + tag.length);
        },
        snap(side) {
            const root = document.documentElement;

            root.style.setProperty('--sb-panel-top', '0.75rem');
            root.style.setProperty('--sb-panel-left', side === 'left' ? '0.75rem' : 'auto');
            root.style.setProperty('--sb-panel-right', side === 'right' ? '0.75rem' : 'auto');
            this.expanded = false;
        },
    }"
    x-effect="query; onlyModified; apply()"
    x-bind:data-expanded="expanded ? '' : null"
    x-bind:data-hover-mode="$store.sbDevice?.current === 'hover' ? '' : null"
>
    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" class="sb-panel-tools-search">
        <x-filament::input
            type="search"
            x-model.debounce.150ms="query"
            :placeholder="__('Search options')"
            :aria-label="__('Search options')"
        />
    </x-filament::input.wrapper>

    {{-- Which values the panel edits: a screen size, or Hover (while the block is pointed at). --}}
    <div class="sb-panel-tools-buttons" role="group" aria-label="{{ __('Edit the values for') }}">
        @foreach (['desktop' => ['heroicon-m-computer-desktop', __('Desktop')], 'tablet' => ['heroicon-m-device-tablet', __('Tablet and smaller')], 'phone' => ['heroicon-m-device-phone-mobile', __('Phone')], 'hover' => ['heroicon-m-cursor-arrow-rays', __('Hover: while the block is pointed at')]] as $state => [$icon, $label])
            <x-filament::icon-button
                :icon="$icon"
                color="gray"
                size="sm"
                :label="$label"
                :tooltip="$label"
                x-on:click="$store.sbDevice.current = {{ \Illuminate\Support\Js::from($state) }}"
                x-bind:aria-pressed="($store.sbDevice?.current === {{ \Illuminate\Support\Js::from($state) }}).toString()"
                x-bind:class="$store.sbDevice?.current === {{ \Illuminate\Support\Js::from($state) }} && 'sb-panel-tools-on'"
            />
        @endforeach
    </div>

    <div class="sb-panel-tools-buttons">
        <x-filament::dropdown placement="bottom-end" :teleport="true">
            <x-slot name="trigger">
                <x-filament::icon-button
                    icon="heroicon-m-variable"
                    color="gray"
                    size="sm"
                    :label="__('Dynamic content')"
                    :tooltip="__('Dynamic content: a value filled in when the page is shown, put into the text setting you last clicked')"
                />
            </x-slot>

            <x-filament::dropdown.list>
                @foreach (\ScBudgetWeb\SiteBuilder\Blocks\DynamicContent::labels() as $name => $label)
                    <x-filament::dropdown.list.item x-on:click="insertTag({{ \Illuminate\Support\Js::from($name) }}); close()">
                        {{ $label }} <span class="sb-panel-tools-tag">{!! e('{'.'{ '.$name.' }'.'}') !!}</span>
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
        <x-filament::icon-button
            icon="heroicon-m-funnel"
            color="gray"
            size="sm"
            :label="__('Show only modified settings')"
            :tooltip="__('Show only modified settings')"
            x-on:click="onlyModified = ! onlyModified"
            x-bind:aria-pressed="onlyModified"
            x-bind:class="onlyModified && 'sb-panel-tools-on'"
        />
        <span class="sb-panel-tools-floating">
            <x-filament::icon-button
                icon="heroicon-m-arrow-left-end-on-rectangle"
                color="gray"
                size="sm"
                :label="__('Snap to the left')"
                :tooltip="__('Snap to the left')"
                x-on:click="snap('left')"
            />
            <x-filament::icon-button
                icon="heroicon-m-arrow-right-end-on-rectangle"
                color="gray"
                size="sm"
                :label="__('Snap to the right')"
                :tooltip="__('Snap to the right')"
                x-on:click="snap('right')"
            />
            <x-filament::icon-button
                icon="heroicon-m-arrows-pointing-out"
                color="gray"
                size="sm"
                :label="__('Expand')"
                :tooltip="__('Expand or shrink the panel')"
                x-on:click="expanded = ! expanded"
                x-bind:aria-pressed="expanded"
                x-bind:class="expanded && 'sb-panel-tools-on'"
            />
        </span>
    </div>

    <style>
        .sb-panel-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; }
        .sb-panel-tools-search { flex: 1 1 12rem; }
        .sb-panel-tools-buttons { display: flex; align-items: center; gap: 0.125rem; }
        .sb-panel-tools-floating { display: none; align-items: center; gap: 0.125rem; }
        .sb-floating-panel .sb-panel-tools-floating { display: flex; }
        .sb-panel-tools-tag { margin-inline-start: 0.375rem; font-family: ui-monospace, monospace; font-size: 0.75rem; opacity: 0.6; }
        .sb-panel-tools-on { color: var(--primary-600); background: color-mix(in oklab, var(--primary-500) 12%, transparent); }

        /* A toggle with changed settings gets a dot by its title. */
        [data-sb-toggle][data-sb-modified] > .fi-section-header .fi-section-header-heading::after {
            content: ''; display: inline-block; width: 0.4rem; height: 0.4rem; margin-inline-start: 0.4rem; vertical-align: middle;
            border-radius: 9999px; background: var(--primary-500);
        }

        /* Searching: every tab and toggle open, and only what matches shown. */
        .fi-modal-window:has(.sb-panel-tools[data-searching]) .fi-sc-tabs > .fi-tabs { display: none; }
        .fi-modal-window:has(.sb-panel-tools[data-searching]) .fi-sc-tabs-tab { display: block !important; }
        .fi-modal-window:has(.sb-panel-tools[data-searching]) [data-sb-toggle] .fi-section-content-ctn { display: block !important; visibility: visible !important; height: auto !important; }
        .fi-modal-window:has(.sb-panel-tools[data-searching]) [data-sb-tab-note],
        .fi-modal-window:has(.sb-panel-tools[data-only-modified]) [data-sb-tab-note] { display: none; }
        .fi-modal-window:has(.sb-panel-tools[data-searching]) [data-sb-no-match] { display: none !important; }

        /* Show only modified: toggles without changes are hidden, and every tab shows. */
        .fi-modal-window:has(.sb-panel-tools[data-only-modified]) .fi-sc-tabs > .fi-tabs { display: none; }
        .fi-modal-window:has(.sb-panel-tools[data-only-modified]) .fi-sc-tabs-tab { display: block !important; }
        .fi-modal-window:has(.sb-panel-tools[data-only-modified]) [data-sb-toggle]:not([data-sb-modified]) { display: none !important; }

        /* Hover state: settings that can't change on hover are dimmed; the rest show their hover value. */
        .fi-modal-window:has(.sb-panel-tools[data-hover-mode]) [data-sb-toggle] .fi-fo-field:not([data-sb-hoverable]) { opacity: 0.35; pointer-events: none; }

        .fi-modal-window.sb-floating-panel:has(.sb-panel-tools[data-expanded]) {
            top: 0.75rem !important; left: 0.75rem !important; right: 0.75rem !important;
            width: auto !important; max-height: calc(100dvh - 1.5rem) !important;
        }
    </style>
</div>
