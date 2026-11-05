{{--
    Theme Builder › Top bar: a short line, a phone number and an email that can be tapped, and the social icons, in a
    slim row above the header. Its colours are its Background's (the site colour, dark, as standard). The social
    icons take Design › Icon buttons and Icons (.sb-items, .sb-item, .sb-item-icon).
--}}
<div @class(['sb-top-bar text-sm', 'hidden sm:block' => $hidesOnPhones])>
    <div @class([
        'mx-auto flex flex-wrap items-center gap-x-6 gap-y-1 px-4 py-2 sm:px-6 lg:px-8',
        $widthClass,
        $isCentred ? 'justify-center text-center' : 'justify-between',
    ])>
        @if ($line !== '' || $phone !== '' || $email !== '')
            <div @class(['flex flex-wrap items-center gap-x-5 gap-y-1', 'justify-center' => $isCentred])>
                @if ($line !== '')
                    <span>{{ $line }}</span>
                @endif
                @if ($phone !== '' && $phoneHref !== '')
                    <a href="tel:{{ $phoneHref }}" class="inline-flex items-center gap-1.5 text-inherit underline-offset-4 hover:underline">
                        <svg class="h-4 w-4 opacity-80" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                        {{ $phone }}
                    </a>
                @endif
                @if ($email !== '')
                    <a href="mailto:{{ $email }}" class="inline-flex items-center gap-1.5 text-inherit underline-offset-4 hover:underline">
                        <svg class="h-4 w-4 opacity-80" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                        {{ $email }}
                    </a>
                @endif
            </div>
        @endif

        @if ($socialLinks !== [])
            <div class="sb-items flex items-center gap-3">
                @foreach ($socialLinks as $network => $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="sb-item sb-social-link inline-flex items-center justify-center text-inherit opacity-80 transition hover:opacity-100 focus-visible:opacity-100 [&_svg]:h-4 [&_svg]:w-4">
                        <span class="sb-item-icon inline-flex"><x-site-builder::social-icon :network="$network" /></span>
                        <span class="sr-only">{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
