<?php

use Illuminate\Support\Facades\Route;
use ScBudgetWeb\SiteBuilder\Http\Controllers\PageController;
use ScBudgetWeb\SiteBuilder\Http\Controllers\ReleaseEditLock;

/*
| Pages: the signed admin previews (any status, or an earlier revision), and published pages at /{slug}. The fallback
| route is only tried after every other route, so a page can never hide one of the app's own URLs.
*/

// Closing an editor frees its edit lock at once (navigator.sendBeacon). The signed link names the lock and its holder,
// so it needs no session or CSRF token; it can only free a lock that person holds, which lapses anyway.
Route::post(trim((string) config('site-builder.pages.preview_path', 'site-builder/preview'), '/').'/edit-lock/{kind}/{id}/{user}', ReleaseEditLock::class)
    ->middleware('signed')
    ->whereIn('kind', ['page', 'block', 'template'])
    ->whereNumber('id')
    ->name('site-builder.edit-lock.release');

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

    // The visual builder's canvas: the page with someone's draft, marked up for editing.
    Route::get($previewPath.'/{page}/canvas/{user}', [PageController::class, 'canvas'])
        ->middleware('signed')
        ->whereNumber(['page', 'user'])
        ->name('site-builder.pages.canvas');

    // The same for a block library item: its block on its own, in the theme.
    Route::get($previewPath.'/library/{savedBlock}/canvas/{user}', [PageController::class, 'libraryCanvas'])
        ->middleware('signed')
        ->whereNumber(['savedBlock', 'user'])
        ->name('site-builder.library.canvas');

    // A Theme Builder template's canvas: its blocks on their own, the site's header and footer hidden.
    Route::get($previewPath.'/templates/{template}/canvas/{user}', [PageController::class, 'templateCanvas'])
        ->middleware('signed')
        ->whereNumber(['template', 'user'])
        ->name('site-builder.templates.canvas');

    Route::fallback([PageController::class, 'show'])
        ->name('site-builder.pages.show');
});
