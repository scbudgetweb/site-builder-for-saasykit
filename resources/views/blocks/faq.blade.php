@push('head')
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@include('site-builder::blocks.partials.heading')

<div class="divide-y divide-current/15 border-y border-current/15">
    @foreach ($items as $item)
        <details class="sb-item group py-5">
            <summary class="flex cursor-pointer list-none items-start justify-between gap-6 text-left text-lg font-semibold [&::-webkit-details-marker]:hidden">
                <span>{{ $item['question'] }}</span>
                <svg class="mt-1 h-5 w-5 shrink-0 transition-transform group-open:rotate-45" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </summary>
            <div class="block-text sb-prose mt-3 opacity-85">{!! $item['html'] !!}</div>
        </details>
    @endforeach
</div>
