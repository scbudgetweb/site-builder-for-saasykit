{{-- The post's date, author and category ($parts, in that order), separated by dots; the category links to its posts. --}}
<p @class([
    'block-text flex flex-wrap items-center gap-x-2 gap-y-1 text-sm',
    'justify-center' => $align === 'center',
    'justify-end' => $align === 'right',
])>
    @foreach ($parts as $part => $item)
        @unless ($loop->first)
            <span class="opacity-50" aria-hidden="true">·</span>
        @endunless

        @if ($part === 'date')
            @if ($item['datetime'])
                <time datetime="{{ $item['datetime'] }}" class="opacity-80">{{ $item['text'] }}</time>
            @else
                <span class="opacity-80">{{ $item['text'] }}</span>
            @endif
        @elseif ($part === 'category' && $item['url'])
            <a href="{{ $item['url'] }}" @class([
                'transition',
                'rounded-full border border-current/25 px-2.5 py-0.5 text-xs hover:border-current/60' => $categoryLook === 'pill',
                'font-medium underline-offset-2 hover:underline' => $categoryLook === 'text',
            ])>{{ $item['text'] }}</a>
        @else
            <span class="opacity-80">{{ $item['text'] }}</span>
        @endif
    @endforeach
</p>
