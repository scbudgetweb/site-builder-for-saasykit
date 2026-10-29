<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blocks;

use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * A block that depends on the visitor (it can't be cached): it greets whoever is signed in. For the Theme Builder's
 * cached templates, which render such a block on every request.
 */
class VisitorGreetingBlock extends TextBlock
{
    public static function name(): string
    {
        return 'visitor_greeting';
    }

    public static function label(): string
    {
        return 'Visitor greeting';
    }

    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function viewData(array $data, Page $page): array
    {
        return [...parent::viewData($data, $page), 'html' => e('Hello, '.(auth()->user()?->getAttribute('name') ?? 'guest'))];
    }

    public static function view(): string
    {
        return 'site-builder::blocks.text';
    }
}
