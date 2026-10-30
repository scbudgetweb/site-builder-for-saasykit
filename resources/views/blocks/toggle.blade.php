{{-- A title that opens and closes its content: <details>, open at first with $isOpen, with a plus, an arrow or no $marker. --}}
<details class="sb-item sb-toggle group border-y border-current/15 py-5" @if ($isOpen) open @endif>
    <summary class="flex cursor-pointer list-none items-start justify-between gap-6 text-left text-lg font-semibold [&::-webkit-details-marker]:hidden">
        <span class="sb-item-title">{{ $title }}</span>
        @if ($marker === 'plus')
            <svg class="mt-1 h-5 w-5 shrink-0 transition-transform group-open:rotate-45" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        @elseif ($marker === 'chevron')
            <svg class="mt-1 h-5 w-5 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/></svg>
        @endif
    </summary>
    <div class="sb-item-text block-text sb-prose mt-3 opacity-85">{!! $html !!}</div>
</details>
