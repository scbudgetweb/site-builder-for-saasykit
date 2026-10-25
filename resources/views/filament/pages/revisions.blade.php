{{-- Pages › Revisions: each saved version, newest first, with who saved it, Preview (signed, new tab) and Restore. --}}
@if ($revisions->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No revisions yet. One is kept each time the page is saved with changes.') }}</p>
@else
    <ul class="divide-y divide-gray-200 dark:divide-white/10">
        @foreach ($revisions as $revision)
            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">
                        {{ $revision->created_at?->translatedFormat('j M Y, H:i') }}
                        @if ($loop->first)
                            <x-filament::badge color="success" class="ms-1 inline-flex">{{ __('Current') }}</x-filament::badge>
                        @endif
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $revision->user_name ?: __('Unknown') }}
                        · {{ \Illuminate\Support\Str::limit((string) ($revision->snapshot['title'] ?? ''), 60) }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <x-filament::button tag="a" :href="$revision->previewUrl()" target="_blank" color="gray" size="sm" icon="heroicon-o-eye">
                        {{ __('Preview') }}
                    </x-filament::button>
                    @unless ($loop->first)
                        <x-filament::button size="sm" icon="heroicon-o-arrow-uturn-left" wire:click="restoreRevision({{ $revision->getKey() }})">
                            {{ __('Restore') }}
                        </x-filament::button>
                    @endunless
                </div>
            </li>
        @endforeach
    </ul>
@endif
