{{--
    The Contact form block's form (ContactForm): its fields in order, each labelled above its input with any help text
    under it, then errors. Half-width fields sit side by side from small screens up; the rest take the whole row.
--}}
@php
    // On a dark background (the block's text scheme is light), a lighter red that still reads.
    $errorClass = 'mt-1.5 text-sm font-medium text-red-600 [[data-text-scheme=light]_&]:text-red-300';
    $input = 'w-full rounded-sb-input border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-primary-500';
    $id = fn (string $field): string => 'contact-'.$field.'-'.$this->getId();
    $inputTypes = ['name' => ['text', 'name'], 'email' => ['email', 'email'], 'phone' => ['tel', 'tel'], 'text' => ['text', 'off']];
@endphp
<div class="sb-contact-form">
    @if ($isSent)
        <p class="block-text rounded-sb-card border border-current/15 p-6 text-center text-lg font-medium" role="status">{{ $thanks }}</p>
    @else
        <form wire:submit="send" class="grid gap-5 text-left sm:grid-cols-2" novalidate>
            @foreach ($fields as $index => $field)
                @php
                    $key = "answers.{$index}";
                    $fieldId = $id((string) $index);
                    $describedBy = trim(($field['help'] !== null ? $fieldId.'-help ' : '').($errors->has($key) || $errors->has($key.'.*') ? $fieldId.'-error' : ''));
                    $isChoiceList = in_array($field['type'], ['radio', 'checkboxes'], true);
                @endphp
                <div @class(['sb-contact-field', 'sm:col-span-2' => $field['width'] !== 'half'])>
                    @if ($isChoiceList)
                        <fieldset @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
                            <legend class="mb-1.5 block text-sm font-semibold">
                                {{ $field['label'] }}@unless ($field['required']) <span class="font-normal opacity-70">({{ __('optional') }})</span>@endunless
                            </legend>
                            <div class="flex flex-col gap-2">
                                @foreach ($field['options'] as $choiceIndex => $choice)
                                    <label class="flex items-start gap-3 text-sm">
                                        @if ($field['type'] === 'radio')
                                            <input type="radio" name="{{ $fieldId }}" value="{{ $choice }}" wire:model="{{ $key }}" class="mt-0.5 h-4 w-4 shrink-0 border-gray-300">
                                        @else
                                            <input type="checkbox" value="{{ $choice }}" wire:model="{{ $key }}" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300">
                                        @endif
                                        <span>{{ $choice }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @else
                        <label for="{{ $fieldId }}" class="mb-1.5 block text-sm font-semibold">
                            {{ $field['label'] }}@unless ($field['required']) <span class="font-normal opacity-70">({{ __('optional') }})</span>@endunless
                        </label>
                        @if ($field['type'] === 'textarea')
                            <textarea id="{{ $fieldId }}" wire:model="{{ $key }}" rows="6" maxlength="{{ \ScBudgetWeb\SiteBuilder\Livewire\ContactForm::MAX_MESSAGE_LENGTH }}"
                                      @if ($field['required']) required @endif @if ($field['placeholder'] !== null) placeholder="{{ $field['placeholder'] }}" @endif
                                      @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif @error($key) aria-invalid="true" @enderror class="{{ $input }}"></textarea>
                        @elseif ($field['type'] === 'select')
                            <select id="{{ $fieldId }}" wire:model="{{ $key }}" @if ($field['required']) required @endif
                                    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif @error($key) aria-invalid="true" @enderror class="{{ $input }}">
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($field['options'] as $choice)
                                    <option value="{{ $choice }}">{{ $choice }}</option>
                                @endforeach
                            </select>
                        @else
                            @php([$type, $autocomplete] = $inputTypes[$field['type']] ?? ['text', 'off'])
                            <input id="{{ $fieldId }}" type="{{ $type }}" wire:model="{{ $key }}" autocomplete="{{ $autocomplete }}" @if ($field['required']) required @endif
                                   @if ($field['placeholder'] !== null) placeholder="{{ $field['placeholder'] }}" @endif
                                   @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif @error($key) aria-invalid="true" @enderror class="{{ $input }}">
                        @endif
                    @endif
                    @if ($field['help'] !== null)
                        <p id="{{ $fieldId }}-help" class="mt-1.5 text-sm opacity-75">{{ $field['help'] }}</p>
                    @endif
                    @if ($errors->has($key) || $errors->has($key.'.*'))
                        <p id="{{ $fieldId }}-error" class="{{ $errorClass }}" role="alert">{{ $errors->first($key) ?: $errors->first($key.'.*') }}</p>
                    @endif
                </div>
            @endforeach

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
                @error('form')
                    <p class="{{ $errorClass }} mb-3" role="alert">{{ $message }}</p>
                @enderror
                <button type="submit" wire:loading.attr="disabled" class="{{ $buttonClass }} disabled:opacity-60" @if ($buttonStyle !== '') style="{{ $buttonStyle }}" @endif>
                    <span wire:loading.remove wire:target="send">{{ $button }}</span>
                    <span wire:loading wire:target="send">{{ __('Sending…') }}</span>
                </button>
            </div>
        </form>
    @endif
</div>
