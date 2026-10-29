{{-- The mailing list sign-up form (MailingListSignup). Background and text colours: Branding › Mailing list. --}}
<div>
    @if ($isAvailable)
        <x-site-builder::banner variant="app_newsletter" class="overflow-hidden rounded-sb-card">
            <div class="relative mx-auto max-w-2xl px-4 py-12 text-center sm:px-6 sm:py-14">
                @if (filled($eyebrow))
                    <p class="text-sm font-semibold uppercase tracking-wider text-sb-banner-heading">{{ $eyebrow }}</p>
                @endif
                <h2 class="mt-2 text-2xl font-bold leading-tight text-sb-banner-heading sm:text-3xl">{{ $title }}</h2>
                @if (filled($subtext))
                    <p class="mt-3 text-base text-sb-banner-subtitle sm:text-lg">{{ $subtext }}</p>
                @endif

                @if ($isSubscribed)
                    <p class="mt-8 text-lg font-semibold text-sb-banner-heading" role="status">{{ $thanks }}</p>
                @else
                    <form wire:submit="subscribe" class="mx-auto mt-8 max-w-lg text-left" novalidate>
                        @if ($asksName)
                            <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="mailing-list-first-{{ $this->getId() }}" class="sr-only">{{ __('First name') }}</label>
                                    <input id="mailing-list-first-{{ $this->getId() }}" type="text" wire:model="firstName" autocomplete="given-name" placeholder="{{ __('First name') }}"
                                           class="w-full rounded-sb-input border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                <div>
                                    <label for="mailing-list-last-{{ $this->getId() }}" class="sr-only">{{ __('Last name') }}</label>
                                    <input id="mailing-list-last-{{ $this->getId() }}" type="text" wire:model="lastName" autocomplete="family-name" placeholder="{{ __('Last name') }}"
                                           class="w-full rounded-sb-input border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-col gap-3 sm:flex-row">
                            <label for="mailing-list-email-{{ $this->getId() }}" class="sr-only">{{ __('Email address') }}</label>
                            <input id="mailing-list-email-{{ $this->getId() }}" type="email" wire:model="email" autocomplete="email" required
                                   placeholder="{{ $placeholder }}"
                                   @error('email') aria-invalid="true" aria-describedby="mailing-list-error-{{ $this->getId() }}" @enderror
                                   class="min-w-0 flex-1 rounded-sb-input border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <button type="submit" wire:loading.attr="disabled"
                                    class="site-button site-button-{{ $buttonSlot }} inline-flex items-center justify-center px-6 py-3 text-sm font-semibold disabled:opacity-60">
                                <span wire:loading.remove wire:target="subscribe">{{ $button }}</span>
                                <span wire:loading wire:target="subscribe">{{ __('Signing up…') }}</span>
                            </button>
                        </div>

                        @if ($requiresConsent)
                            <label class="mt-4 flex items-start gap-3 text-sm text-sb-banner-subtitle">
                                <input type="checkbox" wire:model="consent" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300">
                                {{-- Escaped in MailingListSignup::consentHtml(); only the two legal links are HTML. --}}
                                <span @if ($consentColor) style="color: {{ $consentColor }};" @endif>{!! $consentHtml !!}</span>
                            </label>
                        @endif

                        @foreach (['email', 'firstName', 'lastName', 'consent'] as $field)
                            @error($field)
                                <p @if ($field === 'email') id="mailing-list-error-{{ $this->getId() }}" @endif class="mt-3 text-center text-sm font-medium text-sb-banner-heading" role="alert">{{ $message }}</p>
                            @enderror
                        @endforeach

                        {{-- Honeypot: hidden from people and screen readers; only bots fill it in. --}}
                        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                            <label for="mailing-list-website-{{ $this->getId() }}">{{ __('Leave this field empty') }}</label>
                            <input id="mailing-list-website-{{ $this->getId() }}" type="text" wire:model="website" tabindex="-1" autocomplete="off">
                        </div>
                    </form>
                @endif

                @if (! $requiresConsent && filled($consentHtml))
                    {{-- Escaped in MailingListSignup::consentHtml(); only the two legal links are HTML. --}}
                    <p class="mt-6 text-xs text-sb-banner-subtitle" @if ($consentColor) style="color: {{ $consentColor }};" @endif>{!! $consentHtml !!}</p>
                @endif
            </div>
        </x-site-builder::banner>
    @endif
</div>
