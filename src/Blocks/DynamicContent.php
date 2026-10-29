<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Closure;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use Throwable;

/**
 * Dynamic content: tags such as {{ page_title }} in a block's text (headings, rich text, buttons) that show a
 * value when the page is drawn. A tag can have a fallback for when there's no value: {{ user_name | there }}. In rich
 * text they're Filament's merge tags (type {{ or use the toolbar).
 *
 * Built in: the page title, the site name, today's date, the year and the signed-in visitor's name. Apps add their own
 * with SiteBuilder::dynamicContent() (the blog templates add the post's fields). A tag that isn't known is left as it is.
 *
 * Values are replaced in the block's finished HTML, escaped, so a value can never add markup (and braces in a value
 * can't make another tag). A block with a tag that differs by visitor, time or page (any but the site name) is drawn
 * on each request, as a "hole" in the cached page (PageRenderer).
 */
final class DynamicContent
{
    private const PATTERN = '/\{\{\s*([a-z][a-z0-9_]{0,39})\s*(?:\|\s*([^{}<>|]{0,80}?)\s*)?\}\}/';

    /**
     * Every tag: name => [label, value (given the page being viewed), whether it differs by visitor, time or page].
     *
     * @return array<string, array{0: string, 1: Closure(Page): mixed, 2: bool}>
     */
    public static function tags(): array
    {
        return [
            'page_title' => [__('Page title'), fn (Page $page): ?string => $page->title, true],
            'site_name' => [__('Site name'), fn (): mixed => config('app.name'), false],
            'current_date' => [__('Today\'s date'), fn (): string => now()->translatedFormat('j F Y'), true],
            'current_year' => [__('This year'), fn (): string => now()->format('Y'), true],
            'user_name' => [__('Visitor\'s name (signed in)'), fn (): mixed => auth()->user()?->name ?? null, true],
            ...app(SiteBuilder::class)->registeredDynamicContent(),
        ];
    }

    /**
     * Tag name => label, for the rich editor's merge tags and the help text.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(fn (array $tag): string => $tag[0], self::tags());
    }

    /**
     * Tag name => the tag itself, so the rich text renderer leaves each merge tag as text for replace() to fill in.
     *
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        return array_combine(array_keys(self::tags()), array_map(fn (string $name): string => "{{ {$name} }}", array_keys(self::tags())));
    }

    /**
     * Whether the block's data has a tag that differs by visitor, time or page, so it can't be cached with the page.
     *
     * @param  array<string, mixed>  $data
     */
    public static function isPerRequest(array $data): bool
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($json) || ! str_contains($json, '{{') || preg_match_all(self::PATTERN, $json, $matches) === 0) {
            return false;
        }

        $tags = self::tags();

        foreach ($matches[1] as $name) {
            if ($tags[$name][2] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The HTML with each known tag replaced by its value (or its fallback), escaped.
     */
    public static function replace(string $html, Page $page): string
    {
        if (! str_contains($html, '{{')) {
            return $html;
        }

        $tags = self::tags();
        $values = [];

        return (string) preg_replace_callback(self::PATTERN, function (array $match) use ($tags, $page, &$values): string {
            $name = $match[1];

            if (! isset($tags[$name])) {
                return $match[0];
            }

            if (! array_key_exists($name, $values)) {
                try {
                    $value = ($tags[$name][1])($page);
                } catch (Throwable $exception) {
                    report($exception);
                    $value = null;
                }

                $values[$name] = is_scalar($value) ? trim((string) $value) : '';
            }

            // The fallback was typed into the block, so it's already escaped in the HTML.
            $text = $values[$name] !== '' ? e($values[$name]) : ($match[2] ?? '');

            return str_replace(['{', '}'], ['&#123;', '&#125;'], $text);
        }, $html);
    }
}
