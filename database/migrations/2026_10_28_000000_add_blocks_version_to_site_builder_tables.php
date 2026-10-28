<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which shape the stored blocks are in (BlockSchema::VERSION when saved). Rows saved before 1.5 are 1.
        foreach (['site_builder_pages', 'site_builder_saved_blocks'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedSmallInteger('blocks_version')->default(1)->after('blocks');
            });
        }
    }

    public function down(): void
    {
        foreach (['site_builder_pages', 'site_builder_saved_blocks'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('blocks_version');
            });
        }
    }
};
