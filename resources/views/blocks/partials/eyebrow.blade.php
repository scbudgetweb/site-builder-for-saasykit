{{-- A block's small heading (eyebrow) above its heading: small capitals, or a pill. --}}
@if (filled($block['eyebrow'] ?? null))
    <p @class([
        'sb-eyebrow',
        'sb-eyebrow-pill' => ($block['eyebrow_style'] ?? null) === 'pill',
    ])>{{ $block['eyebrow'] }}</p>
@endif
