<?php

namespace ScBudgetWeb\SiteBuilder\Templates;

use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Blocks\PageRenderer;
use ScBudgetWeb\SiteBuilder\Blocks\RenderCache;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\Template;

/**
 * A template's HTML, cached, so a header or footer on every page costs a cache read: the template is only loaded and
 * rendered when it (or anything it shows, through RenderCache's shared version) changes. A block that depends on the
 * visitor (PageBlock::isCacheable() false, e.g. the account links) is a "hole": left out of the cached HTML and
 * rendered on each request, wherever it sits, even inside a Section.
 */
class TemplateRenderer
{
    public function __construct(private PageRenderer $renderer) {}

    public function render(int $templateId, int $updatedAt): ?HtmlString
    {
        $built = RenderCache::isEnabled()
            ? cache()->remember(RenderCache::templateKey($templateId, $updatedAt), now()->addDay(), fn (): ?array => $this->build($templateId))
            : $this->build($templateId);

        if (! is_array($built) || ! is_string($built['html'] ?? null)) {
            return null;
        }

        $page = request()->attributes->get(Templates::PAGE_ATTRIBUTE);

        return new HtmlString($this->renderer->fillHoles($built, $page instanceof Page ? $page : new Page));
    }

    /**
     * The template's HTML with markers for its holes, and the holes.
     *
     * @return array{html: string, holes: array<string, mixed>}|null
     */
    private function build(int $templateId): ?array
    {
        $template = Template::query()->find($templateId);

        if ($template === null) {
            return null;
        }

        $page = $template->previewPage();
        $holes = [];
        $html = implode('', $this->renderer->renderBlocks($page->blockList(), $page, holes: $holes));

        return ['html' => $html, 'holes' => $holes];
    }
}
