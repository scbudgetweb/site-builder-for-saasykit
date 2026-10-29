{{-- DeviceValue: a number and a unit, for the screen size being edited (sbDeviceValue, in the design-fields script). --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="sbDeviceValue({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            units: @js($getUnits()),
            responsive: @js($isResponsive()),
        })"
        style="display: flex; align-items: center; gap: 0.5rem;"
    >
        @include('site-builder::filament.forms.device-switch', ['responsive' => $isResponsive()])

        <x-filament::input.wrapper style="flex: 1 1 auto; min-width: 5rem;">
            <x-filament::input
                type="number"
                :id="$getId()"
                inputmode="decimal"
                :step="$getStep()"
                :min="$getMinValue()"
                :max="$getMaxValue()"
                x-model="number"
                x-on:input="write()"
                x-bind:placeholder="fallback()"
            />
        </x-filament::input.wrapper>

        @if (count($getUnits()) > 1)
            <x-filament::input.wrapper style="flex: 0 0 auto;">
                <x-filament::input.select x-model="unit" x-on:change="write()" :aria-label="__('Unit')">
                    @foreach ($getUnits() as $unit)
                        <option value="{{ $unit }}">{{ $unit === '' ? __('×') : $unit }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @elseif (($getUnits()[0] ?? '') !== '')
            <span style="font-size: 0.875rem; opacity: 0.7;">{{ $getUnits()[0] }}</span>
        @endif
    </div>
</x-dynamic-component>
