<?php

namespace ScBudgetWeb\SiteBuilder\Templates;

use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\Support\VersionedCache;
use Throwable;

/**
 * The Theme Builder: which template (Website › Theme builder) a type such as the header uses on the page being shown.
 * The most specific active one wins: one for "only" this page, then one for everywhere (or everywhere "except" pages
 * that don't include this one), the most recently saved first. With none, the theme's standard view shows.
 *
 * The list of active templates is cached (a few columns each, never their blocks), so finding one costs a cache read;
 * TemplateRenderer caches each template's HTML.
 */
class Templates
{
    public const CORE_TYPES = ['header' => 'Header', 'footer' => 'Footer'];

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
            ...$this->siteBuilder->registeredTemplateTypes(),
        ];
    }

    /**
     * The template of this type for the page being shown ($page, or the one PageController set), or null.
     *
     * @return array{id: int, updated_at: int}|null
     */
    public function for(string $type, ?Page $page = null): ?array
    {
        $page ??= request()->attributes->get(self::PAGE_ATTRIBUTE);
        $pageId = $page instanceof Page ? $page->getKey() : null;
        $candidates = array_filter($this->active(), fn (array $template): bool => $template['type'] === $type && match ($template['applies_to']) {
            Template::ONLY => $pageId !== null && in_array($pageId, $template['page_ids'], true),
            Template::EXCEPT => $pageId === null || ! in_array($pageId, $template['page_ids'], true),
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
            return $this->renderer->render($template['id'], $template['updated_at']);
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
     * @return array<int, array{id: int, type: string, applies_to: string, page_ids: array<int, int>, updated_at: int}>
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
            ->get(['id', 'type', 'applies_to', 'page_ids', 'updated_at'])
            ->map(fn (Template $template): array => [
                'id' => (int) $template->id,
                'type' => $template->type,
                'applies_to' => $template->applies_to,
                'page_ids' => array_values(array_map('intval', array_filter((array) $template->page_ids, 'is_numeric'))),
                'updated_at' => (int) $template->updated_at?->getTimestamp(),
            ])
            ->all());
    }

    public static function flush(): void
    {
        VersionedCache::newVersion(cache()->store(), self::VERSION_KEY);
    }
}
