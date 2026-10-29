{{-- The banner's text and buttons. --}}
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

    {{-- Social proof: overlapping faces, a star rating and a line of text, e.g. "Join 900+ happy customers". --}}
    @if (($proofAvatars ?? []) !== [] || filled($block['proof_stars'] ?? null) || filled($block['proof_text'] ?? null))
        <div @class(['mt-8 flex flex-wrap items-center gap-x-4 gap-y-2 text-sb-banner-subtitle', 'justify-center' => ! $isLeftAligned])>
            @if (($proofAvatars ?? []) !== [])
                <div class="flex -space-x-3">
                    @foreach ($proofAvatars as $avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="" loading="lazy" class="h-10 w-10 rounded-full object-cover ring-2 ring-white">
                    @endforeach
                </div>
            @endif
            <div @class(['flex flex-col gap-1', 'items-center text-center' => ! $isLeftAligned])>
                @include('site-builder::blocks.partials.stars', ['stars' => $block['proof_stars'] ?? 0])
                @if (filled($block['proof_text'] ?? null))
                    <p class="text-sm sm:text-base">{{ $block['proof_text'] }}</p>
                @endif
            </div>
        </div>
    @endif
</div>

@if ($featureImageUrl ?? null)
    <div class="mx-auto mt-12 max-w-5xl">
        <img src="{{ $featureImageUrl }}" alt="{{ $block['feature_image_alt'] ?? '' }}" loading="lazy" decoding="async"
             class="h-auto w-full rounded-sb-image shadow-2xl">
    </div>
@endif
