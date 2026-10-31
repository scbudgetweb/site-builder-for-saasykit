{{--
    Site builder theme: shadows SaaSykit's blog/view.blade.php. A Blog post template (Website › Theme builder) that
    applies to the post's category is the page's body; without one (or if it fails), SaaSykit's standard post shows.
--}}
@php($blogTemplate = app(\ScBudgetWeb\SiteBuilder\Blog\Blog::class)->renderPost($post))

<x-layouts.app>

    @push('head')
        @vite(['resources/js/blog.js'])
    @endpush

    <x-slot name="title">{{ $post->title }}</x-slot>

    @if(!empty($post->description))
        <x-slot name="description">{{ $post->description }}</x-slot>
    @endif

    @if ($blogTemplate !== null)
        @if ($post->media->count() > 0)
            <x-slot name="socialCard">{{ $post->getFirstMediaUrl('blog-images') }}</x-slot>
        @endif

        {{ $blogTemplate }}
    @else
        <x-blog.post :post="$post" />

        <div class="text-primary-500 text-sm text-center mx-auto mt-8">
            {{ __('Share this post.') }}
        </div>
        <div class="flex gap-3 justify-center pt-3">
            <x-link.social-icon name="x" title="{{ __('Twitter page') }}" link="https://x.com/intent/post?text={{ urlencode($post->title) }}&url={{ urlencode(url()->current()) }}" class="hover:text-primary-500"/>
            <x-link.social-icon name="linkedin" title="{{ __('LinkedIn community') }}" link="https://www.linkedin.com/shareArticle?url={{ urlencode(url()->current()) }}&title={{ urlencode($post->title) }}" class="hover:text-primary-500"/>
        </div>

        <div class="text-center">
            <x-section.outro>
                <x-heading.h6 class="text-primary-50">
                    {{ __('Stay up-to-date') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-50">
                    {{ __('Subscribe to our newsletter') }}
                </x-heading.h2>

                <x-input.field labelClass="text-primary-50" inputClass="bg-transparent placeholder-primary-100 text-primary-50" placeholder="{{ __('Your email address') }}" class="mx-auto mt-6" />

                <div class="mt-10">
                    <x-button-link.secondary href="{{route('blog')}}">
                        {{ __('Subscribe') }}
                    </x-button-link.secondary>
                </div>
            </x-section.outro>
        </div>

        @if (count($morePosts) > 0)

            <div class="text-center">
                <x-heading.h6 class="text-primary-500">
                    {{ __('Don\'t miss this') }}
                </x-heading.h6>
                <x-heading.h2>
                    {{ __('You might also like') }}
                </x-heading.h2>
            </div>

            <x-blog.post-cards :posts="$morePosts" link-to-more-posts="true"/>
        @endif
    @endif

</x-layouts.app>
