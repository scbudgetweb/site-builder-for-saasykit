<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_builder_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status')->default('draft')->index();
            // The built-in page (slot key, e.g. "home") this page replaces; unique, so a slot has at most one page.
            $table->string('replaces')->nullable()->unique();
            $table->boolean('show_page_banner')->default(true);
            // Filament Builder state: [{type, data}, ...].
            $table->json('blocks')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('share_image')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_builder_pages');
    }
};
