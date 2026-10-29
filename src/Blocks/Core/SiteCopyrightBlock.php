<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;

/**
 * Theme Builder: the copyright line, from Website › Navigation › Footer, or its own text (":year" becomes this year).
 */
class SiteCopyrightBlock extends PageBlock
{
    public const SOURCES = ['navigation' => 'The one in Website › Navigation › Footer', 'own' => 'Its own text'];

    public const ALIGNMENTS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

    public static function name(): string
    {
        return 'site_copyright';
    }

    public static function label(): string
    {
        return __('Copyright');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedScale;
    }

    public static function templateTypes(): ?array
    {
        return ['header', 'footer'];
    }

    /** Its copyright line is body text (.block-text) of its own, not rich text. */
    public static function hasBodyText(): bool
    {
        return true;
    }

    protected static function fields(): array
    {
        return [
            Select::make('source')
                ->label(__('Copyright line'))
                ->options(array_map(fn (string $label): string => __($label), self::SOURCES))
                ->default('navigation')
                ->selectablePlaceholder(false)
                ->live(),
            TextInput::make('text')
                ->label(__('Text'))
                ->hintIcon(self::HELP_ICON, __(':year becomes this year, e.g. "© :year Village Hall".'))
                ->maxLength(200)
                ->placeholder('© :year '.config('app.name'))
                ->visible(fn (Get $get): bool => $get('source') === 'own'),
            Select::make('align')
                ->label(__('Line up'))
                ->options(array_map(fn (string $label): string => __($label), self::ALIGNMENTS))
                ->default('left')
                ->selectablePlaceholder(false),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $own = is_string($data['text'] ?? null) ? trim($data['text']) : '';

        return [
            // Navigation's line is escaped there, with only its simple links kept (NavigationMenus::linkedText()).
            'copyright' => static::pick($data['source'] ?? null, self::SOURCES, 'navigation') === 'own' && $own !== ''
                ? new HtmlString(e(str_replace(':year', date('Y'), $own)))
                : app(NavigationMenus::class)->footer()['copyrightHtml'],
            'align' => static::pick($data['align'] ?? null, self::ALIGNMENTS, 'left'),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
