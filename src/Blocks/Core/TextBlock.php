<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Formatted text, with optional buttons above or below it.
 */
class TextBlock extends PageBlock
{
    public const BUTTON_POSITIONS = ['after_text', 'before_text'];

    /** Text style => label. "standard" is plain body text; the rest are classes in site-builder.css (.sb-text-…). */
    public const TEXT_STYLES = [
        'standard' => 'Standard',
        'lead' => 'Lead (a larger introduction)',
        'statement' => 'Statement (large, in the heading font, with an accent line)',
        'note' => 'Note (small and muted)',
    ];

    public static function name(): string
    {
        return 'text';
    }

    public static function label(): string
    {
        return __('Text');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedDocumentText;
    }

    public static function defaultWidth(): string
    {
        return 'narrow';
    }

    protected static function fields(): array
    {
        return [
            static::eyebrowFields(),
            static::richText('content', __('Text'))->required(),
            static::buttonsRepeater(2),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('align')
                ->label(__('Alignment'))
                ->options(['left' => __('Left'), 'center' => __('Centre')])
                ->default('left')
                ->selectablePlaceholder(false),
            static::buttonPositionSelect(self::BUTTON_POSITIONS),
        ];
    }

    protected static function styleToggleTitle(): string
    {
        return __('Text style');
    }

    protected static function styleFields(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('text_style')
                    ->label(__('Text style'))
                    ->hintIcon(self::HELP_ICON, __('A ready-made look for the whole text. Design › Body text and Text size still change it.'))
                    ->options(array_map(fn (string $label): string => __($label), self::TEXT_STYLES))
                    ->default('standard')
                    ->selectablePlaceholder(false)
                    ->live(),
                SiteColour::make('accent_colour')
                    ->label(__('Accent line colour'))
                    ->helperText(__('Blank: the site colour.'))
                    ->visible(fn (Get $get): bool => $get('text_style') === 'statement'),
            ]),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        $text = is_string($state['content'] ?? null) ? trim(strip_tags($state['content'])) : '';

        return $text !== '' ? static::label().': '.Str::limit($text, 60) : static::label();
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'html' => static::html($data['content'] ?? null),
            'isCentered' => ($data['align'] ?? 'left') === 'center',
            'buttons' => static::validButtons($data['buttons'] ?? null),
            'buttonPosition' => static::buttonPosition($data, self::BUTTON_POSITIONS),
            'textStyle' => static::pick($data['text_style'] ?? null, self::TEXT_STYLES, 'standard'),
            'accentColour' => Colour::css($data['accent_colour'] ?? null),
        ];
    }
}
