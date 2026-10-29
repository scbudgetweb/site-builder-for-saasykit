{{--
    Site builder theme: shadows SaaSykit's components/layouts/app/footer.blade.php.
    Colours come from Branding › Header & footer; social links from SaaSykit's General Settings; the tagline, link
    columns (one to four, beside the logo) and copyright line from Website › Navigation › Footer.

    A Theme Builder footer template (Website › Theme builder) that applies to this page takes its place.
--}}
@php
    $footerTemplate = app(\ScBudgetWeb\SiteBuilder\Templates\Templates::class)->render('footer');
@endphp

@if ($footerTemplate !== null)
    <footer {{ $attributes }}>
        {{ $footerTemplate }}
    </footer>
@else
@php
    $socialLinks = array_filter([
        'facebook' => [__('Facebook'), config('app.social_links.facebook')],
        'instagram' => [__('Instagram'), config('app.social_links.instagram')],
        'youtube' => [__('YouTube'), config('app.social_links.youtube')],
        'x' => [__('X'), config('app.social_links.x')],
        'linkedin' => [__('LinkedIn'), config('app.social_links.linkedin') ?: config('app.social_links.linkedin-openid')],
        'github' => [__('GitHub'), config('app.social_links.github')],
        'discord' => [__('Discord'), config('app.social_links.discord')],
    ], fn (array $link): bool => \ScBudgetWeb\SiteBuilder\Blocks\Core\SocialFollowBlock::isWebAddress($link[1]));

    $footer = app(\ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus::class)->footer();
    $columnCount = count($footer['columns']);
    $gridColumns = match ($columnCount) {
        0 => '',
        1 => 'md:grid-cols-2',
        2 => 'sm:grid-cols-2 lg:grid-cols-3',
        3 => 'sm:grid-cols-3 lg:grid-cols-4',
        default => 'sm:grid-cols-4 lg:grid-cols-5',
    };
    $brandSpan = match (true) {
        $columnCount >= 4 => 'sm:col-span-4 lg:col-span-1',
        $columnCount === 3 => 'sm:col-span-3 lg:col-span-1',
        $columnCount === 2 => 'sm:col-span-2 lg:col-span-1',
        default => '',
    };
    $linkColors = 'text-sb-footer-link transition-colors hover:text-sb-footer-link-hover focus-visible:text-sb-footer-link-hover';
@endphp

<footer {{ $attributes->class(['mt-16 bg-sb-footer-bg text-sb-footer-text']) }}>
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div @class(['grid grid-cols-1 gap-8', $gridColumns])>
            <div @class(['max-w-sm', $brandSpan])>
                <a href="{{ route('home') }}" class="inline-flex items-center text-sb-footer-heading">
                    <x-site-builder::logo place="footer" />
                </a>
                @if ($footer['tagline'] !== '')
                    <p class="mt-4 text-sm leading-relaxed">{{ $footer['tagline'] }}</p>
                @endif

                @if ($socialLinks !== [])
                    <div class="mt-6 flex gap-3">
                        @foreach ($socialLinks as $network => [$label, $url])
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="text-sb-footer-social transition-colors hover:text-sb-footer-social-hover focus-visible:text-sb-footer-social-hover [&_svg]:h-5 [&_svg]:w-5">
                                <x-site-builder::social-icon :network="$network" />
                                <span class="sr-only">{{ $label }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @foreach ($footer['columns'] as $column)
                <nav aria-label="{{ $column['heading'] !== '' ? $column['heading'] : __('Footer') }}" @class(['md:justify-self-end' => $columnCount === 1])>
                    @if ($column['heading'] !== '')
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-sb-footer-heading">{{ $column['heading'] }}</h2>
                    @endif
                    <ul @class(['space-y-2.5 text-sm', 'mt-4' => $column['heading'] !== ''])>
                        @foreach ($column['links'] as $link)
                            <li><a href="{{ $link['url'] }}" @if ($link['new_tab']) target="_blank" rel="noopener" @endif class="{{ $linkColors }}">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
        </div>

        <div class="mt-10 border-t border-current/15 pt-6 text-xs">
            {{-- Escaped text, with any simple links checked (NavigationMenus::linkedText()). --}}
            {{ $footer['copyrightHtml'] }}
        </div>
    </div>
</footer>
@endif
