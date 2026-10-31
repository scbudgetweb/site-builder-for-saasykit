<?php

namespace ScBudgetWeb\SiteBuilder\Templates;

use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Blocks\Core\PostListBlock;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\Support\VersionedCache;
use Throwable;

/**
 * The Theme Builder: which template (Website › Theme builder) a type such as the header uses on the page being shown.
 * The most specific active one wins: one for "only" this page, then one for everywhere (or everywhere "except" pages
 * that don't include this one), the most recently saved first. With none, the theme's standard view shows. Blog post
 * and Blog archive templates apply by category in the same way (Blog::categoryId()).
 *
 * The list of active templates is cached (a few columns each, never their blocks), so finding one costs a cache read;
 * TemplateRenderer caches each template's HTML.
 */
class Templates
{
    public const CORE_TYPES = ['header' => 'Header', 'footer' => 'Footer'];

    public const BLOG_POST = 'blog_post';

    public const BLOG_ARCHIVE = 'blog_archive';

    /** Offered when the app has a blog (Blog::isAvailable()). They apply by category, not by page. */
    public const BLOG_TYPES = [self::BLOG_POST => 'Blog post', self::BLOG_ARCHIVE => 'Blog archive'];

    /** The request attribute holding the site builder page being shown, set by PageController. */
    public const PAGE_ATTRIBUTE = 'site-builder.page';

    /** The request attribute that hides the header and footer, set by a template's visual builder canvas. */
    public const HIDE_CHROME_ATTRIBUTE = 'site-builder.hide-chrome';

    private const CACHE_KEY = 'site-builder.templates';

    private const VERSION_KEY = 'site-builder.templates-version';

    public function __construct(private SiteBuilder $siteBuilder, private TemplateRenderer $renderer) {}

    /**
     * @return array<string, string> key => label
     */
    public function types(): array
    {
        return [
            ...array_map(fn (string $label): string => __($label), self::CORE_TYPES),
            ...(app(Blog::class)->isAvailable() ? array_map(fn (string $label): string => __($label), self::BLOG_TYPES) : []),
            ...$this->siteBuilder->registeredTemplateTypes(),
        ];
    }

    /**
     * The blocks a new template of this type starts with: the standard layout (for a blog post, its title, details,
     * image, text, share links and related posts; for the blog's archive, its title and posts), so it's changed rather
     * than built from nothing. Empty for the rest.
     *
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public static function starterBlocks(string $type): array
    {
        return match ($type) {
            // The post in a narrow Section: its title, details and image close together, then its text and share links.
            self::BLOG_POST => [
                ['type' => 'section', 'data' => [
                    'gap' => 'medium',
                    'appearance' => ['width' => 'narrow'],
                    'rows' => [
                        ['layout' => 'stacked', 'gap' => 'small', 'blocks' => [
                            ['type' => 'post_title', 'data' => ['level' => 'h1']],
                            ['type' => 'post_meta', 'data' => []],
                            ['type' => 'post_image', 'data' => []],
                        ]],
                        ['layout' => 'stacked', 'blocks' => [
                            ['type' => 'post_content', 'data' => []],
                            ['type' => 'post_share', 'data' => ['heading' => __('Share this post')]],
                        ]],
                    ],
                ]],
                ['type' => 'related_posts', 'data' => ['heading' => __('You might also like'), 'count' => 3]],
            ],
            self::BLOG_ARCHIVE => [
                ['type' => 'section', 'data' => [
                    'gap' => 'medium',
                    'rows' => [
                        ['layout' => 'stacked', 'blocks' => [
                            ['type' => 'archive_title', 'data' => ['level' => 'h1', 'intro' => __('Check out our latest news and updates.')]],
                        ]],
                        ['layout' => 'stacked', 'blocks' => [
                            ['type' => 'post_list', 'data' => ['per_page' => PostListBlock::DEFAULT_PER_PAGE]],
                        ]],
                    ],
                ]],
            ],
            default => [],
        };
    }

    /**
     * Whether templates of this type apply by blog category (category_ids) rather than by page.
     */
    public static function isBlogType(?string $type): bool
    {
        return $type !== null && isset(self::BLOG_TYPES[$type]);
    }

