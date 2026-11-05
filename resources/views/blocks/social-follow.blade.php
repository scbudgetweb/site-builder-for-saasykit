@php($align = in_array($block['align'] ?? null, ['left', 'right'], true) ? $block['align'] : 'center')
@php($isCentered = $align === 'center')

@if ($links !== [])
    <div @class(['text-center' => $isCentered, 'text-right' => $align === 'right'])>
        @if (filled($block['heading'] ?? null))
            <h2 class="section-heading mb-5 text-xl">{{ $block['heading'] }}</h2>
        @endif
        <div @class(['sb-items flex flex-wrap gap-3', 'justify-center' => $isCentered, 'justify-end' => $align === 'right'])>
            @foreach ($links as $network => $link)
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener" title="{{ $link['label'] }}"
                   class="sb-item sb-social-link inline-flex items-center justify-center rounded-full border border-current p-[11px] opacity-80 transition hover:opacity-100 hover:bg-current/10 [&_svg]:h-5 [&_svg]:w-5">
                    <span class="sb-item-icon inline-flex"><x-site-builder::social-icon :network="$network" /></span>
                    <span class="sr-only">{{ $link['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@elseif ($marksNodes ?? false)
    {{-- On the builder's canvas only: visitors see nothing, as there's nothing to link to. --}}
    <p class="sb-social-follow-empty rounded-sb-card border border-dashed border-current/30 p-4 text-center text-sm opacity-80">
        {{ __('No social links to show yet. Add their web addresses (starting https://) in Settings › General Settings › Social Links.') }}
    </p>
@endif
