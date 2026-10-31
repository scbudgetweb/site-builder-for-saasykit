{{-- The Contact form block's form (ContactForm): each field labelled above its input; errors under it. --}}
@php
    // On a dark background (the block's text scheme is light), a lighter red that still reads.
    $errorClass = 'mt-1.5 text-sm font-medium text-red-600 [[data-text-scheme=light]_&]:text-red-300';
    $input = 'w-full rounded-sb-input border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-primary-500';
    $id = fn (string $field): string => 'contact-'.$field.'-'.$this->getId();
    $fields = array_filter([
        'name' => $nameField !== 'hidden' ? [__('Name'), 'text', 'name', $nameField === 'required'] : null,
        'email' => [__('Email address'), 'email', 'email', true],
        'phone' => $phoneField !== 'hidden' ? [__('Phone number'), 'tel', 'tel', $phoneField === 'required'] : null,
        'subject' => $subjectField !== 'hidden' ? [__('Subject'), 'text', 'off', $subjectField === 'required'] : null,
    ]);
@endphp
<div class="sb-contact-form">
    @if ($isSent)
        <p class="block-text rounded-sb-card border border-current/15 p-6 text-center text-lg font-medium" role="status">{{ $thanks }}</p>
    @else
        <form wire:submit="send" class="grid gap-5 text-left sm:grid-cols-2" novalidate>
            @foreach ($fields as $field => [$label, $type, $autocomplete, $isRequired])
                {{-- In pairs; an odd one out at the end takes the whole row. --}}
                <div @class(['sm:col-span-2' => count($fields) % 2 === 1 && $loop->last])>
                    <label for="{{ $id($field) }}" class="mb-1.5 block text-sm font-semibold">
                        {{ $label }}@unless ($isRequired) <span class="font-normal opacity-70">({{ __('optional') }})</span>@endunless
                    </label>
                    <input id="{{ $id($field) }}" type="{{ $type }}" wire:model="{{ $field }}" autocomplete="{{ $autocomplete }}" @if ($isRequired) required @endif
                           @error($field) aria-invalid="true" aria-describedby="{{ $id($field) }}-error" @enderror class="{{ $input }}">
                    @error($field)
                        <p id="{{ $id($field) }}-error" class="{{ $errorClass }}" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="sm:col-span-2">
                <label for="{{ $id('message') }}" class="mb-1.5 block text-sm font-semibold">{{ __('Message') }}</label>
                <textarea id="{{ $id('message') }}" wire:model="message" rows="6" required maxlength="{{ \ScBudgetWeb\SiteBuilder\Livewire\ContactForm::MAX_MESSAGE_LENGTH }}"
                          @error('message') aria-invalid="true" aria-describedby="{{ $id('message') }}-error" @enderror class="{{ $input }}"></textarea>
                @error('message')
                    <p id="{{ $id('message') }}-error" class="{{ $errorClass }}" role="alert">{{ $message }}</p>
                @enderror
            </div>

            @if ($requiresConsent)
                <div class="sm:col-span-2">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" wire:model="consent" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300">
                        <span>
                            @if ($privacyLink)
                                {{ __('I agree to my details being kept to answer my message, as the') }} <a href="{{ $privacyLink }}" class="underline hover:no-underline">{{ __('Privacy Policy') }}</a> {{ __('explains.') }}
                            @else
                                {{ __('I agree to my details being kept to answer my message.') }}
                            @endif
                        </span>
                    </label>
                    @error('consent')
                        <p class="{{ $errorClass }}" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            {{-- Honeypot: hidden from people and screen readers; only bots fill it in. --}}
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label for="{{ $id('website') }}">{{ __('Leave this field empty') }}</label>
                <input id="{{ $id('website') }}" type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="sm:col-span-2">
                <button type="submit" wire:loading.attr="disabled" class="{{ $buttonClass }} disabled:opacity-60" @if ($buttonStyle !== '') style="{{ $buttonStyle }}" @endif>
                    <span wire:loading.remove wire:target="send">{{ $button }}</span>
                    <span wire:loading wire:target="send">{{ __('Sending…') }}</span>
                </button>
            </div>
        </form>
    @endif
</div>
