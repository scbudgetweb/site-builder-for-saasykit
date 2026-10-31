<?php

namespace ScBudgetWeb\SiteBuilder\Blog;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * A blog post as the blog blocks show it, read from SaaSykit's model (Blog::fromModel()) or made up for the canvas when
 * there's no post yet (Blog::samplePost()). Blocks never touch the model, so an app with a different blog only needs
 * its own model to give the same fields.
 */
final class BlogPost
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly string $url,
        public readonly string $excerpt = '',
        public readonly string $body = '',
        public readonly ?CarbonInterface $publishedAt = null,
        public readonly bool $isPublished = true,
        public readonly string $author = '',
        public readonly ?int $categoryId = null,
        public readonly string $category = '',
        public readonly ?string $categoryUrl = null,
        public readonly ?string $imageUrl = null,
    ) {}

    /**
     * From SaaSykit's BlogPost (title, slug, description, body, published_at, is_published, its author and
     * blogPostCategory, and the first image in its blog-images media collection). Missing parts are left blank.
     */
    public static function fromModel(Model $post): self
    {
        $category = self::related($post, 'blogPostCategory');
        $author = self::related($post, 'author');
        $publishedAt = $post->getAttribute('published_at');
        $categorySlug = $category?->getAttribute('slug');

        return new self(
            id: is_numeric($post->getKey()) ? (int) $post->getKey() : null,
            title: (string) $post->getAttribute('title'),
            url: self::route('blog.view', $post->getAttribute('slug')) ?? '#',
            excerpt: trim((string) $post->getAttribute('description')),
            body: (string) $post->getAttribute('body'),
            publishedAt: $publishedAt instanceof CarbonInterface ? $publishedAt : (is_string($publishedAt) && $publishedAt !== '' ? Carbon::parse($publishedAt) : null),
            isPublished: (bool) $post->getAttribute('is_published'),
            author: $author === null ? '' : trim((string) (method_exists($author, 'getPublicName') ? $author->getPublicName() : $author->getAttribute('name'))),
            categoryId: is_numeric($category?->getKey()) ? (int) $category->getKey() : null,
            category: trim((string) $category?->getAttribute('name')),
            categoryUrl: self::route('blog.category', $categorySlug),
            imageUrl: self::imageOf($post),
        );
    }

    /**
     * The date as the site shows dates (SaaSykit's app.date_format), or "Draft" for a post that isn't published.
     */
    public function date(): string
    {
        if (! $this->isPublished) {
            return __('Draft');
        }

        return $this->publishedAt?->translatedFormat((string) config('app.date_format', 'j F Y')) ?? '';
    }

    private static function related(Model $post, string $relation): ?Model
    {
        if (! method_exists($post, $relation)) {
            return null;
        }

        $related = $post->getRelationValue($relation);

        return $related instanceof Model ? $related : null;
    }

    private static function route(string $name, mixed $slug): ?string
    {
        return is_string($slug) && $slug !== '' && Route::has($name) ? route($name, ['slug' => $slug]) : null;
    }

    private static function imageOf(Model $post): ?string
    {
        if (! method_exists($post, 'getFirstMediaUrl')) {
            return null;
        }

        try {
            $url = $post->getFirstMediaUrl('blog-images');
        } catch (Throwable) {
            return null;
        }

        return is_string($url) && $url !== '' ? $url : null;
    }
}
