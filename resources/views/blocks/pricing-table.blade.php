{{--
    Plans side by side, each a card (Design › Items) with its name, price, features and button; a featured plan stands
    out with a ring and its badge. Or SaaSykit's live plans ($isLive, partials/pricing-live), as the theme's home page shows them.
--}}
@include('site-builder::blocks.partials.heading')

@if ($isLive)
    @include('site-builder::blocks.partials.pricing-live')
@else
    <div @class([
        'sb-items grid items-stretch gap-6 lg:gap-8',
        'mx-auto max-w-md' => count($plans) === 1,
        'md:grid-cols-2' => count($plans) >= 2,
        'mx-auto max-w-4xl' => count($plans) === 2,
        'lg:grid-cols-3' => count($plans) === 3,
        'lg:grid-cols-4' => count($plans) === 4,
    ])>
        @foreach ($plans as $plan)
            <div @class([
                'sb-item relative flex flex-col rounded-2xl border p-8 text-left',
                'border-current/15' => ! $plan['isFeatured'],
                'sb-item-highlight border-transparent ring-2 shadow-sb-card' => $plan['isFeatured'],
                // A lighter site colour on a dark background, so the ring shows.
                $isDark ? 'ring-primary-400' : 'ring-primary-600' => $plan['isFeatured'],
            ])>
                @if ($plan['isFeatured'] && $plan['badge'] !== '')
                    <span class="absolute -top-3 left-8 rounded-full bg-primary-600 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">{{ $plan['badge'] }}</span>
                @elseif ($plan['badge'] !== '')
                    <span class="mb-3 self-start rounded-full bg-current/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide">{{ $plan['badge'] }}</span>
                @endif

                <h3 class="sb-item-title text-xl font-bold">{{ $plan['name'] }}</h3>

                @if ($plan['description'] !== '')
                    <p class="sb-item-text mt-2 text-sm opacity-80">{{ $plan['description'] }}</p>
                @endif

                @if ($plan['price'] !== '')
                    <p class="mt-6 flex flex-wrap items-baseline gap-x-2">
                        <span class="sb-item-value text-4xl font-extrabold tracking-tight">{{ $plan['price'] }}</span>
                        @if ($plan['period'] !== '')
                            <span class="sb-item-meta text-sm opacity-70">{{ $plan['period'] }}</span>
                        @endif
                    </p>
                @endif

                @if ($plan['features'] !== [])
                    <ul class="sb-item-text mt-6 space-y-3 text-sm">
                        @foreach ($plan['features'] as $feature)
                            <li @class(['flex gap-3', 'opacity-55' => ! $feature['included']])>
                                @if ($feature['included'])
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $isDark ? 'text-current' : 'text-primary-600' }}" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                    <span>{{ $feature['text'] }}</span>
                                @else
                                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                    <span><span class="sr-only">{{ __('Not included:') }}</span> {{ $feature['text'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($plan['buttons'] !== [])
                    <div class="mt-auto pt-8">
                        @include('site-builder::blocks.partials.buttons', ['buttons' => $plan['buttons'], 'align' => 'left'])
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
