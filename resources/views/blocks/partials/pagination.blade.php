{{--
    Links to a list's other pages (Post list), in the theme's look: the current page as the first button style, the
    others outlined in the text colour. On a phone, just Previous and Next. $paginator and $elements are Laravel's.
--}}
@php($link = 'inline-flex min-w-10 items-center justify-center rounded-sb-button px-3 py-2 text-sm font-semibold border border-current/25 text-inherit transition-colors hover:border-current')
@php($disabled = 'inline-flex min-w-10 items-center justify-center rounded-sb-button border border-transparent px-3 py-2 text-sm font-semibold opacity-40')
<div class="flex items-center justify-between gap-2 sm:justify-center">
    @if ($paginator->onFirstPage())
        <span class="{{ $disabled }}" aria-disabled="true">{{ __('Previous') }}</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}">{{ __('Previous') }}</a>
    @endif

    <span class="text-sm opacity-70 sm:hidden">{{ __('Page :page of :pages', ['page' => $paginator->currentPage(), 'pages' => $paginator->lastPage()]) }}</span>

    <ul class="hidden items-center gap-2 sm:flex">
        @foreach ($elements as $element)
            @if (is_string($element))
                <li class="{{ $disabled }}" aria-hidden="true">{{ $element }}</li>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    <li>
                        @if ($page == $paginator->currentPage())
                            <span class="site-button site-button-1 inline-flex min-w-10 items-center justify-center px-3 py-2 text-sm font-semibold" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $link }}" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    </li>
                @endforeach
            @endif
        @endforeach
    </ul>

    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}">{{ __('Next') }}</a>
    @else
        <span class="{{ $disabled }}" aria-disabled="true">{{ __('Next') }}</span>
    @endif
</div>
