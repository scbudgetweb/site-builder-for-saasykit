<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\CreateTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\TemplateVisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Schemas\TemplateForm;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * The Theme Builder's Blog archive template (the blog's page, and each category's): its title and its posts a page at a
 * time, applied by category; and the Latest posts block, for any page.
 */
class BlogArchiveTemplateTest extends BlogTestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function template(array $attributes = []): Template
    {
        return $this->blogTemplate(Templates::BLOG_ARCHIVE, ['name' => 'Archive', ...$attributes]);
    }

    /**
     * The archive's template with just a post list.
     *
     * @param  array<string, mixed>  $data
     */
    private function postList(array $data = []): Template
    {
        return $this->template(['blocks' => [self::block('post_list', $data)]]);
    }

    public function test_the_blog_shows_in_its_template(): void
    {
        $this->template();
        $this->blogPost(['title' => 'Older news', 'slug' => 'older-news']);
        $this->blogPost(['title' => 'Summer fair', 'slug' => 'summer-fair', 'blog_post_category_id' => $this->events->id]);
        $this->blogPost(['title' => 'Not out yet', 'slug' => 'not-out-yet', 'is_published' => false]);

        $html = (string) app(Blog::class)->renderArchive();

        $this->assertMatchesRegularExpression('/<div class="text-center">\s*<h1 class="sb-h1 section-heading">Blog<\/h1>\s*<p[^>]*>Check out our latest news and updates.<\/p>/', $html);
        $this->assertMatchesRegularExpression('/Summer fair.*Older news/s', $html, 'Newest first, from every category.');
        $this->assertStringContainsString('<a href="http://localhost/blog/summer-fair"', $html);
        $this->assertStringNotContainsString('Not out yet', $html, 'Never a draft.');
        $this->assertStringNotContainsString('sb-pagination', $html, 'One page: no links to others.');
    }

    public function test_a_categorys_archive_shows_its_name_and_only_its_posts(): void
    {
        $this->template(['blocks' => [
            self::block('archive_title', ['blog_title' => 'From our blog', 'intro' => 'The latest from {{ archive_title }}']),
            self::block('post_list'),
        ]]);
        $this->blogPost(['title' => 'Older news', 'slug' => 'older-news']);
        $this->blogPost(['title' => 'Summer fair', 'slug' => 'summer-fair', 'blog_post_category_id' => $this->events->id]);

        $html = (string) app(Blog::class)->renderArchive($this->events);
        $this->assertStringContainsString('<h1 class="sb-h1 section-heading">Events</h1>', $html, 'The category\'s name, whatever the blog\'s title.');
        $this->assertStringContainsString('The latest from Events', $html);
        $this->assertStringContainsString('Summer fair', $html);
        $this->assertStringNotContainsString('Older news', $html);

        $html = (string) app(Blog::class)->renderArchive();
        $this->assertStringContainsString('<h1 class="sb-h1 section-heading">From our blog</h1>', $html);
        $this->assertStringContainsString('The latest from Blog', $html);
        $this->assertStringContainsString('Older news', $html);
    }

    public function test_posts_show_a_page_at_a_time(): void
    {
        $this->postList(['per_page' => 2]);
        foreach (['First', 'Second', 'Third'] as $title) {
            $this->blogPost(['title' => "{$title} post", 'slug' => strtolower($title)]);
        }

        $html = (string) app(Blog::class)->renderArchive();
        $this->assertMatchesRegularExpression('/Third post.*Second post/s', $html);
        $this->assertStringNotContainsString('First post', $html);
        $this->assertStringContainsString('aria-label="Pages of posts"', $html);
        $this->assertMatchesRegularExpression('/<span class="site-button site-button-1[^"]*" aria-current="page">1<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<a href="http:\/\/localhost\?page=2" rel="next"[^>]*>Next<\/a>/', $html);

        request()->merge(['page' => 2]);
        $html = (string) app(Blog::class)->renderArchive();
        $this->assertStringContainsString('First post', $html);
        $this->assertStringNotContainsString('Third post', $html);

        request()->merge(['page' => 9]);
        $this->assertStringNotContainsString('sb-items', (string) app(Blog::class)->renderArchive(), 'Past the last page: nothing, as When empty says.');
    }

    public function test_without_page_links_only_the_latest_posts_show(): void
    {
        $this->postList(['per_page' => 2, 'paginate' => false]);
        foreach (['First', 'Second', 'Third'] as $title) {
            $this->blogPost(['title' => "{$title} post", 'slug' => strtolower($title)]);
        }
        request()->merge(['page' => 2]);

        $html = (string) app(Blog::class)->renderArchive();

        $this->assertMatchesRegularExpression('/Third post.*Second post/s', $html);
        $this->assertStringNotContainsString('sb-pagination', $html);
    }

    public function test_a_template_applies_by_category(): void
    {
        $blog = app(Blog::class);

        $this->assertNull($blog->renderArchive(), 'No template: the standard archive view.');

        $this->template(['name' => 'Events', 'applies_to' => Template::ONLY, 'category_ids' => [$this->events->id], 'blocks' => [self::block('text', ['content' => '<p>Events only</p>'])]]);
        $this->assertNull($blog->renderArchive(), 'The blog\'s own page has no category, so one for chosen categories never applies there.');
        $this->assertNull($blog->renderArchive($this->news));
        $this->assertStringContainsString('Events only', (string) $blog->renderArchive($this->events));

        Carbon::setTestNow(now()->addMinute());
        $this->template(['name' => 'Not events', 'applies_to' => Template::EXCEPT, 'category_ids' => [$this->events->id], 'blocks' => [self::block('text', ['content' => '<p>Not events</p>'])]]);
        Carbon::setTestNow();

        $this->assertStringContainsString('Not events', (string) $blog->renderArchive());
        $this->assertStringContainsString('Not events', (string) $blog->renderArchive($this->news));
        $this->assertStringContainsString('Events only', (string) $blog->renderArchive($this->events));
    }

    public function test_archive_blocks_and_tags_belong_to_blog_archive_templates(): void
    {
        $registry = app(BlockRegistry::class);
        $archiveBlocks = ['archive_title', 'post_list'];

        $this->assertSame([], array_intersect($archiveBlocks, array_keys($registry->offered())), 'Not on pages.');
        $this->assertSame([], array_intersect($archiveBlocks, array_keys($registry->editingTemplate(Templates::BLOG_POST)->offered())));
        $this->assertSame($archiveBlocks, array_values(array_intersect(array_keys($registry->editingTemplate(Templates::BLOG_ARCHIVE)->offered()), $archiveBlocks)));

        // A post's page isn't an archive: no archive title.
        $this->blogTemplate(Templates::BLOG_POST, ['blocks' => [self::block('archive_title'), self::block('heading', ['heading' => 'In {{ archive_title | the blog }}'])]]);
        $html = (string) app(Blog::class)->renderPost($this->blogPost());
        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringContainsString('In the blog', $html);
    }

    public function test_latest_posts_show_on_any_page(): void
    {
        $this->blogPost(['title' => 'Older news', 'slug' => 'older-news']);
        $this->blogPost(['title' => 'Summer fair', 'slug' => 'summer-fair', 'blog_post_category_id' => $this->events->id]);
        $this->blogPost(['title' => 'Latest news', 'slug' => 'latest-news']);
        $this->page(['blocks' => [
            self::block('latest_posts', ['heading' => 'From the blog', 'count' => 2]),
            self::block('latest_posts', ['heading' => 'News', 'count' => 3, 'category_id' => $this->news->id, 'show' => ['date']]),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/From the blog.*Latest news.*Summer fair.*News.*Latest news.*Older news/s', $html);
        $this->assertSame(1, substr_count($html, 'Summer fair'), 'Only the first is from every category.');
        $this->assertSame(1, substr_count($html, 'Older news'), 'Two asked for in the first.');
        $this->assertSame(1, substr_count($html, '<span>Events</span>'), 'The second shows only the date.');
    }

    public function test_latest_posts_is_offered_only_when_the_app_has_a_blog(): void
    {
        $this->assertArrayHasKey('latest_posts', app(BlockRegistry::class)->offered());
        $this->assertArrayHasKey('latest_posts', app(BlockRegistry::class)->editingTemplate('footer')->offered());

        config(['site-builder.blog.post_model' => 'App\\Models\\NoSuchModel']);
        $this->assertArrayNotHasKey('latest_posts', app(BlockRegistry::class)->offered());
        $this->assertArrayHasKey('latest_posts', app(BlockRegistry::class)->all(), 'One already on a page still draws (as When empty says).');
    }

    public function test_admins_make_one_for_chosen_categories_starting_from_the_standard_layout(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());

        $this->assertSame(['everywhere' => 'The blog and every category', 'only' => 'The categories chosen', 'except' => 'The blog and every category except those chosen'], TemplateForm::appliesToOptions(Templates::BLOG_ARCHIVE));

        Livewire::test(CreateTemplate::class)
            ->fillForm(['name' => 'Event archive', 'type' => Templates::BLOG_ARCHIVE, 'applies_to' => Template::ONLY])
            ->assertFormFieldVisible('category_ids')
            ->assertFormFieldHidden('page_ids')
            ->fillForm(['category_ids' => [$this->events->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = Template::query()->sole();
        $this->assertSame([$this->events->id], $template->category_ids);
        $this->assertSame(['section'], array_column($template->blocks, 'type'));
        $this->assertSame(['archive_title', 'post_list'], array_column(array_merge(...array_map(fn (array $row): array => array_merge(...array_column($row['columns'], 'blocks')), $template->blocks[0]['data']['rows'])), 'type'), 'Its title, then its posts.');
    }

    public function test_its_canvas_shows_the_whole_blog(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $template = $this->template();
        $this->blogPost(['title' => 'Summer fair', 'slug' => 'summer-fair', 'blog_post_category_id' => $this->events->id]);

        $html = Livewire::test(TemplateVisualEditor::class, ['record' => $template->getRouteKey()])->instance()->canvasHtml();

        $this->assertStringContainsString('<h1 class="sb-h1 section-heading">Blog</h1>', $html);
        $this->assertStringContainsString('Summer fair', $html);
        $this->assertStringContainsString('data-sb-node="block"', $html);
    }
}
