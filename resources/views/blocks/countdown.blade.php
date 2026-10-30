{{--
    Counts down to $endsAt (a timestamp, so a cached page still counts right): each unit is an item. Its numbers in the
    HTML are where it starts; the script inline keeps them going, so a block added in the visual builder works too.
    When it ends: a message, hidden (not on the builder's canvas), or zero.
--}}
@include('site-builder::blocks.partials.heading')

<div class="sb-countdown"
     x-data="{
        end: {{ $endsAt }},
        left: null,
        units: { days: 86400, hours: 3600, minutes: 60, seconds: 1 },
        get ended() { return this.left === 0 },
        value(unit) {
            if (this.left === null) return null;
            const value = unit === 'days' ? Math.floor(this.left / 86400) : Math.floor(this.left % (unit === 'hours' ? 86400 : unit === 'minutes' ? 3600 : 60) / this.units[unit]);
            return unit === 'days' ? String(value) : String(value).padStart(2, '0');
        },
        tick() {
            this.left = Math.max(0, Math.round((this.end - Date.now()) / 1000));
            if (this.ended && @js($whenEnded === 'hide' && ! ($marksNodes ?? false))) { $el.closest('[data-block]').hidden = true; }
        },
     }"
     x-init="tick(); const timer = setInterval(() => { tick(); if (ended) clearInterval(timer); }, 1000)">
    <p class="sr-only">{{ __('Ends :date', ['date' => $endsAtText]) }}</p>

    {{-- Shown or hidden by an inline style, which x-show takes over (a class would stay). --}}
    <div class="sb-items flex flex-wrap justify-center gap-4 sm:gap-6 text-center"
         @if ($whenEnded === 'message') x-show="! ended" @if ($hasEnded) style="display: none" @endif @endif aria-hidden="true">
        @foreach ($units as $unit)
            <div class="sb-item min-w-20 sm:min-w-24">
                <span class="sb-item-value block text-4xl sm:text-5xl font-extrabold tabular-nums {{ $isDark ? 'text-inherit' : 'text-primary-600' }}"
                      x-text="value(@js($unit['unit'])) ?? $el.textContent">{{ $unit['value'] }}</span>
                <span class="sb-item-label mt-2 block text-sm font-medium opacity-80"
                      x-text="value(@js($unit['unit'])) === '1' || value(@js($unit['unit'])) === '01' ? @js($unit['labels'][0]) : @js($unit['labels'][1])">{{ in_array($unit['value'], ['1', '01'], true) ? $unit['labels'][0] : $unit['labels'][1] }}</span>
            </div>
        @endforeach
    </div>

    @if ($whenEnded === 'message')
        <p class="block-text text-center text-lg font-medium" x-show="ended" @if (! $hasEnded) style="display: none" @endif>{{ $endedMessage }}</p>
    @endif
</div>

@if ($buttons !== [])
    <div class="sb-block-buttons mt-8">
        @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'align' => 'center'])
    </div>
@endif
