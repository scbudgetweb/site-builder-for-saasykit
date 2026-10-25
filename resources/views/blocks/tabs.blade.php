@include('site-builder::blocks.partials.heading')

<div x-data="{ active: 0 }">
    <div class="flex flex-wrap gap-2 border-b border-current/15" role="tablist">
        @foreach ($items as $item)
            <button type="button" role="tab" id="tab-{{ $index }}-{{ $loop->index }}"
                    :aria-selected="(active === {{ $loop->index }}).toString()"
                    aria-controls="tabpanel-{{ $index }}-{{ $loop->index }}"
                    @click="active = {{ $loop->index }}"
                    class="-mb-px border-b-2 px-4 py-3 text-sm font-semibold transition-colors"
                    :class="active === {{ $loop->index }} ? 'border-primary-600 text-primary-600' : 'border-transparent opacity-70 hover:opacity-100'">
                {{ $item['title'] }}
            </button>
        @endforeach
    </div>

    @foreach ($items as $item)
        <div id="tabpanel-{{ $index }}-{{ $loop->index }}" role="tabpanel" aria-labelledby="tab-{{ $index }}-{{ $loop->index }}"
             x-show="active === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif class="block-text sb-prose pt-6">
            {!! $item['html'] !!}
        </div>
    @endforeach
</div>
