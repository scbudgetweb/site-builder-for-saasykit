{{-- A media library file's thumbnail: the picture itself (lazy), or an icon for a sound or other file. --}}
@php($file = $getRecord())
<div class="relative aspect-square w-full overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    @if ($file->isImage())
        <img src="{{ $file->url() }}" alt="" loading="lazy" decoding="async" @class(['h-full w-full', 'object-cover' => $file->kind === 'image', 'object-contain p-[12%]' => $file->kind !== 'image'])>
    @else
        <div class="flex h-full w-full flex-col items-center justify-center gap-1 text-gray-400 dark:text-gray-500">
            <x-filament::icon :icon="$file->kind === 'audio' ? \Filament\Support\Icons\Heroicon::OutlinedMusicalNote : \Filament\Support\Icons\Heroicon::OutlinedDocument" class="h-1/3 w-1/3" />
            <span class="text-xs font-medium uppercase">{{ pathinfo($file->path, PATHINFO_EXTENSION) }}</span>
        </div>
    @endif
</div>
