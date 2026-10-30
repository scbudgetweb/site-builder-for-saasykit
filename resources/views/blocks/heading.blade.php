{{-- A heading on its own, at its level ($level: h1…h6, which also sets its standard size, .sb-h1…), with its small heading. --}}
<div @class(['text-center' => $align === 'center', 'text-right' => $align === 'right'])>
    @include('site-builder::blocks.partials.eyebrow')
    @if ($heading !== '')
        <{{ $level }} @class(["sb-{$level} section-heading", 'text-center' => $align === 'center', 'text-right' => $align === 'right'])>{{ $heading }}</{{ $level }}>
    @endif
</div>
