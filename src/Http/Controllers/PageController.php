<?php

namespace ScBudgetWeb\SiteBuilder\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\PageDraft;
use ScBudgetWeb\SiteBuilder\Models\PageRevision;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * Serves published pages at /{slug} (from the fallback route, so a page can never hide one of the app's routes) and
 * their signed admin previews.
 */
class PageController extends Controller
{
    public function show(PageService $pages, string $fallbackPlaceholder): View|RedirectResponse
    {
        abort_unless(preg_match('/^[a-z0-9-]+$/i', $fallbackPlaceholder) === 1, 404);

        $page = $pages->findPublishedBySlug($fallbackPlaceholder);

        abort_if($page === null, 404);

        // A page that replaces a built-in page lives at that page's URL only; a wrongly cased slug is corrected. The
        // redirect is to a path on this site, never an absolute URL built from the request's Host header.
        if ($page->slot() !== null || $page->slug !== $fallbackPlaceholder) {
            return new RedirectResponse(self::pathOf($page->url()), 301);
        }

        return view('site-builder::pages.show', ['page' => $page]);
    }

    /**
     * Any status, through a temporary signed link from the admin Preview buttons. Pages in the bin aren't previewed.
     */
    public function preview(Page $page): Response
    {
        return self::private(view('site-builder::pages.show', ['page' => $page, 'isPreview' => true]));
    }

    /**
     * The page as it was in a revision (Pages › Revisions › Preview), through a temporary signed link. Nothing is saved.
     */
    public function previewRevision(Page $page, PageRevision $revision): Response
    {
        $page->forceFill($revision->attributesToRestore());

        return self::private(view('site-builder::pages.show', ['page' => $page, 'isPreview' => true, 'revision' => $revision]));
    }

    /**
     * The visual builder's canvas, in an iframe on the editor: the page with this person's draft (or as it's saved, until
     * they change something), each block, row and column marked with its uid. Through a temporary signed link; nothing
     * is saved.
     */
    public function canvas(Page $page, int $user): Response
    {
        $draft = PageDraft::query()->where('page_id', $page->getKey())->where('user_id', $user)->first();

        if ($draft !== null) {
            $page->forceFill(['blocks' => $draft->blocks]);
        }

        return self::private(view('site-builder::pages.show', ['page' => $page, 'isPreview' => true, 'isCanvas' => true]));
    }

    /**
     * A block library item's canvas: its block on its own, in the theme, with this person's draft if they have one.
     */
    public function libraryCanvas(SavedBlock $savedBlock, int $user): Response
    {
        $draft = PageDraft::query()->where('saved_block_id', $savedBlock->getKey())->where('user_id', $user)->first();

        return self::private(view('site-builder::pages.show', ['page' => $savedBlock->previewPage($draft?->blocks), 'isPreview' => true, 'isCanvas' => true]));
    }

    /**
     * A Theme Builder template's canvas: its blocks as the page's content, with the site's header and footer hidden, so
     * a header template is edited on its own.
     */
    public function templateCanvas(Template $template, int $user): Response
    {
        $draft = PageDraft::query()->where('template_id', $template->getKey())->where('user_id', $user)->first();
        request()->attributes->set(Templates::HIDE_CHROME_ATTRIBUTE, true);

        return self::private(view('site-builder::pages.show', ['page' => $template->previewPage($draft?->blocks), 'isPreview' => true, 'isCanvas' => true]));
    }

    /**
     * A preview shows unpublished content to whoever holds the link, so no shared cache may keep a copy.
     */
    private static function private(View $view): Response
    {
        return response($view)->header('Cache-Control', 'private, no-store');
    }

    /**
     * The path (and query) of a URL on this site, e.g. "/about-us".
     */
    private static function pathOf(string $url): string
    {
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? "{$path}?{$query}" : $path;
    }
}
