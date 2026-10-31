{{-- Share links for the post ($links: network => url and label), round icons as Social follow draws them. --}}
<div @class(['text-center' => $align === 'center', 'text-right' => $align === 'right'])>
    @if ($heading !== '')
        <h2 class="section-heading mb-4 text-lg">{{ $heading }}</h2>
    @endif
    <div @class(['flex flex-wrap gap-3', 'justify-center' => $align === 'center', 'justify-end' => $align === 'right'])>
        @foreach ($links as $network => $link)
            <a href="{{ $link['url'] }}" @if ($network !== 'email') target="_blank" rel="noopener" @endif title="{{ $link['label'] }}"
               class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-current opacity-80 transition hover:opacity-100 hover:bg-current/10 [&_svg]:h-5 [&_svg]:w-5">
                <x-site-builder::social-icon :network="$network" />
                <span class="sr-only">{{ $link['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
