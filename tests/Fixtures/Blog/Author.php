<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Model;

/**
 * A stand-in for SaaSykit's User as a post's author, with its public name.
 */
class Author extends Model
{
    public $timestamps = false;

    protected $table = 'blog_authors';

    protected $guarded = [];

    public function getPublicName(): string
    {
        return $this->public_name ?? $this->name;
    }
}
