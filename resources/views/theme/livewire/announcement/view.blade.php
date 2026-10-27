{{--
    Site builder theme: shadows SaaSykit's livewire/announcement/view.blade.php (Admin › Announcements), on the website
    and in the dashboard. Same markup and behaviour, with Branding › Header & footer › Announcement bar colours as inline
    styles (the dashboard's stylesheet has no site-builder utilities). Each colour is a validated hex colour, or SaaSykit's
    standard class when it isn't set.
--}}
@php
    $colours = app(\ScBudgetWeb\SiteBuilder\Branding\Branding::class)->announcementColours();
    $barStyle = implode(' ', array_filter([
        $colours['background'] ? "background-color: {$colours['background']};" : null,
        $colours['text'] ? "color: {$colours['text']};" : null,
    ]));
@endphp

<div class="text-sm" x-data="{ isVisible: true }">
    @if ($announcement !== null)
        @if ($colours['link'] || $colours['linkHover'])
            <style>
                @if ($colours['link']) .sb-announcement a:not(.announcement-close-button) { color: {{ $colours['link'] }}; } @endif
                @if ($colours['linkHover']) .sb-announcement a:not(.announcement-close-button):hover, .sb-announcement a:not(.announcement-close-button):focus-visible { color: {{ $colours['linkHover'] }}; } @endif
            </style>
        @endif
        <div @class([
            'text-center announcement sb-announcement px-4',
            'bg-secondary-300' => ! $colours['background'],
            'text-secondary-900' => ! $colours['text'],
        ]) @if ($barStyle !== '') style="{{ $barStyle }}" @endif x-show="isVisible">
            <div class="mx-auto py-3 flex gap-2 md:gap-8 items-center justify-center">
                <span class="line-clamp-3 md:line-clamp-2" >
                    {!! str($announcement->content)->sanitizeHtml() !!}
                </span>
                @if ($announcement->is_dismissible)
                <a wire:click="dismiss({{ $announcement->id }})" @class([
                    'cursor-pointer hover:scale-103 transition announcement-close-button',
                    'text-primary-900 hover:text-primary-900' => ! $colours['text'],
                ]) @if ($colours['text']) style="color: {{ $colours['text'] }};" @endif aria-label="{{ __('Close') }}" @click="isVisible = !isVisible">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </a>
                @endif
            </div>
        </div>
    @endif
</div>
