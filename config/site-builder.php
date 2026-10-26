<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin navigation
    |--------------------------------------------------------------------------
    |
    | Everything the package adds to the admin panel (Pages, Block library,
    | Navigation, Branding, Legal pages) sits in one navigation group of its own.
    |
    */

    'navigation' => [
        'group' => 'Website',
        'icon' => 'heroicon-s-globe-alt',
        'collapsed' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | The base theme replaces SaaSykit's marketing site (home page, header,
    | footer, layouts, blog index) by putting its views in front of SaaSykit's.
    | SaaSykit's own view files are never edited. Turn it off to keep them.
    |
    | When view.paths includes the app's own theme folder (app_path), the
    | theme goes straight after it, so a view there overrides the theme's.
    |
    */

    'theme' => [
        'enabled' => env('SITE_BUILDER_THEME', true),
        'app_path' => resource_path('views/theme'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | The permission (checked with the user's can()) each admin area needs.
    | SaaSykit's "update settings" covers the site-wide settings.
    |
    */

    'permissions' => [
        'branding' => 'update settings',
        'legal' => 'update settings',
        'pages' => [
            'view' => 'view pages',
            'create' => 'create pages',
            'update' => 'update pages',
            'delete' => 'delete pages',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Published pages are served at /{slug} by a fallback route, so a page can
    | never hide one of the app's own routes. Turn the routes off to serve pages
    | yourself (PageController shows how).
    |
    */

    'pages' => [
        'routes' => true,
        'preview_path' => 'site-builder/preview',
        'middleware' => ['web'],
        // How many saved versions of each page to keep (Pages › Revisions).
        'revisions' => 30,
        // Cache the HTML of pages whose blocks are all cacheable (no live figures or videos), until anything changes.
        'cache' => env('SITE_BUILDER_PAGE_CACHE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Logos, banners and images in page blocks are stored on this (public) disk,
    | under the given folder.
    |
    */

    'uploads' => [
        'disk' => env('SITE_BUILDER_UPLOAD_DISK', 'public'),
        'directory' => 'site-builder',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings cache
    |--------------------------------------------------------------------------
    |
    | Every saved setting is cached as one array (one cache read per request)
    | until it's saved again. A deploy that clears the cache is fine: it refills
    | from the database on the next request.
    |
    */

    'cache_key' => 'site-builder.settings',

];
