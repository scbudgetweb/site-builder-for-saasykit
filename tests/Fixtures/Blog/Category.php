<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Model;

/**
 * A stand-in for SaaSykit's BlogPostCategory.
 */
class Category extends Model
{
    public $timestamps = false;

    protected $table = 'blog_post_categories';

    protected $guarded = [];
}
