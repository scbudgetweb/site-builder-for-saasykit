{{--
    MediaPicker: one file from the media library, as a grid of thumbnails (newest first) with a search and, for more than
    one kind, a filter by kind. Pages load from the field's getMediaForJs(); the chosen file's path is the state.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            search: '',
            kind: '',
            items: [],
            page: 1,
            hasMore: false,
            loading: true,
            async load(more = false) {
                this.loading = true
                this.page = more ? this.page + 1 : 1
                const result = await $wire.callSchemaComponentMethod(@js($getKey()), 'getMediaForJs', { search: this.search, kind: this.kind, page: this.page })
                this.items = more ? [...this.items, ...(result?.items ?? [])] : (result?.items ?? [])
                this.hasMore = result?.hasMore ?? false
                this.loading = false
            },
        }"
        x-init="load()"
        class="flex flex-col gap-3"
    >
        <div class="flex flex-wrap items-center gap-2">
            <div class="min-w-48 flex-1">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="search" x-model.debounce.400ms="search" x-on:input.debounce.400ms="load()" :placeholder="__('Search by name')" />
                </x-filament::input.wrapper>
            </div>
            @if ($getKindFilters() !== [])
                <x-filament::tabs :contained="false">
                    <x-filament::tabs.item alpine-active="kind === ''" x-on:click="kind = ''; load()">{{ __('All') }}</x-filament::tabs.item>
                    @foreach ($getKindFilters() as $kind => $label)
                        <x-filament::tabs.item :alpine-active="'kind === ' . \Illuminate\Support\Js::from($kind)" x-on:click="kind = {{ \Illuminate\Support\Js::from($kind) }}; load()">{{ $label }}</x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            @endif
        </div>

        <div class="grid max-h-[26rem] grid-cols-3 gap-3 overflow-y-auto p-0.5 sm:grid-cols-4 md:grid-cols-5" role="listbox" aria-label="{{ __('Files in the library') }}">
            <template x-for="item in items" x-bind:key="item.path">
                <button
                    type="button"
                    role="option"
                    x-on:click="state = item.path"
                    x-bind:aria-selected="state === item.path"
                    x-bind:title="item.name"
                    class="group flex min-w-0 flex-col gap-1 rounded-lg p-1 text-left outline-none ring-1 ring-transparent transition focus-visible:ring-2 focus-visible:ring-primary-500"
                    x-bind:class="state === item.path ? 'bg-primary-50 ring-2 ring-primary-500 dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/5'"
                >
                    <span class="relative block aspect-square w-full overflow-hidden rounded-md bg-gray-100 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <template x-if="item.kind === 'image' || item.kind === 'svg'">
                            <img x-bind:src="item.url" alt="" loading="lazy" class="h-full w-full" x-bind:class="item.kind === 'image' ? 'object-cover' : 'object-contain p-[12%]'">
                        </template>
                        <template x-if="item.kind !== 'image' && item.kind !== 'svg'">
                            <span class="flex h-full w-full items-center justify-center text-gray-400">
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedMusicalNote" class="h-1/3 w-1/3" />
                            </span>
                        </template>
                        <span x-show="state === item.path" class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-primary-600 text-white shadow">
                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::Check" class="h-4 w-4" />
                        </span>
                    </span>
                    <span class="truncate text-xs font-medium text-gray-950 dark:text-white" x-text="item.name"></span>
                    <span class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="item.summary"></span>
                </button>
            </template>
        </div>

        <p x-show="! loading && items.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
            <span x-show="search === '' && kind === ''">{{ __('Nothing in the library yet. Upload a file below, and it can be chosen anywhere.') }}</span>
            <span x-show="search !== '' || kind !== ''">{{ __('Nothing matches. Try another name.') }}</span>
        </p>

        <div x-show="hasMore" class="flex justify-center">
            <x-filament::button color="gray" size="sm" x-on:click="load(true)" x-bind:disabled="loading">{{ __('Show more') }}</x-filament::button>
        </div>
    </div>
</x-dynamic-component>
