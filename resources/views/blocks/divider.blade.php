@php
    $spacing = match ($block['size'] ?? 'medium') {
        'small' => 'py-4',
        'large' => 'py-16',
        default => 'py-8',
    };
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 {{ $spacing }}" data-block="divider" aria-hidden="{{ filled($block['label'] ?? null) ? 'false' : 'true' }}">
    @if (($block['style'] ?? 'line') === 'line')
        @if (filled($block['label'] ?? null))
            <div class="flex items-center gap-4 text-sm font-semibold uppercase tracking-wider text-gray-500">
                <span class="h-px flex-1 bg-gray-200"></span>
                <span>{{ $block['label'] }}</span>
                <span class="h-px flex-1 bg-gray-200"></span>
            </div>
        @else
            <hr class="border-gray-200">
        @endif
    @endif
</div>
