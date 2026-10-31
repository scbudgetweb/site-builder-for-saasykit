<?php

namespace ScBudgetWeb\SiteBuilder\Blog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * The app's blog, for the Theme Builder's Blog post and Blog archive templates: what's being shown on this request (a
 * post or an archive, set by the theme's blog views, or a sample on a template's canvas), and the queries the blog
 * blocks run. Bound per request (scoped).
 *
 * The models are SaaSykit's (config site-builder.blog), read into BlogPost so the blocks don't depend on them.
 */
class Blog
{
    private ?BlogPost $post = null;

    /** @var array{categoryId: int|null, category: string|null}|null The archive being shown: the blog's, or a category's. */
    private ?array $archive = null;

    /**
     * Whether the app has a blog: its post model and the blog.view route.
     */
    public function isAvailable(): bool
    {
        return $this->postModel() !== null && Route::has('blog.view');
    }

    /**
     * Shows $post with its template (the Blog post type, for its category), or null for the theme's standard view (no
     * template applies, or it failed).
     */
    public function renderPost(Model $post): ?HtmlString
    {
        $this->showPost(BlogPost::fromModel($post));

        return app(Templates::class)->render(Templates::BLOG_POST, $this->page());
    }

    public function showPost(?BlogPost $post): static
    {
        $this->post = $post;
        $this->archive = null;

        return $this;
    }

    /**
     * Shows the blog's archive (/blog), or $category's, with its template (the Blog archive type, for that category),
     * or null for the theme's standard view.
     */
    public function renderArchive(?Model $category = null): ?HtmlString
    {
        $this->showArchive($category?->getKey() === null ? null : (int) $category->getKey(), $category === null ? null : (string) $category->getAttribute('name'));

        return app(Templates::class)->render(Templates::BLOG_ARCHIVE, $this->page());
    }

    public function showArchive(?int $categoryId = null, ?string $category = null): static
    {
        $this->post = null;
        $this->archive = ['categoryId' => $categoryId, 'category' => $category];

        return $this;
    }

    public function isArchive(): bool
    {
        return $this->archive !== null;
    }

    /**
     * The archive's title: the category's name, or "Blog" for the whole blog. Null when it isn't an archive.
     */
    public function archiveTitle(): ?string
    {
        return $this->archive === null ? null : ($this->archive['category'] ?? __('Blog'));
    }

    /**
     * The post being shown, or null (not a post's page).
     */
    public function post(): ?BlogPost
    {
        return $this->post;
    }

    /**
     * The category of what's being shown, for which template applies (Templates::for()).
     */
    public function categoryId(): ?int
    {
        return $this->post?->categoryId ?? $this->archive['categoryId'] ?? null;
    }

    /**
     * A template's canvas shows the latest published post, or a sample when there isn't one yet; an archive's shows the
     * whole blog's.
     */
    public function useSampleFor(string $templateType): static
    {
        return match ($templateType) {
            Templates::BLOG_POST => $this->showPost($this->latest(1)[0] ?? self::samplePost()),
            Templates::BLOG_ARCHIVE => $this->showArchive(),
            default => $this,
        };
    }

    public static function samplePost(): BlogPost
    {
        return new BlogPost(
            id: null,
            title: __('Your post\'s title shows here'),
            url: '#',
            excerpt: __('Each post\'s description shows here.'),
            body: '<p>'.e(__('Each post\'s text shows here, as written in Blog › Posts. This sample shows until the first post is published.')).'</p>',
            publishedAt: now(),
            author: (string) (auth()->user()?->name ?? config('app.name')),
            category: __('Category'),
            categoryUrl: '#',
        );
    }

    /**
     * An unsaved page for what's being shown, so {{ page_title }} and the like have a value.
     */
    public function page(): Page
    {
        return (new Page)->forceFill(['title' => $this->post?->title ?? $this->archiveTitle() ?? '', 'slug' => 'blog']);
    }

    /**
     * The latest published posts, newest first, optionally from one category and without one post.
     *
     * @return array<int, BlogPost>
     */
    public function latest(int $count, ?int $categoryId = null, ?int $except = null): array
    {
        $query = $this->published();

        if ($query === null || $count < 1) {
            return [];
        }

        return $query
            ->when($categoryId !== null, fn (Builder $query) => $query->where('blog_post_category_id', $categoryId))
            ->when($except !== null, fn (Builder $query) => $query->whereKeyNot($except))
            ->limit($count)
            ->get()
            ->map(fn (Model $post): BlogPost => BlogPost::fromModel($post))
            ->all();
    }

    /**
     * The archive's published posts, newest first, a page at a time ($page, or the request's ?page=). Empty when the app
     * has no blog.
     *
     * @return LengthAwarePaginator<int, BlogPost>
     */
    public function archivePosts(int $perPage, ?int $page = null): LengthAwarePaginator
    {
        $categoryId = $this->archive['categoryId'] ?? null;
        $query = $this->published();

        if ($query === null) {
            return new LengthAwarePaginator([], 0, max(1, $perPage));
        }

        $posts = $query
            ->when($categoryId !== null, fn (Builder $query) => $query->where('blog_post_category_id', $categoryId))
            ->paginate(max(1, $perPage), page: $page);

        return $posts->through(fn (Model $post): BlogPost => BlogPost::fromModel($post));
    }

    /**
     * Posts to read after $post: from its category first (when $sameCategoryFirst), then the latest others.
     *
     * @return array<int, BlogPost>
     */
    public function related(BlogPost $post, int $count, bool $sameCategoryFirst = true): array
    {
        $posts = $sameCategoryFirst && $post->categoryId !== null ? $this->latest($count, $post->categoryId, $post->id) : [];

        if (count($posts) < $count) {
            $shown = array_filter([$post->id, ...array_map(fn (BlogPost $related): ?int => $related->id, $posts)]);

            foreach ($this->latest($count + count($shown)) as $other) {
                if (count($posts) < $count && ! in_array($other->id, $shown, true)) {
                    $posts[] = $other;
                }
            }
        }

        return $posts;
    }

    /**
     * The categories, by name, for choosing where a template applies.
     *
     * @return array<int, string> id => name
     */
    public function categories(): array
    {
        $model = $this->categoryModel();

        return $model === null ? [] : $model::query()->orderBy('name')->pluck('name', (new $model)->getKeyName())->all();
    }

    /**
     * @return Builder<Model>|null
     */
    private function published(): ?Builder
    {
        $model = $this->postModel();

        if ($model === null) {
            return null;
        }

        $instance = new $model;

        return $model::query()
            ->where('is_published', true)
            ->with(array_values(array_filter(['author', 'blogPostCategory', 'media'], fn (string $relation): bool => method_exists($instance, $relation))))
            ->orderByDesc('published_at')
            ->orderByDesc($instance->getKeyName());
    }

    /**
     * @return class-string<Model>|null
     */
    private function postModel(): ?string
    {
        return self::model(config('site-builder.blog.post_model'));
    }

    /**
     * @return class-string<Model>|null
     */
    private function categoryModel(): ?string
    {
        return self::model(config('site-builder.blog.category_model'));
    }

    /**
     * @return class-string<Model>|null
     */
    private static function model(mixed $class): ?string
    {
        return is_string($class) && class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }
}
