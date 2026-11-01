{{-- A media library file at a larger size in its Details: the picture, the sound file's player, or its kind. --}}
<div class="overflow-hidden rounded-xl bg-gray-100 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    @if ($file->isImage())
        <img src="{{ $file->url() }}" alt="{{ $file->alt ?? '' }}" class="mx-auto max-h-80 w-auto object-contain">
    @elseif ($file->kind === 'audio')
        <div class="p-4">
            <audio controls preload="none" src="{{ $file->url() }}" class="w-full"></audio>
        </div>
    @else
        <div class="flex items-center justify-center gap-2 p-8 text-sm text-gray-500">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedDocument" class="h-6 w-6" />
            {{ \Illuminate\Support\Str::upper(pathinfo($file->path, PATHINFO_EXTENSION)) }}
        </div>
    @endif
</div>
