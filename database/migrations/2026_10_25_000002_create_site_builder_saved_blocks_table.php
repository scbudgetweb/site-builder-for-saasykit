<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The block library: blocks and Sections saved for reuse, linked from pages by "Saved block" blocks.
        Schema::create('site_builder_saved_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // The saved block's type (e.g. "call_to_action"), for the library list.
            $table->string('type')->index();
            // Filament Builder state with one item: [{type, data}]. Library items are few, so JSON fits.
            $table->json('blocks');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_builder_saved_blocks');
    }
};
