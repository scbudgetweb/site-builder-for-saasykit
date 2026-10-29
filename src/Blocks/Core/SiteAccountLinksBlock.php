<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Theme Builder: Log in and a button (e.g. Get started) for visitors, or the account menu once signed in, as the
 * standard header has. Not cached: it depends on who's looking.
 */
class SiteAccountLinksBlock extends PageBlock
{
    public const ALIGNMENTS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

    public static function name(): string
    {
        return 'site_account_links';
    }

    public static function label(): string
    {
        return __('Account links');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedUserCircle;
    }

    public static function templateTypes(): ?array
    {
        return ['header', 'footer'];
    }

    public static function isCacheable(array $data): bool
    {
        return false;
    }

    protected static function fields(): array
    {
        return [
            TextInput::make('login_label')
                ->label(__('Log in text'))
                ->hintIcon(self::HELP_ICON, __('Shown to visitors who aren\'t signed in. Signed in, they see their account menu instead.'))
                ->placeholder(__('Log in'))
                ->maxLength(40),
            Toggle::make('show_button')
                ->label(__('Show a button too'))
                ->default(true)
                ->live(),
            TextInput::make('button_label')
                ->label(__('Button text'))
                ->placeholder(__('Get started'))
                ->maxLength(40)
                ->visible(fn (Get $get): bool => (bool) $get('show_button')),
            TextInput::make('button_url')
                ->label(__('Button link'))
                ->hintIcon(self::HELP_ICON, __('Blank: the pricing on the home page. Or a page on this site (/about-us), a part of a page (#pricing) or a full web address.'))
                ->placeholder('/#pricing')
                ->maxLength(500)
                ->regex(self::LINK_PATTERN)
                ->visible(fn (Get $get): bool => (bool) $get('show_button')),
            Select::make('align')
                ->label(__('Line up'))
                ->options(array_map(fn (string $label): string => __($label), self::ALIGNMENTS))
                ->default('right')
                ->selectablePlaceholder(false),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $text = fn (string $key, string $default): string => is_string($data[$key] ?? null) && trim($data[$key]) !== '' ? trim($data[$key]) : $default;
        $url = is_string($data['button_url'] ?? null) && preg_match(self::LINK_PATTERN, $data['button_url']) === 1 ? $data['button_url'] : route('home').'#pricing';

        return [
            'loginLabel' => $text('login_label', __('Log in')),
            'showButton' => (bool) ($data['show_button'] ?? true),
            'buttonLabel' => $text('button_label', __('Get started')),
            'buttonUrl' => $url,
            'align' => static::pick($data['align'] ?? null, self::ALIGNMENTS, 'right'),
            // SaaSykit's account menu; an app without it shows nothing for signed-in visitors.
            'hasUserMenu' => view()->exists('components.layouts.app.user-menu'),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
