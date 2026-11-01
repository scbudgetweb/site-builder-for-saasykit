{{-- Where a media library file is used ($uses: label and, where there is one, a link to edit it). --}}
@if ($uses === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Nothing uses this file, so it can be deleted.') }}</p>
@else
    <ul class="space-y-1.5 text-sm">
        @foreach ($uses as $use)
            <li class="flex items-start gap-2">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedLink" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                @if ($use['url'])
                    <x-filament::link :href="$use['url']">{{ $use['label'] }}</x-filament::link>
                @else
                    <span class="text-gray-700 dark:text-gray-300">{{ $use['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
