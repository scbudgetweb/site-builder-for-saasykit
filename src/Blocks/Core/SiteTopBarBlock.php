<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Theme Builder › Top bar (Divi's secondary menu bar): a slim row above the header with a short line (such as opening
 * hours), a phone number and an email address that can be tapped, and the social icons from SaaSykit's General
 * Settings. On the site colour, dark, unless its Background says otherwise; it can hide on phones.
 */
class SiteTopBarBlock extends PageBlock
{
    public const LAYOUTS = [
        'split' => 'The line and contact details on the left, social icons on the right',
        'centre' => 'Everything in the middle',
    ];

    public static function name(): string
    {
        return 'site_top_bar';
    }

    public static function label(): string
    {
        return __('Top bar');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedBars2;
    }

    public static function templateTypes(): ?array
    {
        return ['header'];
    }

    public static function defaultWidth(): string
    {
        return 'full';
    }

    public static function defaultSpacing(): string
    {
        return 'none';
    }

    public static function defaultBackground(): string
    {
        return 'brand_dark';
    }

    protected static function fields(): array
    {
        return [
            TextInput::make('text')
                ->label(__('A short line'))
                ->placeholder(__('Open Monday to Friday, 9am to 5pm'))
                ->maxLength(120),
            TextInput::make('phone')
                ->label(__('Phone number'))
                ->tel()
                ->placeholder('01234 567890')
                ->maxLength(40),
            TextInput::make('email')
                ->label(__('Email address'))
                ->email()
                ->placeholder('hello@example.com')
                ->maxLength(150),
            Toggle::make('show_social')
                ->label(__('Social icons'))
                ->helperText(__('One for each network with a link in Settings › General Settings › Social Links.'))
                ->default(true),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('layout')
                ->label(__('Line up'))
                ->options(array_map(fn (string $label): string => __($label), self::LAYOUTS))
                ->default('split')
                ->selectablePlaceholder(false),
            Select::make('content_width')
                ->label(__('Content width'))
                ->options(array_map(fn (array $width): string => __($width[0]), SiteHeaderBlock::WIDTHS))
                ->default('contained')
                ->selectablePlaceholder(false),
            Toggle::make('hide_on_phones')
                ->label(__('Hide on phones'))
                ->helperText(__('The phone number and email can go at the bottom of the mobile menu instead (Website › Navigation › Mobile menu).')),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $text = fn (string $key): string => is_string($data[$key] ?? null) ? trim($data[$key]) : '';
        $phone = mb_substr($text('phone'), 0, 40);
        $email = $text('email');

        return [
            'line' => $text('text'),
            'phone' => $phone,
            'phoneHref' => preg_replace('/[^0-9+]/', '', $phone) ?? '',
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '',
            'socialLinks' => ($data['show_social'] ?? true) !== false ? SocialFollowBlock::links() : [],
            'isCentred' => static::pick($data['layout'] ?? null, self::LAYOUTS, 'split') === 'centre',
            'widthClass' => SiteHeaderBlock::WIDTHS[static::pick($data['content_width'] ?? null, SiteHeaderBlock::WIDTHS, 'contained')][1],
            'hidesOnPhones' => (bool) ($data['hide_on_phones'] ?? false),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
