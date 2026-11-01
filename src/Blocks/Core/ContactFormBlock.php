<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * A contact form: name, email, phone, subject and message, as the block chooses. Each
 * message is kept in Website › Contact messages and, unless the block turns it off, emailed to its Deliver to address or
 * SaaSykit's Support Email. The form itself is ContactForm (Livewire), with the block's choices locked in.
 */
class ContactFormBlock extends PageBlock
{
    /** A field's choice => label. */
    public const FIELD_CHOICES = ['required' => 'Asked, required', 'optional' => 'Asked, optional', 'hidden' => 'Not asked'];

    public static function name(): string
    {
        return 'contact_form';
    }

    public static function label(): string
    {
        return __('Contact form');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedEnvelope;
    }

    public static function defaultWidth(): string
    {
        return 'narrow';
    }

    /** Its submit button takes Design › Buttons. */
    public static function hasButtons(): bool
    {
        return true;
    }

    /**
     * A form for each visitor (Livewire, with its own token), never cached.
     */
    public static function isCacheable(array $data): bool
    {
        return false;
    }

    protected static function fields(): array
    {
        $choices = array_map(fn (string $label): string => __($label), self::FIELD_CHOICES);
        $field = fn (string $name, string $label, string $default): Select => Select::make($name)
            ->label($label)
            ->options($choices)
            ->default($default)
            ->selectablePlaceholder(false);

        return [
            ...static::headingFields(),
            Grid::make(['default' => 1, 'sm' => 3])->schema([
                $field('name_field', __('Name'), 'required'),
                $field('phone_field', __('Phone number'), 'hidden'),
                $field('subject_field', __('Subject'), 'hidden'),
            ]),
            TextInput::make('form_name')
                ->label(__('Form name (optional)'))
                ->hintIcon(self::HELP_ICON, __('Shown with each message in Website › Contact messages, so you can tell forms apart. Blank: the heading, or the page\'s title. Visitors never see it.'))
                ->maxLength(100),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Toggle::make('send_email')
                    ->label(__('Email messages'))
                    ->hintIcon(self::HELP_ICON, __('Each message is always kept in Website › Contact messages. With this on, it\'s emailed too, and replying to the email answers the visitor.'))
                    ->default(true)
                    ->inline(false)
                    ->live(),
                TextInput::make('deliver_to')
                    ->label(__('Deliver to'))
                    ->email()
                    ->maxLength(255)
                    ->placeholder(fn (): ?string => self::supportEmail())
                    ->helperText(fn (): string => self::supportEmail() !== null
                        ? __('Blank: the Support Email in General settings (:email).', ['email' => self::supportEmail()])
                        : __('Blank: the Support Email in General settings, which isn\'t set yet.'))
                    ->visible(fn (Get $get): bool => $get('send_email') !== false),
            ]),
            Toggle::make('require_consent')
                ->label(__('Ask them to agree to their details being kept'))
                ->hintIcon(self::HELP_ICON, __('A tick box linking to your Privacy Policy. Some countries\' rules expect it.')),
            ToggleButtons::make('after')
                ->label(__('After sending'))
                ->options(['message' => __('Show a thank-you message'), 'redirect' => __('Go to a page')])
                ->default('message')
                ->inline()
                ->live(),
            TextInput::make('redirect_url')
                ->label(__('Page to go to'))
                ->placeholder('/thank-you')
                ->maxLength(500)
                ->regex('/^(https?:\/\/|\/)/i')
                ->validationMessages(['regex' => __('Start with / (a page on this site) or https://.')])
                ->required(fn (Get $get): bool => $get('after') === 'redirect')
                ->visible(fn (Get $get): bool => $get('after') === 'redirect'),
            BlockAppearance::moreOptions(__('Its own button text and thank-you message.'), [
                TextInput::make('button_text')
                    ->label(__('Button text'))
                    ->placeholder(__('Send message'))
                    ->maxLength(40),
                TextInput::make('success_message')
                    ->label(__('Thank-you message'))
                    ->placeholder(__('Thank you. Your message has been sent, and we\'ll be in touch soon.'))
                    ->maxLength(300),
            ]),
        ];
    }

    /** SaaSykit's Support Email (Admin › General settings › Application), or null when it isn't a valid address. */
    public static function supportEmail(): ?string
    {
        $email = config('app.support_email');

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Where the block's messages are emailed: its own Deliver to, or the Support Email; null when it doesn't email them.
     */
    public static function deliverTo(array $data): ?string
    {
        if (($data['send_email'] ?? true) === false) {
            return null;
        }

        $own = $data['deliver_to'] ?? null;

        return is_string($own) && filter_var($own, FILTER_VALIDATE_EMAIL) ? $own : self::supportEmail();
    }

    public static function viewData(array $data, Page $page): array
    {
        $choice = fn (string $key, string $default): string => static::pick($data[$key] ?? null, self::FIELD_CHOICES, $default);
        $text = fn (string $key, int $max): ?string => is_string($data[$key] ?? null) && filled($data[$key]) ? mb_substr($data[$key], 0, $max) : null;
        $redirect = ($data['after'] ?? 'message') === 'redirect' && is_string($data['redirect_url'] ?? null) && preg_match('/^(https?:\/\/|\/)/i', $data['redirect_url']) === 1
            ? $data['redirect_url']
            : null;

        return [
            'form' => [
                'nameField' => $choice('name_field', 'required'),
                'phoneField' => $choice('phone_field', 'hidden'),
                'subjectField' => $choice('subject_field', 'hidden'),
                'requiresConsent' => (bool) ($data['require_consent'] ?? false),
                'buttonText' => $text('button_text', 40),
                'successMessage' => $text('success_message', 300),
                'redirectUrl' => $redirect,
                'formName' => $text('form_name', 100) ?? $text('admin_label', 100) ?? $text('heading', 100) ?? ($page->exists ? $page->title : null),
                'pageId' => $page->exists ? $page->getKey() : null,
                'deliverTo' => self::deliverTo($data),
            ],
        ];
    }
}
