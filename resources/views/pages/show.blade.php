{{--
    A page on its own URL, in place of a built-in page it replaces, in the signed admin preview ($isPreview), or on the
    visual builder's canvas ($isCanvas), where the editor around it draws its outlines and toolbars.
    Uses the app's public layout (the theme's, or SaaSykit's when the theme is off).
--}}
{{-- The page being shown, for Theme Builder templates that apply to some pages only (set before the layout draws the header). --}}
@php(request()->attributes->set(\ScBudgetWeb\SiteBuilder\Templates\Templates::PAGE_ATTRIBUTE, $page))
<x-layouts.app>
    <x-slot name="title">{{ $page->metaTitle() }}</x-slot>
    @if (filled($page->seo_description))
        <x-slot name="description">{{ $page->seo_description }}</x-slot>
    @endif
    <x-slot name="canonical">{{ $page->url() }}</x-slot>
    @if ($shareImageUrl = \ScBudgetWeb\SiteBuilder\Blocks\PageBlock::imageUrl($page->share_image))
        <x-slot name="socialCard">{{ $shareImageUrl }}</x-slot>
    @endif

    {{-- Branding fonts that blocks choose for themselves (Style › Heading text, Body text, Links). --}}
    @foreach (\ScBudgetWeb\SiteBuilder\Blocks\BlockDesign::fontStylesheets($page->blockList()) as $fontStylesheet)
        @push('head')
            <link rel="stylesheet" href="{{ $fontStylesheet }}">
        @endpush
    @endforeach

    @if ($page->noindex || ! $page->isPublished() || ($isPreview ?? false))
        @push('head')
            <meta name="robots" content="noindex, nofollow">
        @endpush
    @endif

    @if ($isCanvas ?? false)
        @push('head')
            <style>
                .sb-canvas-empty { display: flex; min-height: 6rem; align-items: center; justify-content: center; border: 2px dashed color-mix(in oklab, currentColor 25%, transparent); border-radius: 0.5rem; font-size: 0.875rem; opacity: 0.7; }
            </style>
        @endpush
    @elseif ($isPreview ?? false)
        <div class="sticky top-0 z-40 border-b border-amber-200 bg-amber-100 text-sm text-amber-900" role="status">
            <p class="mx-auto max-w-7xl px-4 py-2 sm:px-6 lg:px-8">
                @if (isset($revision))
                    {{ __('Preview of an earlier version of this page, saved :date. It isn\'t live: restore it in the editor to use it.', ['date' => $revision->created_at?->translatedFormat('j M Y, H:i')]) }}
                @else
                    {{ $page->isPublished() ? __('Preview of this page as it is saved now.') : __('Preview of a draft page. Only people with this link can see it, and the link expires after an hour.') }}
                @endif
            </p>
        </div>
    @endif

    @include('site-builder::pages.content', ['page' => $page, 'marksNodes' => $isCanvas ?? false])
</x-layouts.app>
