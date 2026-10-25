@php($isCentered = ($block['align'] ?? 'center') === 'center')

@if ($links !== [])
    <div @class(['text-center' => $isCentered])>
        @if (filled($block['heading'] ?? null))
            <h2 class="section-heading mb-5 text-xl">{{ $block['heading'] }}</h2>
        @endif
        <div @class(['flex flex-wrap gap-3', 'justify-center' => $isCentered])>
            @foreach ($links as $network => $link)
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener" title="{{ $link['label'] }}"
                   class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-current opacity-80 transition hover:opacity-100 hover:bg-current/10 [&_svg]:h-5 [&_svg]:w-5">
                    <x-site-builder::social-icon :network="$network" />
                    <span class="sr-only">{{ $link['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
