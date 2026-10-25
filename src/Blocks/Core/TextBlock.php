<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Formatted text, with optional buttons above or below it.
 */
class TextBlock extends PageBlock
{
    public const BUTTON_POSITIONS = ['after_text', 'before_text'];

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

    public static function headingSettings(): array
    {
        return [];
    }

    protected static function fields(): array
    {
        return [
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
        ];
    }
}
