{{--
    Site builder theme: shadows SaaSykit's pages/terms-of-service.blade.php. The title sits in the inner banner; the text is
    edited at Website › Legal pages, with SaaSykit's own text (site-builder::legal.terms-of-service-default) until then.
--}}
<x-layouts.app>
    <x-slot name="title">
        {{ __('Terms of Service') }}
    </x-slot>

    <x-site-builder::banner variant="inner" class="py-14 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
            <h1 class="sb-h1 text-sb-banner-heading">{{ __('Terms of Service') }}</h1>
        </div>
    </x-site-builder::banner>

    <div class="mx-auto my-6 max-w-4xl px-4 md:my-10">
        <x-site-builder::legal-page key="terms_of_service" />
    </div>
</x-layouts.app>
