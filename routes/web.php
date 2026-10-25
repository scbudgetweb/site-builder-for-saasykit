<?php

use Illuminate\Support\Facades\Route;
use ScBudgetWeb\SiteBuilder\Http\Controllers\PageController;

/*
| Pages: the signed admin previews (any status, or an earlier revision), and published pages at /{slug}. The fallback
| route is only tried after every other route, so a page can never hide one of the app's own URLs.
*/

Route::middleware(config('site-builder.pages.middleware', ['web']))->group(function () {
    $previewPath = trim((string) config('site-builder.pages.preview_path', 'site-builder/preview'), '/');

    Route::get($previewPath.'/{page}', [PageController::class, 'preview'])
        ->middleware('signed')
        ->whereNumber('page')
        ->name('site-builder.pages.preview');

    Route::get($previewPath.'/{page}/revisions/{revision}', [PageController::class, 'previewRevision'])
        ->middleware('signed')
        ->whereNumber(['page', 'revision'])
        ->scopeBindings()
        ->name('site-builder.pages.revision-preview');

    Route::fallback([PageController::class, 'show'])
        ->name('site-builder.pages.show');
});
