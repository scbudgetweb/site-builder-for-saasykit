{{-- Theme Builder › Logo: the Branding logo (header or footer), linking home. --}}
<div @class(['flex', 'justify-center' => $align === 'center', 'justify-end' => $align === 'right'])>
    <a href="{{ route('home') }}" class="inline-flex shrink-0 items-center" aria-label="{{ __(':app home', ['app' => config('app.name')]) }}">
        <x-site-builder::logo :place="$place" :style="$heightStyle !== '' ? $heightStyle : null" />
    </a>
</div>
