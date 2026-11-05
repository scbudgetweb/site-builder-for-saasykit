<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
 * A contact form, its fields a list the block sets (Fields): a type, a label, a placeholder, required or not, full or
 * half width, choices for Dropdown, Radio buttons and Checkboxes, and help text. Every form has one Email field, which
 * replying uses. Each message is kept in Website › Contact messages and, unless the block turns it off, emailed to its
 * Deliver to address or SaaSykit's Support Email. The form itself is ContactForm (Livewire), with the block's choices
 * locked in.
 *
 * A field is saved as {type, label, placeholder, required, width, options (one per line), help}. Blocks saved before
 * 1.7.2 chose Name, Phone and Subject as Required, Optional or Not asked (name_field, phone_field, subject_field);
 * upgrade() turns those into the same fields, in the same order and widths.
 */
class ContactFormBlock extends PageBlock
{
    public const NAME = 'contact_form';

    /** A field type => label. Name and Email are the visitor's own; Email is what replying uses. */
    public const FIELD_TYPES = [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'text' => 'Text',
        'textarea' => 'Long text',
        'select' => 'Dropdown',
        'radio' => 'Radio buttons',
        'checkboxes' => 'Checkboxes',
    ];

    /** The types with choices (one per line in Choices). */
    public const CHOICE_TYPES = ['select', 'radio', 'checkboxes'];

    public const WIDTHS = ['full' => 'Full width', 'half' => 'Half width'];

    public const MAX_FIELDS = 30;

    public const MAX_CHOICES = 50;

    /** Before 1.7.2: a field's choice => label (name_field, phone_field, subject_field). */
    public const FIELD_CHOICES = ['required' => 'Asked, required', 'optional' => 'Asked, optional', 'hidden' => 'Not asked'];

