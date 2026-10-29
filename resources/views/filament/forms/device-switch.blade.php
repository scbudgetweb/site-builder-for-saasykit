{{--
    Which value a design field is editing: desktop, tablet or phone (when $responsive), or hover, shared by every such
    field on the page ($store.sbDevice). A dot marks one with its own value. Used inside an x-data that has has(device).
--}}
<div role="group" aria-label="{{ __('Screen size') }}" style="display: inline-flex; flex-shrink: 0; gap: 2px;">
    @foreach ([
        ...($responsive ?? true ? ['desktop' => ['heroicon-m-computer-desktop', __('Desktop')], 'tablet' => ['heroicon-m-device-tablet', __('Tablet and smaller')], 'phone' => ['heroicon-m-device-phone-mobile', __('Phone')]] : ['desktop' => ['heroicon-m-stop', __('Normal')]]),
        'hover' => ['heroicon-m-cursor-arrow-rays', __('Hover: while the block is pointed at')],
    ] as $device => [$icon, $label])
        <button
            type="button"
            title="{{ $label }}"
            aria-label="{{ $label }}"
            x-on:click="$store.sbDevice.current = @js($device)"
            x-bind:aria-pressed="($store.sbDevice.current === @js($device)).toString()"
            x-bind:style="$store.sbDevice.current === @js($device) ? 'background: rgb(128 128 128 / 0.2); opacity: 1;' : 'opacity: 0.55;'"
            style="position: relative; display: inline-flex; padding: 6px; border-radius: 6px;"
        >
            <x-filament::icon :icon="$icon" style="width: 16px; height: 16px;" />
            <span x-show="has(@js($device))" aria-hidden="true" style="position: absolute; top: 3px; right: 3px; width: 5px; height: 5px; border-radius: 9999px; background: currentColor;"></span>
        </button>
    @endforeach
</div>
