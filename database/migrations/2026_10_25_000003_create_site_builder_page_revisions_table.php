<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each saved version of a page (D0a-5): its content, banner and SEO settings, and who saved it.
        Schema::create('site_builder_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('site_builder_pages')->cascadeOnDelete();
            // {title, show_page_banner, blocks, seo_title, seo_description, share_image, noindex}
            $table->json('snapshot');
            // No foreign key: the package doesn't own the users table, and the name stays if the user is deleted.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_builder_page_revisions');
    }
};
