<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ScBudgetWeb\SiteBuilder\Media\MediaLibrary;

return new class extends Migration
{
    public function up(): void
    {
        // Website › Media library: one row per file uploaded to pages (images and sound files). A file with a row stays
        // until someone deletes it there. Blocks still store the file's path, so nothing in them changes.
        Schema::create('site_builder_media_files', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 64);
            $table->string('path', 500)->unique();
            // The name shown in the library: the uploaded file's own name, which can be changed there.
            $table->string('name');
            $table->string('kind', 16)->index();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // Filled in for the image wherever it's chosen from the library.
            $table->string('alt')->nullable();
            // The user who uploaded it. No foreign key: the package doesn't own the users table.
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        // Files already uploaded join the library, so none is lost under the new rule. A disk that can't be read now
        // (a cloud disk offline, say) is caught up by `php artisan site-builder:media-sync`.
        try {
            app(MediaLibrary::class)->recordExisting();
        } catch (Throwable) {
            //
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_builder_media_files');
    }
};
