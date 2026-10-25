{{-- A heading, a line of text and buttons. --}}
<div @class(['max-w-3xl', 'mx-auto text-center' => $isCentered])>
    <h2 class="sb-h2 block-heading">{{ $block['heading'] ?? '' }}</h2>

    @if (filled($block['text'] ?? null))
        <p class="block-text mt-4 text-lg opacity-85">{{ $block['text'] }}</p>
    @endif

    @if ($buttons !== [])
        <div class="sb-block-buttons mt-8">
            @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'isDark' => $isDark, 'align' => $isCentered ? 'center' : 'left'])
        </div>
    @endif
</div>
