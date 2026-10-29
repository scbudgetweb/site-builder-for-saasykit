<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\MailingLists\MailingListService;
use ScBudgetWeb\SiteBuilder\Models\MailingListProvider;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * The mailing list sign-up form (MailingListSignup). Styled in Branding › Mailing list; the block can have its own
 * wording, send sign-ups to another list on the account, ask for a name, ask them to tick the small print, and go to
 * a page afterwards. Shows nothing until Website › Mailing lists has a service and list in use. The API key is only
 * ever in Website › Mailing lists, never in a block.
 */
class MailingListBlock extends PageBlock
{
    public const NAME = 'mailing_list';

    public static function name(): string
    {
        return self::NAME;
    }

    public static function label(): string
    {
        return __('Mailing list sign-up');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedEnvelopeOpen;
    }

    public static function defaultWidth(): string
    {
        return 'narrow';
    }

    public static function isVisible(array $data): bool
    {
        return MailingListProvider::active() !== null;
    }

    /**
     * A form for each visitor (Livewire), never cached; in a Theme Builder template it's rendered on each request.
     */
    public static function isCacheable(array $data): bool
    {
        return false;
    }

    protected static function fields(): array
    {
        return [
            TextInput::make('heading')
                ->label(__('Heading'))
                ->helperText(__('Leave blank to use the heading from Branding › Mailing list.'))
                ->maxLength(120)
                ->live(onBlur: true),
            Textarea::make('intro')
                ->label(__('Text'))
                ->helperText(__('Leave blank to use the text from Branding › Mailing list.'))
                ->rows(2)
                ->maxLength(250),
            Select::make('list')
                ->label(__('Send sign-ups to'))
                ->hintIcon(self::HELP_ICON, __('The list people join. The default is the one chosen in Website › Mailing lists; or pick another list on the same account, e.g. one for this page\'s topic.'))
                ->options(fn (): array => ['' => __('The default list')] + self::accountLists())
                ->default('')
                ->selectablePlaceholder(false),
            Toggle::make('ask_name')
                ->label(__('Ask for their name'))
                ->hintIcon(self::HELP_ICON, __('Adds first and last name fields (both optional). Fewer fields usually means more sign-ups.')),
            Toggle::make('require_consent')
                ->label(__('Ask them to tick the small print'))
                ->hintIcon(self::HELP_ICON, __('A tick box with the small print from Branding › Mailing list (with your Privacy Policy and Terms). Some countries\' rules expect it.')),
            ToggleButtons::make('after')
                ->label(__('After signing up'))
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
                    ->helperText(__('Blank: from Branding.'))
                    ->maxLength(40),
                TextInput::make('success_message')
                    ->label(__('Thank-you message'))
                    ->helperText(__('Blank: from Branding.'))
                    ->maxLength(200),
            ]),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $list = is_string($data['list'] ?? null) && $data['list'] !== '' ? $data['list'] : null;
        $redirect = ($data['after'] ?? 'message') === 'redirect' && is_string($data['redirect_url'] ?? null) && preg_match('/^(https?:\/\/|\/)/i', $data['redirect_url']) === 1
            ? $data['redirect_url']
            : null;
        $text = fn (string $key, int $max): ?string => is_string($data[$key] ?? null) && filled($data[$key]) ? mb_substr($data[$key], 0, $max) : null;

        return [
            'signup' => [
                'heading' => $text('heading', 120),
                'intro' => $text('intro', 250),
                'buttonText' => $text('button_text', 40),
                'successMessage' => $text('success_message', 200),
                'listId' => $list,
                'asksName' => (bool) ($data['ask_name'] ?? false),
                'requiresConsent' => (bool) ($data['require_consent'] ?? false),
                'redirectUrl' => $redirect,
            ],
        ];
    }

    /**
     * The lists on the account in use, for "Send sign-ups to".
     *
     * @return array<string, string>
     */
    private static function accountLists(): array
    {
        $provider = MailingListProvider::active();

        return $provider === null ? [] : app(MailingListService::class)->lists($provider);
    }
}
