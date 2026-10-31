<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\PostShareBlock;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Blog\BlogPost;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\CreateTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\TemplateVisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Templates\Templates;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blog\Author;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blog\Category;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blog\Post;

/**
 * The Theme Builder's Blog post template: offered when the app has a blog, applied by the post's category, its blocks
 * showing the post being viewed (drawn on each request, the rest cached), and the latest post on its canvas.
 */
class BlogPostTemplateTest extends PageTestCase
{
    private Category $news;

    private Category $events;

    /** Each new post is a day newer. */
    private int $days = 0;

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('site-builder.blog.post_model', Post::class);
        $app['config']->set('site-builder.blog.category_model', Category::class);
        // SaaSykit's.
        $app['config']->set('app.date_format', 'd/m/Y');
    }

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->get('/blog/category/{slug}', fn () => 'Category')->name('blog.category');
        $router->get('/blog/{slug}', fn () => 'Post')->name('blog.view');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Post::createTables();
        $this->news = Category::query()->create(['name' => 'News', 'slug' => 'news']);
        $this->events = Category::query()->create(['name' => 'Events', 'slug' => 'events']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function blogPost(array $attributes = []): Post
    {
        return Post::query()->create([
            'author_id' => Author::query()->firstOrCreate(['name' => 'Sam Jones'], ['public_name' => 'Sam from the Village Hall'])->id,
            'blog_post_category_id' => $this->news->id,
            'title' => 'New kitchen opens',
            'slug' => 'new-kitchen-opens',
            'description' => 'Our hall has a new kitchen.',
            'body' => '<h2>All new</h2><p>Cookers & fridges.</p>',
            'image' => 'https://example.com/kitchen.jpg',
            'is_published' => true,
            'published_at' => Carbon::parse('2026-06-01')->addDays($this->days++),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function template(array $attributes = []): Template
    {
        return Template::query()->create([
            'name' => 'Posts',
            'type' => Templates::BLOG_POST,
            'blocks' => Templates::starterBlocks(Templates::BLOG_POST),
            ...$attributes,
        ]);
    }

    public function test_it_is_offered_when_the_app_has_a_blog(): void
    {
        $this->assertSame(['header' => 'Header', 'footer' => 'Footer', 'blog_post' => 'Blog post'], app(Templates::class)->types());

        config(['site-builder.blog.post_model' => 'App\\Models\\NoSuchModel']);
        $this->assertArrayNotHasKey('blog_post', app(Templates::class)->types(), 'Without a blog, no blog templates.');
    }

    public function test_the_post_shows_in_its_template(): void
    {
        $this->template();
        $post = $this->blogPost(['body' => '<h2>All new</h2><p>Cookers & {{ user_name }}</p><script>alert(1)</script>']);

        $html = (string) app(Blog::class)->renderPost($post->fresh());

        $this->assertMatchesRegularExpression('/<h1 class="sb-h1 section-heading">New kitchen opens<\/h1>/', $html);
        $this->assertMatchesRegularExpression('/<time datetime="2026-06-01" class="opacity-80">01\/06\/2026<\/time>.*Sam from the Village Hall.*<a href="http:\/\/localhost\/blog\/category\/news"[^>]*>News<\/a>/s', $html);
        $this->assertStringContainsString('<img src="https://example.com/kitchen.jpg" alt="New kitchen opens"', $html);
        $this->assertStringContainsString('<h2>All new</h2><p>Cookers &amp; &#123;&#123; user_name &#125;&#125;</p>', $html, 'Its braces stay as text.');
        $this->assertStringNotContainsString('<script>', $html, 'Sanitised, as rich text always is.');
        $this->assertStringContainsString('https://x.com/intent/post?text=New%20kitchen%20opens&amp;url=http%3A%2F%2Flocalhost%2Fblog%2Fnew-kitchen-opens', $html);
        $this->assertStringContainsString('mailto:?subject=New%20kitchen%20opens', $html);
        $this->assertStringContainsString('Share this post', $html);
        $this->assertStringNotContainsString('You might also like', $html, 'No other posts: Related posts hides.');
    }

    public function test_dates_follow_the_sites_format_and_drafts_say_so(): void
    {
        config(['app.date_format' => 'j F Y']);
        $post = BlogPost::fromModel($this->blogPost());
        $draft = BlogPost::fromModel($this->blogPost(['is_published' => false]));

        $this->assertSame('1 June 2026', $post->date());
        $this->assertSame('Draft', $draft->date());
        $this->assertSame('Sam from the Village Hall', $post->author);
        $this->assertSame('http://localhost/blog/category/news', $post->categoryUrl);
    }

    public function test_related_posts_come_from_the_same_category_first(): void
    {
        $this->template();
        $older = $this->blogPost(['title' => 'Older news', 'slug' => 'older-news']);
        $event = $this->blogPost(['title' => 'Summer fair', 'slug' => 'summer-fair', 'blog_post_category_id' => $this->events->id]);
        $this->blogPost(['title' => 'Not out yet', 'slug' => 'not-out-yet', 'is_published' => false]);
        $post = $this->blogPost();

        $related = app(Blog::class)->related(BlogPost::fromModel($post), 2);
        $this->assertSame(['Older news', 'Summer fair'], array_map(fn (BlogPost $related): string => $related->title, $related), 'Its category first, then the latest others; never itself or a draft.');
        $this->assertSame(['Summer fair', 'Older news'], array_map(fn (BlogPost $related): string => $related->title, app(Blog::class)->related(BlogPost::fromModel($post), 2, sameCategoryFirst: false)));

        $html = (string) app(Blog::class)->renderPost($post);
        $this->assertStringContainsString('You might also like', $html);
        $this->assertMatchesRegularExpression('/<h3 class="sb-item-title[^"]*">\s*<a href="http:\/\/localhost\/blog\/older-news"[^>]*>Older news<\/a>/', $html);
        $this->assertStringContainsString('<span>Events</span>', $html);
        $this->assertSame(1, substr_count($html, 'Summer fair'), 'Three asked for, two to show.');
        $this->assertStringNotContainsString('Not out yet', $html);
        $this->assertNotNull($older);
        $this->assertNotNull($event);
    }

    public function test_a_template_applies_by_the_posts_category(): void
    {
        $blog = app(Blog::class);
        $news = $this->blogPost();
        $event = $this->blogPost(['slug' => 'fair', 'blog_post_category_id' => $this->events->id]);
        $uncategorised = $this->blogPost(['slug' => 'other', 'blog_post_category_id' => null]);

        $this->assertNull($blog->renderPost($news), 'No template: the standard post view.');

        $this->template(['name' => 'Every post', 'blocks' => [self::block('text', ['content' => '<p>Every post</p>'])]]);
        Carbon::setTestNow(now()->addMinute());
        $this->template(['name' => 'Not events', 'applies_to' => Template::EXCEPT, 'category_ids' => [$this->events->id], 'blocks' => [self::block('text', ['content' => '<p>Not events</p>'])]]);
        Carbon::setTestNow(now()->subMinutes(10));
        $this->template(['name' => 'Events', 'applies_to' => Template::ONLY, 'category_ids' => [$this->events->id], 'blocks' => [self::block('text', ['content' => '<p>Events only</p>'])]]);
        Carbon::setTestNow();

        $this->assertStringContainsString('Events only', (string) $blog->renderPost($event), 'One for chosen categories wins, however old.');
        $this->assertStringContainsString('Not events', (string) $blog->renderPost($news), 'Then the most recently saved.');
        $this->assertStringContainsString('Not events', (string) $blog->renderPost($uncategorised));
        $this->assertNull(app(Templates::class)->for('footer'), 'Headers and footers still go by page.');
    }

    public function test_post_blocks_are_drawn_for_each_post_and_the_rest_is_cached(): void
    {
        $this->template(['blocks' => [
            self::block('heading', ['heading' => 'Read about {{ post_title }} by {{ post_author }}']),
            self::block('text', ['content' => '<p>Cached part</p>']),
            self::block('post_title'),
        ]]);

        $first = (string) app(Blog::class)->renderPost($this->blogPost(['title' => 'First post', 'slug' => 'first']));
        $second = (string) app(Blog::class)->renderPost($this->blogPost(['title' => 'Second post', 'slug' => 'second']));

        $this->assertStringContainsString('Read about First post by Sam from the Village Hall', $first);
        $this->assertStringContainsString('Read about Second post', $second);
        $this->assertStringContainsString('<h1 class="sb-h1 section-heading">Second post</h1>', $second);
        $this->assertStringNotContainsString('First post', $second);
        $this->assertStringContainsString('Cached part', $second);
    }

    public function test_a_post_without_an_image_or_text_leaves_those_blocks_out(): void
    {
        $this->template();

        $html = (string) app(Blog::class)->renderPost($this->blogPost(['image' => null, 'body' => '', 'author_id' => null, 'blog_post_category_id' => null]));

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('sb-prose', $html);
        $this->assertMatchesRegularExpression('/<p class="block-text flex[^"]*">\s*<time[^>]*>01\/06\/2026<\/time>\s*<\/p>/', $html, 'Just the date.');
    }

    public function test_a_broken_template_falls_back_to_the_standard_post(): void
    {
        $this->template(['blocks' => [self::block('section', ['rows' => 'not rows'])]]);
        config(['app.debug' => false]);

        $this->assertNotNull(app(Blog::class)->renderPost($this->blogPost()), 'Templates::render() reports it and never throws.');
    }

    public function test_share_links(): void
    {
        $post = new BlogPost(id: 1, title: 'Fair & fun', url: 'https://example.com/blog/fair');

        $this->assertSame([
            'linkedin' => ['url' => 'https://www.linkedin.com/sharing/share-offsite/?url=https%3A%2F%2Fexample.com%2Fblog%2Ffair', 'label' => 'Share on LinkedIn'],
            'email' => ['url' => 'mailto:?subject=Fair%20%26%20fun&body=https%3A%2F%2Fexample.com%2Fblog%2Ffair', 'label' => 'Share by email'],
        ], PostShareBlock::links($post, ['email', 'linkedin', 'myspace']));
    }

    public function test_post_blocks_and_tags_belong_to_blog_post_templates(): void
    {
        $registry = app(BlockRegistry::class);
        $postBlocks = ['post_title', 'post_meta', 'post_image', 'post_content', 'post_share', 'related_posts'];

        $this->assertSame([], array_intersect($postBlocks, array_keys($registry->offered())), 'Not on pages.');
        $this->assertSame($postBlocks, array_values(array_intersect(array_keys($registry->editingTemplate(Templates::BLOG_POST)->offered()), $postBlocks)));
        $this->assertSame([], array_intersect($postBlocks, array_keys($registry->editingTemplate('header')->offered())));

        // On a page, a post's tag is blank (or its fallback).
        $this->page(['blocks' => [self::block('heading', ['heading' => 'Hello {{ post_title | there }}'])]]);
        $this->assertStringContainsString('Hello there', $this->get('/about-us')->getContent());
    }

    public function test_admins_make_one_for_chosen_categories_starting_from_the_standard_layout(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());

        Livewire::test(CreateTemplate::class)
            ->fillForm(['name' => 'Event posts', 'type' => Templates::BLOG_POST])
            ->assertFormFieldHidden('category_ids')
            ->fillForm(['applies_to' => Template::ONLY])
            ->assertFormFieldVisible('category_ids')
            ->assertFormFieldHidden('page_ids')
            ->call('create')
            ->assertHasFormErrors(['category_ids' => 'required'])
            ->fillForm(['category_ids' => [$this->events->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = Template::query()->sole();
        $this->assertSame([$this->events->id], $template->category_ids);
        $this->assertSame(['section', 'related_posts'], array_column($template->blocks, 'type'));
        $this->assertSame(['post_title', 'post_meta', 'post_image', 'post_content', 'post_share'], array_column(array_merge(...array_map(fn (array $row): array => array_merge(...array_column($row['columns'], 'blocks')), $template->blocks[0]['data']['rows'])), 'type'), 'The post in a Section, related posts under it.');
    }

    public function test_its_canvas_shows_the_latest_post_or_a_sample(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $template = $this->template();

        $editor = Livewire::test(TemplateVisualEditor::class, ['record' => $template->getRouteKey()]);
        $this->assertStringContainsString('title shows here', $editor->instance()->canvasHtml(), 'No posts yet: a sample.');

        $this->blogPost(['title' => 'Latest news', 'slug' => 'latest']);
        app()->forgetScopedInstances();
        $html = $editor->instance()->canvasHtml();
        $this->assertStringContainsString('Latest news', $html);
        $this->assertStringContainsString('data-sb-node="block"', $html);
    }
}
