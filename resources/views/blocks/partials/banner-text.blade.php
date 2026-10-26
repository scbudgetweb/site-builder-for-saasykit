{{-- The banner's text and buttons, and a subclass's extra content when it goes below them. --}}
<div @class(['max-w-4xl', 'mx-auto' => ! $isLeftAligned])>
    @if (filled($block['eyebrow'] ?? null))
        <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-sb-banner-subtitle sm:text-base">{{ $block['eyebrow'] }}</p>
    @endif

    @if ($index === 0)
        <h1 class="{{ $headingClass }} font-extrabold leading-tight text-sb-banner-heading">{{ $block['heading'] ?? '' }}</h1>
    @else
        <h2 class="{{ $headingClass }} font-extrabold leading-tight text-sb-banner-heading">{{ $block['heading'] ?? '' }}</h2>
    @endif

    @if (filled($block['subtitle'] ?? null))
        <p class="mt-4 sm:mt-6 {{ $subtitleClass }} text-sb-banner-subtitle">{{ $block['subtitle'] }}</p>
    @endif

    @if ($buttons !== [])
        <div class="mt-8">
            @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'isDark' => true, 'align' => $isLeftAligned ? 'left' : 'center'])
        </div>
    @endif

    @if (($extraContentView ?? null) !== null && ! ($isExtraContentBeside ?? false))
        <div class="mt-8" data-banner-extra="below">
            @include($extraContentView)
        </div>
    @endif
</div>
