{{-- SiteColour: a colour, see-through or global (sbSiteColour, in the design-fields script). --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="sbSiteColour({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            globals: @js($getGlobalColours()),
            alpha: @js($hasAlpha()),
            pickerSrc: @js($getPickerSrc()),
            deletedLabel: @js(__('Deleted global colour')),
        })"
        x-on:keydown.esc="if (open) { open = false; $event.stopPropagation() }"
        class="sb-colour"
    >
        <x-filament::input.wrapper :disabled="$isDisabled()">
            <div class="sb-colour-row">
                <button
                    type="button"
                    class="sb-colour-dot"
                    x-bind:class="{ 'sb-colour-dot-empty': ! state }"
                    x-bind:style="{ '--sb-colour-preview': preview() }"
                    x-on:click="toggle()"
                    x-bind:aria-expanded="open"
                    aria-label="{{ __('Choose a colour') }}"
                    @disabled($isDisabled())
                ></button>

                <template x-if="global()">
                    <span class="sb-colour-global">
                        <x-filament::icon icon="heroicon-m-globe-alt" class="sb-colour-global-icon" />
                        <span x-text="global().name"></span>
                        <button type="button" class="sb-colour-unlink" x-on:click="unlink()" title="{{ __('Use this colour\'s value instead, so changing the global colour no longer changes this block.') }}">{{ __('Unlink') }}</button>
                    </span>
                </template>

                <input
                    x-show="! global()"
                    type="text"
                    id="{{ $getId() }}"
                    class="fi-input"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="{{ $getPlaceholder() ?? '#' }}"
                    x-model="text"
                    x-on:change="typed()"
                    x-on:focus="show()"
                    @disabled($isDisabled())
                />

                <button type="button" class="sb-colour-clear" x-show="state" x-cloak x-on:click="clear()" aria-label="{{ __('Clear') }}">
                    <x-filament::icon icon="heroicon-m-x-mark" class="sb-colour-clear-icon" />
                </button>
            </div>
        </x-filament::input.wrapper>

        <div x-show="open" x-cloak x-on:click.outside="open = false" class="sb-colour-panel">
            <div x-ref="host" class="sb-colour-picker" wire:ignore></div>

            <template x-if="globals.length">
                <div>
                    <p class="sb-colour-label">{{ __('Global colours') }}</p>
                    <div class="sb-colour-swatches">
                        <template x-for="colour in globals" x-bind:key="colour.id">
                            <button
                                type="button"
                                class="sb-colour-swatch"
                                x-bind:style="{ '--sb-colour-preview': colour.preview }"
                                x-bind:title="colour.name"
                                x-bind:aria-label="colour.name"
                                x-bind:aria-pressed="state === 'gc:' + colour.id"
                                x-on:click="pickGlobal(colour.id)"
                            ></button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-dynamic-component>
