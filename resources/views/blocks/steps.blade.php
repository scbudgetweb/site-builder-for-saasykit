@php($steps = $block['items'] ?? [])

@include('site-builder::blocks.partials.heading')

<ol @class([
    'grid gap-8 sm:grid-cols-2',
    'lg:grid-cols-3' => count($steps) === 3 || count($steps) > 4,
    'lg:grid-cols-4' => count($steps) === 4,
])>
    @foreach ($steps as $step)
        <li class="sb-item text-center">
            @php($icon = \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render($step['icon'] ?? null, 'h-6 w-6'))
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary-600 text-lg font-bold text-white">
                @if ($icon->toHtml() !== '')
                    {{ $icon }}<span class="sr-only">{{ __('Step :number', ['number' => $loop->iteration]) }}</span>
                @else
                    {{ $loop->iteration }}
                @endif
            </span>
            <h3 class="mt-4 text-lg font-semibold">{{ $step['title'] ?? '' }}</h3>
            @if (filled($step['text'] ?? null))
                <p class="block-text mt-2 text-sm leading-relaxed opacity-80">{{ $step['text'] }}</p>
            @endif
        </li>
    @endforeach
</ol>
