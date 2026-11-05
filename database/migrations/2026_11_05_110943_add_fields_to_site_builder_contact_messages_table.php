<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every answer a visitor gave, as {label, type, value} in the form's order (a Contact form block's Fields).
        // The name, email, phone, subject and message columns are still filled, for the list, search and Reply.
        Schema::table('site_builder_contact_messages', function (Blueprint $table) {
            $table->json('fields')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('site_builder_contact_messages', function (Blueprint $table) {
            $table->dropColumn('fields');
        });
    }
};
