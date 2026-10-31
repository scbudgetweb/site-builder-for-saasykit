{{-- The blog archive's title (ArchiveTitleBlock), at its level, with its small heading and an optional line under it. --}}
<div @class(['text-center' => $align === 'center', 'text-right' => $align === 'right'])>
    @include('site-builder::blocks.partials.eyebrow')
    @if ($heading !== '')
        <{{ $level }} class="sb-{{ $level }} section-heading">{{ $heading }}</{{ $level }}>
    @endif
    @if ($intro !== '')
        <p @class(['block-text mt-3 max-w-3xl text-base opacity-80 sm:text-lg', 'mx-auto' => $align === 'center', 'ml-auto' => $align === 'right'])>{{ $intro }}</p>
    @endif
</div>
