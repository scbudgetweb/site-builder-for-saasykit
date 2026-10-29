{{--
    BoxSides: four numbers (sides or corners) and a unit, for the screen size being edited, with a link button that
    makes them all the same (sbBoxSides, in the design-fields script).
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="sbBoxSides({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            units: @js($getUnits()),
            parts: @js($getParts()),
            responsive: @js($isResponsive()),
        })"
        style="display: flex; flex-direction: column; gap: 0.5rem;"
    >
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
            @include('site-builder::filament.forms.device-switch', ['responsive' => $isResponsive()])

            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button
                    type="button"
                    x-on:click="toggleLinked()"
                    x-bind:aria-pressed="linked.toString()"
                    x-bind:title="linked ? @js(__('Linked: one number for all four')) : @js(__('Set each one separately'))"
                    x-bind:style="linked ? 'background: rgb(128 128 128 / 0.2); opacity: 1;' : 'opacity: 0.55;'"
                    style="display: inline-flex; padding: 6px; border-radius: 6px;"
                >
                    <x-filament::icon icon="heroicon-m-link" style="width: 16px; height: 16px;" />
                    <span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">{{ __('Same for all four') }}</span>
                </button>

                @if (count($getUnits()) > 1)
                    <x-filament::input.wrapper>
                        <x-filament::input.select x-model="unit" x-on:change="write()" :aria-label="__('Unit')">
                            @foreach ($getUnits() as $unit)
                                <option value="{{ $unit }}">{{ $unit }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @else
                    <span style="font-size: 0.875rem; opacity: 0.7;">{{ $getUnits()[0] ?? '' }}</span>
                @endif
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(4.5rem, 1fr)); gap: 0.5rem;">
            @foreach ($getPartLabels() as $part => $label)
                <label style="display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.75rem; opacity: 0.85;">
                    <span>{{ $label }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="number"
                            inputmode="decimal"
                            :id="$part === $getParts()[0] ? $getId() : null"
                            :step="$getStep()"
                            :min="$getMinValue()"
                            :max="$getMaxValue()"
                            x-model="numbers['{{ $part }}']"
                            x-on:input="write('{{ $part }}')"
                            x-bind:placeholder="fallback('{{ $part }}')"
                        />
                    </x-filament::input.wrapper>
                </label>
            @endforeach
        </div>
    </div>
</x-dynamic-component>
