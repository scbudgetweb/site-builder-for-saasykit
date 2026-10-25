@php($columns = (int) ($block['columns'] ?? 3))

@include('site-builder::blocks.partials.heading')

<div x-data="{ open: null, images: @js(collect($images)->map(fn ($image) => ['url' => $image['url'], 'alt' => $image['alt']])->all()) }"
     @keydown.escape.window="open = null">
    <div @class([
        'grid gap-4 grid-cols-2',
        'md:grid-cols-2' => $columns === 2,
        'md:grid-cols-3' => $columns === 3,
        'md:grid-cols-4' => $columns === 4,
    ])>
        @foreach ($images as $image)
            <figure>
                <button type="button" @click="open = {{ $loop->index }}" class="block w-full overflow-hidden rounded-sb-image focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600" @if ($imageStyle !== '') style="{{ $imageStyle }}" @endif>
                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" decoding="async" class="aspect-[4/3] w-full object-cover transition-transform duration-300 hover:scale-105">
                </button>
                @if (filled($image['caption']))
                    <figcaption class="mt-2 text-sm opacity-75">{{ $image['caption'] }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>

    <template x-teleport="body">
        <div x-show="open !== null" x-cloak x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-black/85 p-4"
             role="dialog" aria-modal="true" aria-label="{{ __('Image viewer') }}" @click.self="open = null">
            <button type="button" @click="open = null" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="{{ __('Close') }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <template x-if="open !== null">
                <img :src="images[open].url" :alt="images[open].alt" class="max-h-[85vh] max-w-full rounded-lg object-contain">
            </template>
        </div>
    </template>
</div>