    public static function name(): string
    {
        return self::NAME;
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
        return [
            ...static::headingFields(),
            self::fieldsRepeater(),
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

    /**
     * Fields: one card per field, in the form's order.
     */
    protected static function fieldsRepeater(): Repeater
    {
        $hasChoices = fn (Get $get): bool => in_array($get('type'), self::CHOICE_TYPES, true);

        return static::itemTooltips(Repeater::make('fields'), __('field'))
            ->label(__('Fields'))
            ->hintIcon(self::HELP_ICON, __('The form\'s fields, in order. Every form has one Email field, which replying uses: it can be moved and renamed, not removed.'))
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    Select::make('type')
                        ->label(__('Type'))
                        ->options(array_map(fn (string $label): string => __($label), self::FIELD_TYPES))
                        ->default('text')
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live(),
                    TextInput::make('label')
                        ->label(__('Label'))
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true),
                    TextInput::make('placeholder')
                        ->label(__('Placeholder'))
                        ->hintIcon(self::HELP_ICON, __('Faint example text in the empty field, e.g. "Sam Jones".'))
                        ->maxLength(100)
                        ->visible(fn (Get $get): bool => ! in_array($get('type'), [...self::CHOICE_TYPES], true)),
                    ToggleButtons::make('width')
                        ->label(__('Width'))
                        ->hintIcon(self::HELP_ICON, __('Two half-width fields sit side by side, and stack on phones.'))
                        ->options(array_map(fn (string $label): string => __($label), self::WIDTHS))
                        ->default('full')
                        ->inline(),
                    Textarea::make('options')
                        ->label(__('Choices'))
                        ->helperText(__('One per line.'))
                        ->rows(4)
                        ->required($hasChoices)
                        ->maxLength(5000)
                        ->visible($hasChoices)
                        ->columnSpanFull(),
                    TextInput::make('help')
                        ->label(__('Help text (optional)'))
                        ->hintIcon(self::HELP_ICON, __('A short line under the field.'))
                        ->maxLength(200)
                        ->columnSpanFull(),
                    Toggle::make('required')
                        ->label(__('Required'))
                        ->default(true)
                        ->visible(fn (Get $get): bool => $get('type') !== 'email')
                        ->columnSpanFull(),
                ]),
            ])
            ->default(fn (): array => self::standardFields())
            ->minItems(1)
            ->maxItems(self::MAX_FIELDS)
            ->collapsible()
            ->collapsed()
            ->itemLabel(fn (array $state): ?string => self::fieldTitle($state))
            ->addActionLabel(__('Add a field'))
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $types = array_column(is_array($value) ? array_filter($value, 'is_array') : [], 'type');
                $counts = array_count_values(array_filter($types, 'is_string'));

                if (($counts['email'] ?? 0) !== 1) {
                    $fail(__('A form needs one Email field: replying to a message uses it.'));
                }

                if (($counts['name'] ?? 0) > 1) {
                    $fail(__('A form can have one Name field. Use Text for any other name.'));
                }
            });
    }

    /**
     * A field card's title: its label and type, e.g. "Your name · Name".
     *
     * @param  array<string, mixed>  $state
     */
    public static function fieldTitle(array $state): ?string
    {
        $type = self::FIELD_TYPES[(string) ($state['type'] ?? '')] ?? null;
        $label = is_string($state['label'] ?? null) && filled($state['label']) ? $state['label'] : null;

        return match (true) {
            $label !== null && $type !== null => $label.' · '.__($type),
            default => $label ?? ($type !== null ? __($type) : null),
        };
    }

    /**
     * A new form's fields: Name and Email side by side, then Message.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function standardFields(): array
    {
        return [
            ['type' => 'name', 'label' => __('Name'), 'required' => true, 'width' => 'half'],
            ['type' => 'email', 'label' => __('Email address'), 'required' => true, 'width' => 'half'],
            ['type' => 'textarea', 'label' => __('Message'), 'required' => true, 'width' => 'full'],
        ];
    }

    /**
     * A block saved before 1.7.2 (name_field, phone_field and subject_field: required, optional or hidden) with the
     * same form as a Fields list: Name, Email, Phone, Subject in pairs (an odd one out at the end on its own row), then
     * Message. A block with a Fields list already is left as it is.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function upgrade(array $data): array
    {
        if (array_key_exists('fields', $data)) {
            return $data;
        }

        $choice = fn (string $key, string $default): string => static::pick($data[$key] ?? null, self::FIELD_CHOICES, $default);
        $asked = array_values(array_filter([
            ($name = $choice('name_field', 'required')) !== 'hidden' ? ['type' => 'name', 'label' => __('Name'), 'required' => $name === 'required'] : null,
            ['type' => 'email', 'label' => __('Email address'), 'required' => true],
            ($phone = $choice('phone_field', 'hidden')) !== 'hidden' ? ['type' => 'phone', 'label' => __('Phone number'), 'required' => $phone === 'required'] : null,
            ($subject = $choice('subject_field', 'hidden')) !== 'hidden' ? ['type' => 'text', 'label' => __('Subject'), 'required' => $subject === 'required'] : null,
        ]));

        foreach ($asked as $index => $field) {
            $asked[$index]['width'] = count($asked) % 2 === 1 && $index === count($asked) - 1 ? 'full' : 'half';
        }

        unset($data['name_field'], $data['phone_field'], $data['subject_field']);

        return [...$data, 'fields' => [...$asked, ['type' => 'textarea', 'label' => __('Message'), 'required' => true, 'width' => 'full']]];
    }

    /**
     * The form's fields, as the visitor's form uses them: known types only, each with a label, its choices as a list,
     * and exactly one Email field (always required) and at most one Name field, whatever was saved.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{type: string, label: string, placeholder: string|null, required: bool, width: string, options: array<int, string>, help: string|null}>
     */
    public static function formFields(array $data): array
    {
        $saved = self::upgrade($data)['fields'];
        $fields = [];
        $text = fn (mixed $value, int $max): ?string => is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;

        foreach (is_array($saved) ? array_values(array_filter($saved, 'is_array')) : [] as $field) {
            $type = static::pick($field['type'] ?? null, self::FIELD_TYPES, 'text');
            $taken = array_column($fields, 'type');

            if (in_array($type, ['name', 'email'], true) && in_array($type, $taken, true)) {
                $type = 'text';
            }

            $options = in_array($type, self::CHOICE_TYPES, true) ? self::choices($field['options'] ?? null) : [];

            if (in_array($type, self::CHOICE_TYPES, true) && $options === []) {
                continue;
            }

            $fields[] = [
                'type' => $type,
                'label' => $text($field['label'] ?? null, 100) ?? __(self::FIELD_TYPES[$type]),
                'placeholder' => in_array($type, self::CHOICE_TYPES, true) ? null : $text($field['placeholder'] ?? null, 100),
                'required' => $type === 'email' || (bool) ($field['required'] ?? false),
                'width' => static::pick($field['width'] ?? null, self::WIDTHS, 'full'),
                'options' => $options,
                'help' => $text($field['help'] ?? null, 200),
            ];

            if (count($fields) === self::MAX_FIELDS) {
                break;
            }
        }

        if (! in_array('email', array_column($fields, 'type'), true)) {
            array_unshift($fields, ['type' => 'email', 'label' => __('Email address'), 'placeholder' => null, 'required' => true, 'width' => 'full', 'options' => [], 'help' => null]);
        }

        return $fields;
    }

    /**
     * Choices saved one per line (or as a list): trimmed, without blanks or repeats.
     *
     * @return array<int, string>
     */
    public static function choices(mixed $options): array
    {
        $lines = is_array($options) ? $options : preg_split('/\R/', is_string($options) ? $options : '');
        $choices = [];

        foreach ($lines as $line) {
            if (is_string($line) && ($line = mb_substr(trim($line), 0, 150)) !== '' && ! in_array($line, $choices, true)) {
                $choices[] = $line;
            }
        }

        return array_slice($choices, 0, self::MAX_CHOICES);
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
        $text = fn (string $key, int $max): ?string => is_string($data[$key] ?? null) && filled($data[$key]) ? mb_substr($data[$key], 0, $max) : null;
        $redirect = ($data['after'] ?? 'message') === 'redirect' && is_string($data['redirect_url'] ?? null) && preg_match('/^(https?:\/\/|\/)/i', $data['redirect_url']) === 1
            ? $data['redirect_url']
            : null;

        return [
            'form' => [
                'fields' => self::formFields($data),
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
