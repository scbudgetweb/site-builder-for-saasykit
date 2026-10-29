<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Website › Mailing lists: a mailing list service (Brevo first) that sign-up forms add people to. The rows are
        // fixed (one per supported service), so it's inserted here.
        Schema::create('site_builder_mailing_list_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(false);
            $table->text('api_key')->nullable();
            $table->string('list_id')->nullable();
            $table->string('list_name')->nullable();
            // Double opt-in: the provider emails a confirmation link (its own template), and only then adds them.
            $table->boolean('double_opt_in')->default(false);
            $table->string('double_opt_in_template_id')->nullable();
            $table->string('double_opt_in_redirect_url', 500)->nullable();
            $table->timestamps();
        });

        DB::table('site_builder_mailing_list_providers')->insert([
            'name' => 'Brevo',
            'slug' => 'brevo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_builder_mailing_list_providers');
    }
};
