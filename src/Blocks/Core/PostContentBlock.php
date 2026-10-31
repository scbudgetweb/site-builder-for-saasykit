<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * Blog post template: the post's text, as written in Blog › Posts, styled as rich text is (Design › Heading text and
 * Body text). Sanitised as every block's rich text is, and its braces kept as text so it can't hold dynamic content.
 */
class PostContentBlock extends PageBlock
{
    public static function name(): string
    {
        return 'post_content';
    }

    public static function label(): string
    {
        return __('Post content');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedDocumentText;
    }

    public static function templateTypes(): ?array
    {
        return [Templates::BLOG_POST];
    }

    /** Rich text of its own (.block-text.sb-prose). */
    public static function hasBodyText(): bool
    {
        return true;
    }

    public static function defaultWidth(): string
    {
        return 'narrow';
    }

    protected static function fields(): array
    {
        return [
            Text::make(__('Shows the text of the post being viewed, as written in Blog › Posts. Its look is in Design: Heading text and Body text.')),
        ];
    }

    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function isVisible(array $data): bool
    {
        return trim(app(Blog::class)->post()->body ?? '') !== '';
    }

    public static function viewData(array $data, Page $page): array
    {
        $body = app(Blog::class)->post()->body ?? '';

        return [
            'html' => str_replace(['{', '}'], ['&#123;', '&#125;'], Str::sanitizeHtml($body)),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
