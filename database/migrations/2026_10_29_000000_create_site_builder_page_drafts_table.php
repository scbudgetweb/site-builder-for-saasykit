<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The visual builder's work in progress: one per person and page, saved as they go, until they publish it.
        Schema::create('site_builder_page_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('site_builder_pages')->cascadeOnDelete();
            // No foreign key: the package doesn't own the users table.
            $table->unsignedBigInteger('user_id');
            $table->longText('blocks')->nullable();
            $table->unsignedSmallInteger('blocks_version')->default(1);
            // The page's updated_at when the draft started, so publishing can tell if someone saved it since.
            $table->timestamp('base_updated_at')->nullable();
            // Undo and redo: earlier and later versions of the blocks, newest last.
            $table->longText('history')->nullable();
            $table->longText('future')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_builder_page_drafts');
    }
};
