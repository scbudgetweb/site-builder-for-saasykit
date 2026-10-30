{{-- Formatted text, with optional buttons above or below it ($buttonPosition), in a text style ($textStyle: .sb-text-lead…). --}}
@if ($buttons !== [] && $buttonPosition === 'before_text')
    <div class="mb-6">
        @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'align' => $isCentered ? 'center' : 'left'])
    </div>
@endif

<div @class(['text-center' => $isCentered])>
    @include('site-builder::blocks.partials.eyebrow')
</div>

<div @class(['block-text sb-prose', "sb-text-{$textStyle}" => $textStyle !== 'standard', 'text-center' => $isCentered])
     @if ($textStyle === 'statement' && $accentColour) style="--sb-text-accent: {{ $accentColour }}" @endif>
    {!! $html !!}
</div>

@if ($buttons !== [] && $buttonPosition === 'after_text')
    <div class="sb-block-buttons mt-8">
        @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'align' => $isCentered ? 'center' : 'left'])
    </div>
@endif
