<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Pages\Media;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageMedia;
use ScBudgetWeb\SiteBuilder\Media\MediaLibrary;
use ScBudgetWeb\SiteBuilder\Models\MediaFile;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Settings\AudioUploads;
use ScBudgetWeb\SiteBuilder\Settings\ImageUploads;
use ScBudgetWeb\SiteBuilder\Support\SafeSvg;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Website › Media's Library tab: every image and sound file uploaded to pages, as a grid of thumbnails or a list, with
 * filters (kind, in use, date) and a search on the name. Upload adds several at once, with the Images and Audio tabs' checks. A
 * file's Details show it with its link, where it's used and its alt text; Download and Delete are beside it. A file
 * that a page, library block, template or draft uses can't be deleted. Every action needs ManageMedia::canSeeLibrary().
 */
class LibraryTable
{
    public static function configure(Table $table, ManageMedia $page): Table
    {
        $isGrid = $page->libraryLayout !== 'list';

        return $table
            ->query(fn (): Builder => ManageMedia::canSeeLibrary()
                ? MediaFile::query()->inUploadFolder(app(PageService::class)->uploadDirectory())
                : MediaFile::query()->whereRaw('1 = 0'))
            ->defaultSort('created_at', 'desc')
            ->columns($isGrid ? self::gridColumns() : self::listColumns())
            ->contentGrid($isGrid ? ['default' => 2, 'sm' => 3, 'lg' => 4, 'xl' => 5, '2xl' => 6] : null)
            ->paginated([24, 48, 96])
            ->defaultPaginationPageOption(48)
            ->recordAction('details')
            ->filters([
                SelectFilter::make('kind')
                    ->label(__('Kind'))
                    ->options(MediaFile::kinds()),
                TernaryFilter::make('in_use')
                    ->label(__('In use'))
                    ->placeholder(__('All files'))
                    ->trueLabel(__('Used on a page, block or template'))
                    ->falseLabel(__('Not used anywhere'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereIn('path', array_keys(app(MediaLibrary::class)->usedPaths())),
                        false: fn (Builder $query): Builder => $query->whereNotIn('path', array_keys(app(MediaLibrary::class)->usedPaths())),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('uploaded')
                    ->label(__('Uploaded'))
                    ->schema([
                        DatePicker::make('from')->label(__('Uploaded from')),
                        DatePicker::make('until')->label(__('Uploaded until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        ($data['from'] ?? null) ? __('From :date', ['date' => $data['from']]) : null,
                        ($data['until'] ?? null) ? __('Until :date', ['date' => $data['until']]) : null,
                    ]))),
            ])
            ->recordActions([
                self::detailsAction(),
                ActionGroup::make([
                    self::downloadAction(),
                    self::deleteAction(),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('deleteUnused')
                    ->label(__('Delete'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->authorize(fn (): bool => ManageMedia::canSeeLibrary())
                    ->requiresConfirmation()
                    ->modalHeading(__('Delete the files chosen?'))
                    ->modalDescription(__('Files used on a page, library block or template are kept. The rest are deleted for good.'))
                    ->action(function (Collection $records): void {
                        $library = app(MediaLibrary::class);
                        $deleted = $records->filter(fn (MediaFile $file): bool => $library->delete($file))->count();
                        $kept = $records->count() - $deleted;

                        Notification::make()
                            ->title(trans_choice(':count file deleted|:count files deleted', $deleted))
                            ->body($kept > 0 ? trans_choice(':count file is in use, so it was kept.|:count files are in use, so they were kept.', $kept) : null)
                            ->status($kept > 0 ? 'warning' : 'success')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedPhoto)
            ->emptyStateHeading(__('No files yet'))
            ->emptyStateDescription(__('Everything uploaded to your pages shows here. Upload images and sound files here, or in any block.'))
            ->emptyStateActions([self::uploadAction()]);
    }

    /**
     * Upload: several images and sound files at once, each held to its own kind's types and size (the Images and Audio tabs), and
     * each SVG checked (SafeSvg). They join the library as they're stored.
     */
    public static function uploadAction(): Action
    {
        $images = app(ImageUploads::class);
        $audio = app(AudioUploads::class);
        $pages = app(PageService::class);

        return Action::make('upload')
            ->label(__('Upload'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->authorize(fn (): bool => ManageMedia::canSeeLibrary())
            ->modalHeading(__('Upload files'))
            ->modalSubmitActionLabel(__('Add to the library'))
            ->schema([
                FileUpload::make('files')
                    ->label(__('Images and sound files'))
                    ->helperText(__('Images: :imageTypes, up to :imageSize. Sound files: :audioTypes, up to :audioSize. Change these in the Images and Audio tabs.', [
                        'imageTypes' => $images->typeLabels(),
                        'imageSize' => ImageUploads::megabytes($images->maxKilobytes()),
                        'audioTypes' => $audio->typeLabels(),
                        'audioSize' => AudioUploads::megabytes($audio->maxKilobytes()),
                    ]))
                    ->multiple()
                    ->maxParallelUploads(3)
                    ->required()
                    ->disk($pages->uploadDisk())
                    ->directory($pages->uploadDirectory())
                    ->visibility('public')
                    ->acceptedFileTypes([...$images->mimeTypes(), ...$audio->mimeTypes()])
                    ->maxSize(max($images->maxKilobytes(), $audio->maxKilobytes()))
                    ->rule(new SafeSvg)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($images): void {
                        if ($value instanceof TemporaryUploadedFile && in_array($value->getMimeType(), $images->mimeTypes(), true) && $value->getSize() > $images->maxKilobytes() * 1024) {
                            $fail(__(':name is larger than :size, the largest image Website › Media › Images allows.', ['name' => $value->getClientOriginalName(), 'size' => ImageUploads::megabytes($images->maxKilobytes())]));
                        }
                    })
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): ?string => app(MediaLibrary::class)->store($file))
                    ->previewable(false)
                    ->appendFiles(),
            ])
            ->action(function (array $data): void {
                $count = count(array_filter((array) ($data['files'] ?? [])));

                Notification::make()
                    ->title(trans_choice(':count file added to the library|:count files added to the library', $count))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    private static function gridColumns(): array
    {
        return [
            Stack::make([
                ViewColumn::make('preview')->view('site-builder::filament.media.tile'),
                TextColumn::make('name')
                    ->searchable(['name', 'path'])
                    ->weight('medium')
                    ->size('sm')
                    ->lineClamp(1)
                    // A long name without spaces (an upload's random name) breaks anywhere, so the tile keeps its width.
                    ->extraAttributes(['style' => 'padding-top: 0.5rem; word-break: break-all']),
                TextColumn::make('summary')
                    ->state(fn (MediaFile $record): string => self::summary($record))
                    ->color('gray')
                    ->size('xs'),
            ])->space(1),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function listColumns(): array
    {
        return [
            ViewColumn::make('preview')->label('')->view('site-builder::filament.media.tile')->extraAttributes(['style' => 'width: 4rem']),
            TextColumn::make('name')->label(__('Name'))->searchable(['name', 'path'])->sortable()->weight('medium')->wrap(),
            TextColumn::make('kind')->label(__('Kind'))->badge()->formatStateUsing(fn (string $state): string => MediaFile::kinds()[$state] ?? $state),
            TextColumn::make('size')->label(__('Size'))->sortable()->formatStateUsing(fn (int $state): string => Number::fileSize($state, 1)),
            TextColumn::make('dimensions')->label(__('Dimensions'))->state(fn (MediaFile $record): ?string => $record->width ? "{$record->width} × {$record->height}" : null)->placeholder('-'),
            TextColumn::make('created_at')->label(__('Uploaded'))->since()->sortable()->dateTimeTooltip(),
        ];
    }

    private static function summary(MediaFile $file): string
    {
        return implode(' · ', array_filter([
            Str::upper(pathinfo($file->path, PATHINFO_EXTENSION)),
            Number::fileSize($file->size, 1),
            $file->width ? "{$file->width} × {$file->height}" : null,
        ]));
    }

    private static function detailsAction(): Action
    {
        return Action::make('details')
            ->label(__('Details'))
            ->icon(Heroicon::OutlinedInformationCircle)
            ->authorize(fn (): bool => ManageMedia::canSeeLibrary())
            ->slideOver()
            ->modalWidth('xl')
            ->modalHeading(fn (MediaFile $record): string => $record->name)
            ->fillForm(fn (MediaFile $record): array => ['name' => $record->name, 'alt' => $record->alt])
            ->schema(fn (MediaFile $record): array => [
                View::make('site-builder::filament.media.preview')->viewData(['file' => $record]),
                Grid::make(2)->schema([
                    TextEntry::make('summary')->label(__('File'))->state(self::summary($record)),
                    TextEntry::make('uploaded')
                        ->label(__('Uploaded'))
                        ->state(fn (): string => trim($record->created_at?->format('j M Y').' '.(self::uploader($record) ? __('by :name', ['name' => self::uploader($record)]) : ''))),
                    TextEntry::make('url')
                        ->label(__('Link'))
                        ->state($record->url())
                        ->copyable()
                        ->copyMessage(__('Link copied'))
                        ->columnSpanFull()
                        ->extraAttributes(['style' => 'word-break: break-all']),
                ]),
                TextInput::make('name')
                    ->label(__('Name'))
                    ->helperText(__('Only shown here, to find the file again. Its link doesn\'t change.'))
                    ->required()
                    ->maxLength(250),
                TextInput::make('alt')
                    ->label(__('Alternative text'))
                    ->helperText(__('A short description of the picture for screen readers and search engines, filled in when the image is chosen from the library.'))
                    ->maxLength(250)
                    ->visible($record->isImage()),
                Section::make(__('Used on'))
                    ->compact()
                    ->schema([
                        View::make('site-builder::filament.media.uses')->viewData(['uses' => app(MediaLibrary::class)->usesOf($record->path)]),
                    ]),
            ])
            ->modalSubmitActionLabel(__('Save'))
            ->extraModalFooterActions(fn (MediaFile $record): array => [
                self::downloadAction()->record($record),
            ])
            ->action(function (array $data, MediaFile $record): void {
                $record->update([
                    'name' => trim((string) $data['name']),
                    'alt' => $record->isImage() && filled($data['alt'] ?? null) ? trim((string) $data['alt']) : null,
                ]);

                Notification::make()->title(__('Saved'))->success()->send();
            });
    }

    private static function downloadAction(): Action
    {
        return Action::make('download')
            ->label(__('Download'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->authorize(fn (): bool => ManageMedia::canSeeLibrary())
            ->action(function (MediaFile $record): StreamedResponse {
                $extension = pathinfo($record->path, PATHINFO_EXTENSION);
                $name = Str::endsWith(Str::lower($record->name), '.'.Str::lower($extension)) ? $record->name : $record->name.'.'.$extension;

                return Storage::disk($record->disk)->download($record->path, $name);
            });
    }

    private static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('Delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize(fn (): bool => ManageMedia::canSeeLibrary())
            ->disabled(fn (MediaFile $record): bool => app(MediaLibrary::class)->isUsed($record->path))
            ->tooltip(fn (MediaFile $record): ?string => app(MediaLibrary::class)->isUsed($record->path) ? __('In use: Details lists where.') : null)
            ->requiresConfirmation()
            ->modalHeading(fn (MediaFile $record): string => __('Delete :name?', ['name' => $record->name]))
            ->modalDescription(__('The file is deleted for good. Nothing uses it now.'))
            ->action(function (MediaFile $record): void {
                if (app(MediaLibrary::class)->delete($record)) {
                    Notification::make()->title(__('File deleted'))->success()->send();

                    return;
                }

                Notification::make()->title(__('That file is in use, so it was kept.'))->warning()->send();
            });
    }

    private static function uploader(MediaFile $file): ?string
    {
        $model = config('auth.providers.users.model');

        if ($file->uploaded_by === null || ! is_string($model) || ! class_exists($model)) {
            return null;
        }

        $user = $model::query()->find($file->uploaded_by);

        return $user?->getAttribute('name');
    }
}
