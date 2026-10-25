{{-- Formatted text, with optional buttons above or below it ($buttonPosition). --}}
@if ($buttons !== [] && $buttonPosition === 'before_text')
    <div class="mb-6">
        @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'align' => $isCentered ? 'center' : 'left'])
    </div>
@endif

<div @class(['block-text sb-prose', 'text-center' => $isCentered])>
    {!! $html !!}
</div>

@if ($buttons !== [] && $buttonPosition === 'after_text')
    <div class="sb-block-buttons mt-8">
        @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'align' => $isCentered ? 'center' : 'left'])
    </div>
@endif
