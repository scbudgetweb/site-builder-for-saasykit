{{-- The Banner and Hero blocks (Hero is a Banner with taller, larger defaults): a full-width Branding banner (hero or inner) or its own image. As the first block it holds the h1. A subclass's extra content (BannerBlock::extraContentView()) sits below the text, or beside it on wide screens. --}}
<x-site-builder::banner :variant="$variant" :image-url="$imageUrl" :image-overlay="$imageOverlay" :image-position="$imagePosition"
                        :class="$cssClass" data-block="{{ $blockName }}">
    <div @class([
        'relative mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8',
        $heightClass,
        'text-center' => ! $isLeftAligned,
    ])>
        @if ($isExtraContentBeside ?? false)
            <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-12">
                @include('site-builder::blocks.partials.banner-text')

                <div data-banner-extra="beside">
                    @include($extraContentView)
                </div>
            </div>
        @else
            @include('site-builder::blocks.partials.banner-text')
        @endif
    </div>
</x-site-builder::banner>
