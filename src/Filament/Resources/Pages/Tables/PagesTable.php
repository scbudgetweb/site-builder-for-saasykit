<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            // Pages open in the visual builder; a page in the bin opens as a form, where it can be restored.
            ->recordUrl(fn (Page $record): ?string => PageResource::canEdit($record)
                ? PageResource::getUrl($record->trashed() ? 'edit' : 'visual', ['record' => $record])
                : null)
            ->emptyStateHeading(__('No pages yet'))
            ->emptyStateDescription(__('Build a page from blocks: banners, text, images and calls to action. It can live at its own address or replace the home page.'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateActions([CreateAction::make()->label(__('New page'))])
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Page $record): string => Str::after($record->url(), url('/')) ?: '/'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (PageStatus $state): string => $state->label())
                    ->color(fn (PageStatus $state): string => $state === PageStatus::Published ? 'success' : 'gray'),
                TextColumn::make('replaces')
                    ->label(__('Replaces built-in page'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (Page $record): string => $record->slot()?->label() ?? '')
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label(__('Last updated'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(PageStatus::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label(__('Preview'))
                    ->tooltip(__('See the page as visitors will, drafts too, in a new tab'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Page $record): string => $record->previewUrl(), shouldOpenInNewTab: true)
                    ->hidden(fn (Page $record): bool => $record->trashed()),
                Action::make('visual')
                    ->label(__('Visual builder'))
                    ->tooltip(__('Change the page as it looks: click a block to edit it'))
                    ->icon(Heroicon::OutlinedPaintBrush)
                    ->url(fn (Page $record): string => PageResource::getUrl('visual', ['record' => $record]))
                    ->visible(fn (Page $record): bool => ! $record->trashed() && PageResource::canEdit($record)),
                EditAction::make()
                    ->label(__('Edit as a form'))
                    ->tooltip(__('The page\'s settings, and its blocks as a list')),
                Action::make('duplicate')
                    ->label(__('Duplicate'))
                    ->tooltip(__('Make a draft copy of this page to start a new one from'))
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->authorize('create')
                    ->hidden(fn (Page $record): bool => $record->trashed())
                    ->action(function (Page $record): void {
                        $copy = $record->replicate(['published_at']);
                        $copy->title = __(':title (copy)', ['title' => $record->title]);
                        $copy->slug = self::freeCopySlug($record->slug);
                        $copy->status = PageStatus::Draft;
                        $copy->replaces = null;
                        $copy->save();

                        Notification::make()->title(__('Page duplicated as a draft'))->success()->send();
                    }),
                DeleteAction::make(),
                RestoreAction::make(),
                // Only shown for pages in the bin; removes the page and its images for good.
                ForceDeleteAction::make()
                    ->label(__('Delete permanently'))
                    ->modalDescription(__('This page and its images are removed for good and can\'t be restored. Its URL becomes free to use again.')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->label(__('Delete permanently'))
                        ->modalDescription(__('These pages and their images are removed for good and can\'t be restored.')),
                ]),
            ]);
    }

    /**
     * "<slug>-copy-xxxx", with a suffix that no page (including one in the bin) or other part of the site uses yet.
     */
    private static function freeCopySlug(string $slug): string
    {
        $reserved = app(PageService::class)->reservedSlugs();

        do {
            $copy = Str::limit($slug, 85, '').'-copy-'.Str::lower(Str::random(4));
        } while (in_array($copy, $reserved, true) || Page::withTrashed()->where('slug', $copy)->exists());

        return $copy;
    }
}
