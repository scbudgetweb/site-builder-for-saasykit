<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Support\Icons\Heroicon;

/**
 * A Banner made for the top of a landing page: taller, with a larger heading and subtitle by default.
 */
class HeroBlock extends BannerBlock
{
    public static function name(): string
    {
        return 'hero';
    }

    public static function label(): string
    {
        return __('Hero');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedSparkles;
    }

    public static function view(): string
    {
        return 'site-builder::blocks.banner';
    }

    protected static function defaultHeight(): string
    {
        return 'tall';
    }

    protected static function defaultHeadingSize(): string
    {
        return 'large';
    }

    protected static function defaultSubtitleSize(): string
    {
        return 'large';
    }
}
