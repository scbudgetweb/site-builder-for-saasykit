{{--
    Plans side by side, each a card (Design › Items) with its name, price, features and button; a featured plan stands
    out with a ring and its badge. Or SaaSykit's live plans ($isLive, partials/pricing-live), as the theme's home page shows them.

    $choices (PricingTableBlock::choices()), when there's a company size or billing to choose: the controls above the
    plans, and an Alpine component showing each plan's price for the choice (every price worked out on the server,
    the first choice rendered here) and adding it to the buttons' links (link(), kept in step with withChoice()).
--}}
@include('site-builder::blocks.partials.heading')

@if ($isLive)
    @include('site-builder::blocks.partials.pricing-live')
@else
    @php($isChoosing = $choices !== null)
    @if ($isChoosing)
        <div x-data="{
            size: 0,
            billing: @js($choices['billing']),
            prices: @js(array_column($plans, 'prices')),
            queries: @js($choices['queries']),
            price(plan) { return this.prices[plan][this.size][this.billing]; },
            link(url) {
                const query = this.queries?.[this.size]?.[this.billing] ?? '';
                if (query === '') return url;
                const at = url.indexOf('#');
                const base = at < 0 ? url : url.slice(0, at);
                return base + (base.includes('?') ? '&' : '?') + query + (at < 0 ? '' : url.slice(at));
            },
        }">
            <div x-cloak class="sb-pricing-choices mb-10 flex flex-wrap items-end justify-center gap-x-6 gap-y-4">
                @if ($choices['sizes'] !== [])
                    @php($sizeId = 'sb-pricing-size-'.($uid ?? substr(md5(json_encode($choices)), 0, 8)))
                    <div class="text-left">
                        <label for="{{ $sizeId }}" class="mb-1.5 block text-sm font-semibold">{{ $choices['sizeLabel'] }}</label>
                        <select id="{{ $sizeId }}" x-model.number="size" class="w-full min-w-56 rounded-sb-input border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-primary-500">
                            @foreach ($choices['sizes'] as $index => $size)
                                <option value="{{ $index }}" @selected($index === 0)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if (count($choices['billings']) > 1)
                    <div role="group" aria-label="{{ __('Billing') }}" class="inline-flex rounded-full border border-current/15 p-1">
                        @foreach (['monthly', 'annual'] as $billing)
                            <button type="button" x-on:click="billing = @js($billing)"
                                    aria-pressed="{{ $choices['billing'] === $billing ? 'true' : 'false' }}" x-bind:aria-pressed="(billing === @js($billing)).toString()"
                                    class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition aria-pressed:bg-primary-600 aria-pressed:text-white">
                                {{ $choices['labels'][$billing] }}
                                @if ($billing === 'annual' && $choices['saving'] > 0)
                                    <span class="rounded-full bg-current/15 px-2 py-0.5 text-xs">{{ __('Save :percent%', ['percent' => $choices['saving']]) }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
    @endif
    <div @class([
        'sb-items grid items-stretch gap-6 lg:gap-8',
        'mx-auto max-w-md' => count($plans) === 1,
        'md:grid-cols-2' => count($plans) >= 2,
        'mx-auto max-w-4xl' => count($plans) === 2,
        'lg:grid-cols-3' => count($plans) === 3,
        'lg:grid-cols-4' => count($plans) === 4,
    ])>
        @foreach ($plans as $planIndex => $plan)
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
                    <div class="mt-6" @if ($isChoosing) aria-live="polite" @endif>
                        <p class="flex flex-wrap items-baseline gap-x-2">
                            <span class="sb-item-value text-4xl font-extrabold tracking-tight" @if ($isChoosing) x-text="price({{ $planIndex }}).amount" @endif>{{ $plan['price'] }}</span>
                            @if ($plan['period'] !== '')
                                <span class="sb-item-meta text-sm opacity-70" @if ($isChoosing) x-show="! price({{ $planIndex }}).isAsk" @endif @if ($plan['isAsk']) style="display: none" @endif>{{ $plan['period'] }}</span>
                            @endif
                        </p>
                        @if ($isChoosing || $plan['note'] !== '')
                            <p class="sb-item-meta mt-1 text-sm opacity-70" @if ($isChoosing) x-text="price({{ $planIndex }}).note" x-show="price({{ $planIndex }}).note !== ''" @endif @if ($plan['note'] === '') style="display: none" @endif>{{ $plan['note'] }}</p>
                        @endif
                    </div>
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
                        @include('site-builder::blocks.partials.buttons', ['buttons' => $plan['buttons'], 'align' => 'left', 'choiceQuery' => $isChoosing ? ($choices['queries'][0][$choices['billing']] ?? null) : null])
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @if ($isChoosing)
        </div>
    @endif
@endif
