<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A visual builder draft can be of a page or of a block library item.
        Schema::table('site_builder_page_drafts', function (Blueprint $table) {
            $table->unsignedBigInteger('page_id')->nullable()->change();
            $table->foreignId('saved_block_id')->nullable()->after('page_id')->constrained('site_builder_saved_blocks')->cascadeOnDelete();
            $table->unique(['saved_block_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('site_builder_page_drafts', function (Blueprint $table) {
            $table->dropUnique(['saved_block_id', 'user_id']);
            $table->dropConstrainedForeignId('saved_block_id');
        });
    }
};
