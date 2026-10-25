{{-- The Banner and Hero blocks (Hero is a Banner with taller, larger defaults): a full-width Branding banner (hero or inner) or its own image. As the first block it holds the h1. --}}
<x-site-builder::banner :variant="$variant" :image-url="$imageUrl" :image-overlay="$imageOverlay" :image-position="$imagePosition"
                        :class="$cssClass" data-block="{{ $blockName }}">
    <div @class([
        'relative mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8',
        $heightClass,
        'text-center' => ! $isLeftAligned,
    ])>
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
        </div>
    </div>
</x-site-builder::banner>
