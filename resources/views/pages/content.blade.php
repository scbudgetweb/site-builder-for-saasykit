{{--
    A page's body: the standard page banner with the title (unless turned off, or the page opens with its own Banner),
    then the blocks. On the visual builder's canvas ($marksNodes), the blocks are marked up for editing.
--}}
@php($renderer = app(\ScBudgetWeb\SiteBuilder\Blocks\PageRenderer::class))

<div data-page="{{ $page->getKey() }}">
    @if ($renderer->showsPageBanner($page))
        <x-site-builder::banner variant="inner" class="py-14 sm:py-20">
            <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
                <h1 class="sb-h1 text-sb-banner-heading">{{ $page->title }}</h1>
            </div>
        </x-site-builder::banner>
    @elseif (! $renderer->opensWithBanner($page))
        <h1 class="sr-only">{{ $page->title }}</h1>
    @endif

    {{ $renderer->render($page, $marksNodes ?? false) }}

    @if (($marksNodes ?? false) && $page->blockList() === [])
        <button type="button" class="sb-canvas-empty" data-sb-add-first style="display: flex; width: calc(100% - 2rem); margin: 2rem auto; max-width: 64rem; cursor: pointer;">
            {{ __('This page is empty. Click to add your first block.') }}
        </button>
    @endif
</div>
