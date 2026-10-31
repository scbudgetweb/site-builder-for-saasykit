<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Carbon;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Templates\Templates;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blog\Author;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blog\Category;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blog\Post;

/**
 * An app with a blog (the fixture models standing in for SaaSykit's), two categories and SaaSykit's blog routes, for
 * the Blog post and Blog archive templates.
 */
abstract class BlogTestCase extends PageTestCase
{
    protected Category $news;

    protected Category $events;

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
    protected function blogPost(array $attributes = []): Post
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
     * A template of $type, starting from its standard layout.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function blogTemplate(string $type, array $attributes = []): Template
    {
        return Template::query()->create([
            'name' => 'Blog',
            'type' => $type,
            'blocks' => Templates::starterBlocks($type),
            ...$attributes,
        ]);
    }
}
