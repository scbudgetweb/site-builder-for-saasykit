<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blocks;

use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;

/**
 * An app's block that only uses the shared helpers (rich text and up to three buttons) and declares nothing else: its
 * panel finds Design › Buttons and the text size setting from its fields.
 */
class TripleButtonBlock extends PageBlock
{
    public static function name(): string
    {
        return 'triple_button';
    }

    public static function label(): string
    {
        return __('Three buttons');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedCursorArrowRays;
    }

    protected static function fields(): array
    {
        return [
            static::richText('content', __('Text')),
            static::buttonsRepeater(max: 3),
        ];
    }

    public static function view(): string
    {
        return 'blocks.triple-button';
    }
}
