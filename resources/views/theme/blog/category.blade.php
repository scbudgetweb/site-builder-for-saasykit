{{-- Site builder theme: shadows SaaSykit's blog/category.blade.php (the heading and share links in the inner banner). --}}
<x-layouts.app>
    <x-site-builder::banner variant="inner" class="py-14 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
            <h1 class="sb-h1 text-sb-banner-heading">{{ $category->name }}</h1>
            <p class="mt-4 text-lg text-sb-banner-subtitle">{{ __('Check out our latest news and updates.') }}</p>

            <div class="mt-6 flex justify-center gap-3 text-sb-banner-subtitle">
                <x-link.social-icon name="x" title="{{ __('Share on X') }}" link="https://x.com/intent/post?text={{ urlencode(__('Check out the latest news and updates :app blog!', ['app' => config('app.name')])) }}&url={{ urlencode(url()->current()) }}" class="border-current/40 hover:border-current" />
                <x-link.social-icon name="linkedin" title="{{ __('Share on LinkedIn') }}" link="https://www.linkedin.com/shareArticle?url={{ urlencode(url()->current()) }}&title={{ urlencode(__('Check out the latest news and updates :app blog!', ['app' => config('app.name')])) }}" class="border-current/40 hover:border-current" />
            </div>
        </div>
    </x-site-builder::banner>

    <div class="pt-10">
        <x-blog.post-cards :posts="$posts" />
    </div>

    <div class="mx-auto p-4 text-center md:max-w-lg">
        {{ $posts->links() }}
    </div>
</x-layouts.app>
