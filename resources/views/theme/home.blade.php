{{--
    Site builder theme: shadows SaaSykit's home.blade.php with a plain, neutral home page: a hero, three short points,
    SaaSykit's own plans and pricing, and a call to action. Every word goes through __(), so an app can reword it in
    its lang file. A published page in Admin › Website › Pages can replace it (Settings › Replaces built-in page).
--}}
@if ($builtPage = app(\ScBudgetWeb\SiteBuilder\Pages\PageService::class)->forSlot('home'))
    @include('site-builder::pages.show', ['page' => $builtPage])
@else
<x-layouts.app>
    <x-site-builder::banner variant="hero" class="py-20 sm:py-28">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
            <h1 class="sb-h1 text-sb-banner-heading">{{ __('site-builder::theme.home.heading', ['app' => config('app.name')]) }}</h1>

            @if (filled(config('app.description')))
                <p class="mx-auto mt-6 max-w-2xl text-lg text-sb-banner-subtitle sm:text-xl">{{ config('app.description') }}</p>
            @endif

            <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a href="#pricing" class="site-button site-button-2">{{ __('site-builder::theme.home.primary_button') }}</a>
                @guest
                    <a href="{{ route('login') }}" class="site-button site-button-3 text-sb-banner-heading">{{ __('Log in') }}</a>
                @endguest
            </div>
        </div>
    </x-site-builder::banner>

    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8" aria-labelledby="home-points">
        <h2 id="home-points" class="sb-h2 section-heading text-center">{{ __('site-builder::theme.home.points_heading') }}</h2>

        <div class="mt-12 grid gap-8 md:grid-cols-3">
            @foreach (['simple', 'anywhere', 'support'] as $point)
                <div class="rounded-sb-card border border-gray-200 bg-white p-6 shadow-sb-card">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-700" aria-hidden="true">
                        @switch($point)
                            @case('simple')
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                @break
                            @case('anywhere')
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                                @break
                            @default
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>
                        @endswitch
                    </div>
                    <h3 class="mt-4 text-lg font-semibold">{{ __("site-builder::theme.home.points.{$point}.title") }}</h3>
                    <p class="mt-2 text-gray-600">{{ __("site-builder::theme.home.points.{$point}.text") }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section id="pricing" class="scroll-mt-20 bg-gray-50 py-16 sm:py-24" aria-labelledby="home-pricing">
        <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
            <h2 id="home-pricing" class="sb-h2 section-heading text-center">{{ __('site-builder::theme.home.pricing_heading') }}</h2>
            <p class="mx-auto mt-4 max-w-2xl text-gray-600">{{ __('site-builder::theme.home.pricing_text') }}</p>
        </div>

        <div class="pricing mt-10">
            <x-plans.all calculate-saving-rates="true" show-default-product="1" />
            <x-products.all />
        </div>
    </section>

    <x-site-builder::banner variant="inner" class="py-16 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h2 class="sb-h2 text-sb-banner-heading">{{ __('site-builder::theme.home.cta_heading') }}</h2>
            <p class="mt-4 text-lg text-sb-banner-subtitle">{{ __('site-builder::theme.home.cta_text') }}</p>
            <div class="mt-8">
                <a href="#pricing" class="site-button site-button-2">{{ __('site-builder::theme.home.primary_button') }}</a>
            </div>
        </div>
    </x-site-builder::banner>
</x-layouts.app>
@endif
