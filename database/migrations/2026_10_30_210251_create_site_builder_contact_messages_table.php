<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Website › Contact messages: what visitors sent through a Contact form block. Kept for
        // site-builder.contact.keep_days (ContactMessage is prunable). No IP address is kept.
        Schema::create('site_builder_contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained('site_builder_pages')->nullOnDelete();
            // Which form it came from: the block's admin label or heading, or the page's title.
            $table->string('form_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('page_url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_builder_contact_messages');
    }
};
