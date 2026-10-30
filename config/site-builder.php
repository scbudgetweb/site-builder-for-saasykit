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
        'mailing_lists' => 'update settings',
        // Making, changing and deleting block presets (anyone who can edit a page can pick one).
        'presets' => 'update settings',
        'pages' => [
            'view' => 'view pages',
            'create' => 'create pages',
            'update' => 'update pages',
            'delete' => 'delete pages',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mailing lists
    |--------------------------------------------------------------------------
    |
    | Website › Mailing lists connects a mailing list service (Brevo first);
    | sign-up forms add people to it. An address here is emailed each time
    | someone signs up (blank: nobody is).
    |
    */

    'mailing_lists' => [
        'notify_email' => env('SITE_BUILDER_MAILING_LIST_NOTIFY_EMAIL'),
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

    /*
    |--------------------------------------------------------------------------
    | Livewire nesting depth
    |--------------------------------------------------------------------------
    |
    | Livewire refuses updates whose property path is deeper than
    | livewire.payload.max_nesting_depth (10 by default), and rich text in the
    | builder's panels (lists, tables, quotes) goes deeper. The package raises
    | that limit to this many levels, never lowers it, and never goes past 128.
    | Set it to false to leave Livewire's own setting as it is.
    |
    */

    'livewire_nesting_depth' => 48,

];
