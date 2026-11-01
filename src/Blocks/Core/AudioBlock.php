<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Media\MediaLibrary;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Settings\AudioUploads;

/**
 * An uploaded sound file (as Divi's Audio module): a card with an optional cover image, the title, artist and a short
 * description, and the browser's own player, which loads nothing until it's played (preload="none"). The file is kept
 * with the page images, so it's removed with them once nothing uses it. Design › Items styles the card. The types and
 * largest size taken are set in Website › Media › Audio (AudioUploads).
 */
class AudioBlock extends PageBlock
{
    public static function name(): string
    {
        return 'audio';
    }

    public static function label(): string
    {
        return __('Audio');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedMusicalNote;
    }

    public static function defaultWidth(): string
    {
        return 'narrow';
    }

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(__('Player'))
            ->text('title', __('Title text'))
            ->text('meta', __('Artist text'))
            ->text('text', __('Description text'));
    }

    protected static function fields(): array
    {
        $pages = app(PageService::class);
        $uploads = app(AudioUploads::class);

        return [
            ...static::headingFields(),
            FileUpload::make('audio')
                ->label(__('Sound file'))
                ->helperText(fn (): string => __('An :types file, up to :size (Website › Media › Audio). MP3 plays in every browser.', [
                    'types' => app(AudioUploads::class)->typeLabels(),
                    'size' => AudioUploads::megabytes(app(AudioUploads::class)->maxKilobytes()),
                ]))
                ->required()
                ->disk($pages->uploadDisk())
                ->directory($pages->uploadDirectory())
                ->visibility('public')
                ->acceptedFileTypes($uploads->mimeTypes())
                ->maxSize($uploads->maxKilobytes())
                // As imageUpload(): trust any file in the pages upload folder, and nothing else on the disk (lesson 1).
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): ?string => app(MediaLibrary::class)->store($file))
                ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => app(PageService::class)->isPageUpload($file)),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('title')
                    ->label(__('Title'))
                    ->helperText(__('Shown above the player, and read out by screen readers.'))
                    ->required()
                    ->maxLength(150),
                TextInput::make('artist')
                    ->label(__('Artist or speaker (optional)'))
                    ->maxLength(150),
            ]),
            Textarea::make('description')
                ->label(__('Description (optional)'))
                ->rows(2)
                ->maxLength(500),
            static::imageUpload('cover', __('Cover image (optional)'))
                ->helperText(__('A square picture beside the title, such as album art.')),
            Toggle::make('download')
                ->label(__('Offer a download link'))
                ->default(false),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $text = fn (string $key): string => is_string($data[$key] ?? null) ? trim($data[$key]) : '';

        return [
            'audioUrl' => static::imageUrl($data['audio'] ?? null),
            'coverUrl' => static::imageUrl($data['cover'] ?? null),
            'title' => $text('title'),
            'artist' => $text('artist'),
            'description' => $text('description'),
            'offersDownload' => (bool) ($data['download'] ?? false),
        ];
    }
}
