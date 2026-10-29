<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * A heading, a line of text and buttons, on the dark site colour by default.
 */
class CallToActionBlock extends PageBlock
{
    public static function name(): string
    {
        return 'call_to_action';
    }

    public static function label(): string
    {
        return __('Call to action');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedMegaphone;
    }

    public static function hasButtons(): bool
    {
        return true;
    }

    public static function defaultBackground(): string
    {
        return 'brand_dark';
    }

    /** Its own heading (.block-heading), so only its size. */
    public static function headingSettings(): array
    {
        return ['size'];
    }

    /** Its own text (.block-text), not the shared heading's intro or rich text. */
    public static function hasBodyText(): bool
    {
        return true;
    }

    protected static function fields(): array
    {
        return [
            static::eyebrowFields(),
            TextInput::make('heading')->label(__('Heading'))->required()->maxLength(150)->live(onBlur: true),
            Textarea::make('text')->label(__('Text'))->rows(3)->maxLength(500),
            static::buttonsRepeater(2),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('align')
                ->label(__('Alignment'))
                ->options(['center' => __('Centre'), 'left' => __('Left')])
                ->default('center')
                ->selectablePlaceholder(false),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'isCentered' => ($data['align'] ?? 'center') === 'center',
            'buttons' => static::validButtons($data['buttons'] ?? null),
        ];
    }
}
