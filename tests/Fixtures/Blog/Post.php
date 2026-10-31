<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stand-in for SaaSykit's BlogPost: the same columns and relations, and getFirstMediaUrl() in place of Spatie's media
 * library (its image column).
 */
class Post extends Model
{
    protected $table = 'blog_posts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function blogPostCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getFirstMediaUrl(string $collectionName = 'default'): string
    {
        return $collectionName === 'blog-images' ? (string) $this->getAttribute('image') : '';
    }

    public static function createTables(): void
    {
        Schema::create('blog_authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('public_name')->nullable();
        });

        Schema::create('blog_post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable();
            $table->foreignId('blog_post_category_id')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }
}
