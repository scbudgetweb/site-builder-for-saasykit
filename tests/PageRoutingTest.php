<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\URL;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;

class PageRoutingTest extends PageTestCase
{
    public function test_a_published_page_is_served_at_its_slug(): void
    {
        $this->page(['seo_title' => 'About our hall', 'seo_description' => 'Who we are.']);

        $this->get('/about-us')
            ->assertOk()
            ->assertSee('<title>About our hall</title>', false)
            ->assertSee('<meta name="description" content="Who we are.">', false)
            ->assertSee('<link rel="canonical" href="http://localhost/about-us">', false)
            ->assertSee('About us')
            ->assertDontSee('noindex');
    }

    public function test_drafts_unknown_slugs_and_nested_paths_are_not_found(): void
    {
        $this->page(['status' => PageStatus::Draft]);

        $this->get('/about-us')->assertNotFound();
        $this->get('/nothing-here')->assertNotFound();
        $this->get('/about-us/team')->assertNotFound();
    }

    public function test_a_page_never_hides_one_of_the_apps_routes(): void
    {
        $this->page(['slug' => 'blog']);

        $this->get('/blog')->assertOk()->assertSee('Blog')->assertDontSee('About us');
    }

    public function test_a_wrongly_cased_slug_redirects_to_the_page(): void
    {
        $this->page();

        $this->get('/About-Us')->assertRedirect('http://localhost/about-us')->assertStatus(301);
    }

    public function test_redirects_go_to_a_path_on_this_site_whatever_host_the_request_names(): void
    {
        $this->page();

        $this->get('/About-Us', ['Host' => 'evil.example'])->assertStatus(301)->assertHeader('Location', '/about-us');
    }

    public function test_a_page_replacing_a_built_in_page_redirects_there(): void
    {
        $page = $this->page(['replaces' => 'home']);

        $this->assertSame('http://localhost', $page->url());
        $this->get('/about-us')->assertRedirect('http://localhost')->assertStatus(301)->assertHeader('Location', '/');
        $this->assertSame($page->id, app(PageService::class)->forSlot('home')?->id);
    }

    public function test_a_page_whose_slot_is_no_longer_registered_is_served_at_its_slug(): void
    {
        $page = $this->page(['replaces' => 'old-slot']);

        $this->assertSame('http://localhost/about-us', $page->url());
        $this->get('/about-us')->assertOk();
    }

    public function test_noindex_pages_ask_search_engines_not_to_list_them(): void
    {
        $this->page(['noindex' => true]);

        $this->get('/about-us')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_the_signed_preview_shows_a_draft_and_an_unsigned_link_is_refused(): void
    {
        $page = $this->page(['status' => PageStatus::Draft]);

        $this->get($page->previewUrl())
            ->assertOk()
            ->assertSee('Preview of a draft page')
            ->assertSee('noindex, nofollow', false)
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->get(route('site-builder.pages.preview', ['page' => $page->id]))->assertForbidden();
        $this->get(URL::temporarySignedRoute('site-builder.pages.preview', now()->subMinute(), ['page' => $page->id]))->assertForbidden();
    }

    public function test_a_page_in_the_bin_is_neither_served_nor_previewed(): void
    {
        $page = $this->page();
        $previewUrl = $page->previewUrl();
        $page->delete();

        $this->get('/about-us')->assertNotFound();
        $this->get($previewUrl)->assertNotFound();
    }

    public function test_lookups_follow_saves_deletes_and_restores(): void
    {
        $pages = app(PageService::class);
        $page = $this->page(['status' => PageStatus::Draft]);

        $this->assertNull($pages->findPublishedBySlug('about-us'));

        $page->update(['status' => PageStatus::Published]);
        $this->assertSame($page->id, $pages->findPublishedBySlug('about-us')?->id);
        $this->assertNotNull($page->fresh()->published_at);

        $page->delete();
        $this->assertNull($pages->findPublishedBySlug('about-us'));

        $page->restore();
        $this->assertSame($page->id, $pages->findPublishedBySlug('about-us')?->id);
        $this->assertSame([$page->id => ['title' => 'About us', 'url' => 'http://localhost/about-us']], $pages->publishedPageLinks());
    }

    public function test_binning_a_page_frees_its_slot(): void
    {
        $page = $this->page(['replaces' => 'home']);
        $page->delete();

        $this->assertNull($page->fresh()->replaces);
        $this->assertNull(app(PageService::class)->forSlot('home'));
        $this->page(['slug' => 'new-home', 'replaces' => 'home']);
    }

    public function test_a_corrupt_lookup_cache_reads_as_no_pages(): void
    {
        $this->page();
        cache()->forever('site-builder.published-pages.'.(cache()->get('site-builder.pages-version') ?? '0'), 'not an array');

        $this->get('/about-us')->assertNotFound();
        $this->assertNull(app(PageService::class)->forSlot('home'));
    }

    public function test_reserved_slugs_come_from_the_router(): void
    {
        $reserved = app(PageService::class)->reservedSlugs();

        $this->assertContains('blog', $reserved);
        $this->assertContains('site-builder', $reserved);
        $this->assertNotContains('', $reserved);
    }
}