    /**
     * The template of this type for the page being shown ($page, or the one PageController set), or null. A blog type
     * goes by the category of the post or archive being shown (Blog::categoryId()); the blog's own archive (/blog) has
     * none, so only templates for every category, or every one except some, apply there.
     *
     * @return array{id: int, updated_at: int}|null
     */
    public function for(string $type, ?Page $page = null): ?array
    {
        $byCategory = self::isBlogType($type);
        $page ??= request()->attributes->get(self::PAGE_ATTRIBUTE);
        $id = $byCategory ? app(Blog::class)->categoryId() : ($page instanceof Page ? $page->getKey() : null);
        $ids = $byCategory ? 'category_ids' : 'page_ids';
        $candidates = array_filter($this->active(), fn (array $template): bool => $template['type'] === $type && match ($template['applies_to']) {
            Template::ONLY => $id !== null && in_array($id, $template[$ids] ?? [], true),
            Template::EXCEPT => $id === null || ! in_array($id, $template[$ids] ?? [], true),
            default => true,
        });

        usort($candidates, fn (array $a, array $b): int => [$b['applies_to'] === Template::ONLY, $b['updated_at']] <=> [$a['applies_to'] === Template::ONLY, $a['updated_at']]);

        return isset($candidates[0]) ? ['id' => $candidates[0]['id'], 'updated_at' => $candidates[0]['updated_at']] : null;
    }

    /**
     * The template of this type, rendered, for the page being shown; null when there's none (the theme's standard view
     * shows), when the header and footer are hidden (a template's own canvas), or when it fails: a broken template is
     * reported and never takes the page down.
     */
    public function render(string $type, ?Page $page = null): ?HtmlString
    {
        if (request()->attributes->get(self::HIDE_CHROME_ATTRIBUTE)) {
            return new HtmlString('');
        }

        $template = $this->for($type, $page);

        if ($template === null) {
            return null;
        }

        try {
            return $this->renderer->render($template['id'], $template['updated_at'], $page);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function hidesChrome(): bool
    {
        return (bool) request()->attributes->get(self::HIDE_CHROME_ATTRIBUTE);
    }

    /**
     * Every active template: id, type, where it applies and when it was saved. Cached until a template changes.
     *
     * @return array<int, array{id: int, type: string, applies_to: string, page_ids: array<int, int>, category_ids?: array<int, int>, updated_at: int}>
     */
    public function active(): array
    {
        try {
            $templates = $this->cachedActive();
        } catch (QueryException) {
            // Before the templates table is migrated (just after updating the package), the standard header and
            // footer show rather than every page failing.
            return [];
        }

        return is_array($templates) ? array_values(array_filter($templates, 'is_array')) : [];
    }

    private function cachedActive(): mixed
    {
        return VersionedCache::remember(cache()->store(), self::CACHE_KEY, self::VERSION_KEY, fn (): array => Template::query()
            ->where('is_active', true)
            ->get(['id', 'type', 'applies_to', 'page_ids', 'category_ids', 'updated_at'])
            ->map(fn (Template $template): array => [
                'id' => (int) $template->id,
                'type' => $template->type,
                'applies_to' => $template->applies_to,
                'page_ids' => self::ids($template->page_ids),
                'category_ids' => self::ids($template->category_ids),
                'updated_at' => (int) $template->updated_at?->getTimestamp(),
            ])
            ->all());
    }

    /**
     * @return array<int, int>
     */
    private static function ids(mixed $ids): array
    {
        return array_values(array_map('intval', array_filter((array) $ids, 'is_numeric')));
    }

    public static function flush(): void
    {
        VersionedCache::newVersion(cache()->store(), self::VERSION_KEY);
    }
}
