<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Theme Builder templates: a header, footer (or a type an app adds) built from blocks, and where it applies.
        Schema::create('site_builder_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 40)->index();
            $table->boolean('is_active')->default(true);
            // "everywhere", "only" (the pages listed) or "except" (every page but those listed).
            $table->string('applies_to', 20)->default('everywhere');
            $table->json('page_ids')->nullable();
            $table->longText('blocks')->nullable();
            $table->unsignedSmallInteger('blocks_version')->default(1);
            $table->timestamps();
        });

        Schema::table('site_builder_page_drafts', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('saved_block_id')->constrained('site_builder_templates')->cascadeOnDelete();
            $table->unique(['template_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('site_builder_page_drafts', function (Blueprint $table) {
            $table->dropUnique(['template_id', 'user_id']);
            $table->dropConstrainedForeignId('template_id');
        });

        Schema::dropIfExists('site_builder_templates');
    }
};
