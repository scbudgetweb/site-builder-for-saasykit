{{-- Header bar: the header logo from Branding (or the site's name), linking home. --}}
<a href="{{ route('home') }}" class="inline-flex shrink-0 items-center" aria-label="{{ __(':app home', ['app' => config('app.name')]) }}">
    <x-site-builder::logo place="header" :style="$logoStyle" />
</a>
