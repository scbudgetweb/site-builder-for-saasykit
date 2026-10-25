{{-- One image with an optional caption and link, and optional buttons above or below it ($buttonPosition). --}}
@if ($imageUrl)
    @php
        $imageTag = '<img src="'.e($imageUrl).'" alt="'.e($block['alt'] ?? '').'" loading="lazy" decoding="async" class="w-full h-auto rounded-sb-image"'.($imageStyle !== '' ? ' style="'.e($imageStyle).'"' : '').'>';
        $hasButtons = ! empty($block['buttons']);
    @endphp

    @if ($hasButtons && $buttonPosition === 'above_image')
        <div class="mb-6">
            @include('site-builder::blocks.partials.buttons', ['buttons' => $block['buttons'], 'align' => 'left'])
        </div>
    @endif

    <figure>
        @if (filled($block['link'] ?? null) && preg_match('/^(https?:\/\/|\/|#)/i', $block['link']))
            <a href="{{ $block['link'] }}" class="block">{!! $imageTag !!}</a>
        @else
            {!! $imageTag !!}
        @endif

        @if (filled($block['caption'] ?? null))
            <figcaption class="mt-3 text-sm text-center opacity-75">{{ $block['caption'] }}</figcaption>
        @endif
    </figure>

    @if ($hasButtons && $buttonPosition === 'below_image')
        <div class="sb-block-buttons mt-6">
            @include('site-builder::blocks.partials.buttons', ['buttons' => $block['buttons'], 'align' => 'left'])
        </div>
    @endif
@endif
