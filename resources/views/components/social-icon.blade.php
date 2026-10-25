@props([
    // facebook, x, linkedin, instagram, youtube, github, discord or tiktok.
    'network',
    'class' => 'h-5 w-5',
])

{{--
    A social network's logo, drawn inline from the bundled Boxicons (no requests to other servers, lesson 14).
    Boxicons 2.1.4 has no X logo, so that one is drawn here. An unknown network draws nothing.
--}}
@if ($network === 'x')
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="{{ $class }}" fill="currentColor" aria-hidden="true" focusable="false"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
@else
    {{ \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render(match ($network) {
        'facebook' => 'bx:logos/bxl-facebook',
        'linkedin' => 'bx:logos/bxl-linkedin',
        'instagram' => 'bx:logos/bxl-instagram',
        'youtube' => 'bx:logos/bxl-youtube',
        'github' => 'bx:logos/bxl-github',
        'discord' => 'bx:logos/bxl-discord-alt',
        'tiktok' => 'bx:logos/bxl-tiktok',
        default => null,
    }, $class) }}
@endif
