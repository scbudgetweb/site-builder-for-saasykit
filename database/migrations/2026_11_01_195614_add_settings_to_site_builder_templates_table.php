<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A template's own settings: for a header, how it behaves as the page scrolls (HeaderBehaviour).
        Schema::table('site_builder_templates', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('category_ids');
        });
    }

    public function down(): void
    {
        Schema::table('site_builder_templates', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
