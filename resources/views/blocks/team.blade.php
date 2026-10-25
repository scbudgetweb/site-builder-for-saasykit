@php($columns = (int) ($block['columns'] ?? 3))

@include('site-builder::blocks.partials.heading')

<div @class([
    'grid gap-8 sm:grid-cols-2',
    'lg:grid-cols-2' => $columns === 2,
    'lg:grid-cols-3' => $columns === 3,
    'lg:grid-cols-4' => $columns === 4,
])>
    @foreach ($block['members'] ?? [] as $member)
        @php($photoUrl = \ScBudgetWeb\SiteBuilder\Blocks\PageBlock::imageUrl($member['photo'] ?? null))
        <div class="text-center">
            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $member['name'] ?? '' }}" loading="lazy" class="mx-auto h-40 w-40 rounded-full object-cover shadow-sm">
            @else
                <span class="mx-auto flex h-40 w-40 items-center justify-center rounded-full bg-primary-100 text-4xl font-bold text-primary-700" aria-hidden="true">
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($member['name'] ?? '?', 0, 1)) }}
                </span>
            @endif
            <h3 class="mt-5 text-lg font-semibold">{{ $member['name'] ?? '' }}</h3>
            @if (filled($member['role'] ?? null))
                <p class="text-sm font-medium text-primary-600" @if ($isDark) style="color: inherit; opacity: .8" @endif>{{ $member['role'] }}</p>
            @endif
            @if (filled($member['bio'] ?? null))
                <p class="block-text mt-3 text-sm leading-relaxed opacity-80">{{ $member['bio'] }}</p>
            @endif
            @if (filled($member['linkedin'] ?? null) && str_starts_with($member['linkedin'], 'https://'))
                <a href="{{ $member['linkedin'] }}" target="_blank" rel="noopener" class="mt-3 inline-flex opacity-70 hover:opacity-100" title="{{ __(':name on LinkedIn', ['name' => $member['name'] ?? '']) }}">
                    <x-site-builder::social-icon network="linkedin" />
                    <span class="sr-only">{{ __(':name on LinkedIn', ['name' => $member['name'] ?? '']) }}</span>
                </a>
            @endif
        </div>
    @endforeach
</div>
