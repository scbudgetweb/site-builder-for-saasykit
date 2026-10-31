<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Blog post and Blog archive templates apply by category ("only" or "except" these), as headers do by page.
        Schema::table('site_builder_templates', function (Blueprint $table) {
            $table->json('category_ids')->nullable()->after('page_ids');
        });
    }

    public function down(): void
    {
        Schema::table('site_builder_templates', function (Blueprint $table) {
            $table->dropColumn('category_ids');
        });
    }
};
