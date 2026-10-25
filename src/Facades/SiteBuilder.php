<?php

namespace ScBudgetWeb\SiteBuilder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ScBudgetWeb\SiteBuilder\Settings\Settings settings()
 * @method static string navigationGroup()
 * @method static string uploadDisk()
 * @method static string uploadDirectory(string $subfolder = '')
 * @method static \ScBudgetWeb\SiteBuilder\SiteBuilder slots(array<int, \ScBudgetWeb\SiteBuilder\Pages\Slot> $slots)
 * @method static array<string, \ScBudgetWeb\SiteBuilder\Pages\Slot> registeredSlots()
 * @method static \ScBudgetWeb\SiteBuilder\Pages\Slot|null slot(?string $key)
 * @method static \ScBudgetWeb\SiteBuilder\SiteBuilder blocks(array<int, class-string<\ScBudgetWeb\SiteBuilder\Blocks\PageBlock>> $blocks)
 * @method static array<int, class-string<\ScBudgetWeb\SiteBuilder\Blocks\PageBlock>> registeredBlocks()
 * @method static \ScBudgetWeb\SiteBuilder\SiteBuilder stats(array<string, array{0: string, 1: \Closure}> $sources)
 * @method static array<string, array{0: string, 1: \Closure}> statSources()
 * @method static \ScBudgetWeb\SiteBuilder\SiteBuilder links(array<int, \ScBudgetWeb\SiteBuilder\Navigation\Link> $links)
 * @method static array<string, \ScBudgetWeb\SiteBuilder\Navigation\Link> registeredLinks()
 * @method static \ScBudgetWeb\SiteBuilder\Navigation\Link|null link(?string $key)
 * @method static \ScBudgetWeb\SiteBuilder\SiteBuilder legalPages(array<int, \ScBudgetWeb\SiteBuilder\Legal\LegalPage> $pages)
 * @method static array<string, \ScBudgetWeb\SiteBuilder\Legal\LegalPage> registeredLegalPages()
 * @method static \ScBudgetWeb\SiteBuilder\SiteBuilder brandingColours(string $title, array<string, array{0: string, 1: string|array<int, string>}> $colours, array<string, string> $contrastPairs = [])
 * @method static array<string, \ScBudgetWeb\SiteBuilder\Branding\ColourGroup> registeredBrandingColours()
 * @method static \ScBudgetWeb\SiteBuilder\SiteBuilder consentChecker(\Closure $checker)
 * @method static bool consentAllows(string $category)
 *
 * @see \ScBudgetWeb\SiteBuilder\SiteBuilder
 */
class SiteBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ScBudgetWeb\SiteBuilder\SiteBuilder::class;
    }
}
