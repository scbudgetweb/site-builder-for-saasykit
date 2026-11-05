<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use ScBudgetWeb\SiteBuilder\Models\ContactMessage;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Admin › Website › Contact messages: what visitors sent through Contact form blocks, newest first, with the unread
 * count beside it in the menu. Opening one marks it read. Messages can't be created or changed here, only read and
 * deleted. Access: config site-builder.permissions.contact_messages.
 */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $slug = 'site-builder/contact-messages';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        $permission = config('site-builder.permissions.contact_messages', 'update settings');

        return is_string($permission) && (bool) auth()->user()?->can($permission);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDeleteAny(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return app(SiteBuilder::class)->navigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Contact messages');
    }

    public static function getModelLabel(): string
    {
        return __('contact message');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contact messages');
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = ContactMessage::query()->whereNull('read_at')->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Unread');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('name')->label(__('Name'))->placeholder('-'),
                    TextEntry::make('email')
                        ->label(__('Email'))
                        ->url(fn (ContactMessage $record): string => 'mailto:'.$record->email)
                        ->copyable(),
                    TextEntry::make('phone')
                        ->label(__('Phone'))
                        ->placeholder('-')
                        ->url(fn (ContactMessage $record): ?string => filled($record->phone) ? 'tel:'.preg_replace('/[^0-9+]/', '', $record->phone) : null),
                    TextEntry::make('subject')->label(__('Subject'))->placeholder('-'),
                    TextEntry::make('message')
                        ->label(__('Message'))
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'whitespace-pre-line'])
                        ->visible(fn (ContactMessage $record): bool => ! is_array($record->fields)),
                    // Every answer, in the form's order (forms with fields of their own, 1.7.2 on).
                    TextEntry::make('answers')
                        ->label(__('Answers'))
                        ->state(fn (ContactMessage $record): string => self::answersList($record))
                        ->html()
                        ->visible(fn (ContactMessage $record): bool => is_array($record->fields))
                        ->columnSpanFull(),
                    TextEntry::make('form_name')->label(__('Form'))->placeholder('-'),
                    TextEntry::make('page_url')
                        ->label(__('Sent from'))
                        ->placeholder('-')
                        ->url(fn (ContactMessage $record): ?string => is_string($record->page_url) && preg_match('/^https?:\/\//i', $record->page_url) === 1 ? $record->page_url : null, shouldOpenInNewTab: true),
                    TextEntry::make('created_at')->label(__('Received'))->dateTime(),
                ]),
        ]);
    }

    /**
     * A message's answers as a list of labels and answers, escaped. A blank answer shows as "-".
     */
    public static function answersList(ContactMessage $record): string
    {
        $items = array_map(fn (array $answer): string => '<div class="py-1.5"><dt class="text-sm font-medium opacity-70">'.e($answer['label']).'</dt>'
            .'<dd class="whitespace-pre-line">'.e($answer['value'] !== '' ? $answer['value'] : '-').'</dd></div>', $record->answers());

        return '<dl>'.implode('', $items).'</dl>';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('read_at')
                    ->label(__('Read'))
                    ->state(fn (ContactMessage $record): bool => $record->isRead())
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedEnvelopeOpen)
                    ->falseIcon(Heroicon::Envelope)
                    ->trueColor('gray')
                    ->falseColor('primary')
                    ->tooltip(fn (ContactMessage $record): string => $record->isRead() ? __('Read') : __('Unread')),
                TextColumn::make('name')
                    ->label(__('From'))
                    ->state(fn (ContactMessage $record): string => $record->name ?: $record->email)
                    ->description(fn (ContactMessage $record): ?string => $record->name ? $record->email : null)
                    ->weight(fn (ContactMessage $record): ?string => $record->isRead() ? null : 'bold')
                    ->searchable(['name', 'email']),
                TextColumn::make('message')
                    ->label(__('Message'))
                    ->state(fn (ContactMessage $record): string => $record->subject ?: $record->message)
                    ->limit(80)
                    ->wrap()
                    ->searchable(['subject', 'message']),
                TextColumn::make('form_name')
                    ->label(__('Form'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Received'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('read_at')
                    ->label(__('Read'))
                    ->nullable()
                    ->trueLabel(__('Read'))
                    ->falseLabel(__('Unread')),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('markUnread')
                    ->label(__('Mark as unread'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->visible(fn (ContactMessage $record): bool => $record->isRead())
                    ->action(fn (ContactMessage $record) => $record->update(['read_at' => null])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No messages yet'))
            ->emptyStateDescription(__('Messages sent through a Contact form block on your pages show here.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
