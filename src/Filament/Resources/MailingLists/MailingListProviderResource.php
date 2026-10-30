<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\MailingLists;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\MailingLists\Pages\EditMailingListProvider;
use ScBudgetWeb\SiteBuilder\Filament\Resources\MailingLists\Pages\ListMailingListProviders;
use ScBudgetWeb\SiteBuilder\MailingLists\MailingListException;
use ScBudgetWeb\SiteBuilder\MailingLists\MailingListService;
use ScBudgetWeb\SiteBuilder\Models\MailingListProvider;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Admin › Website › Mailing lists: connect a mailing list service (Brevo first) and choose the list sign-up forms add
 * people to. The API key lives only here (encrypted), never in a block. The rows are fixed (one per service), so
 * there's no create or delete. Access: config site-builder.permissions.mailing_lists.
 */
class MailingListProviderResource extends Resource
{
    protected static ?string $model = MailingListProvider::class;

    protected static ?string $slug = 'site-builder/mailing-lists';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        $permission = config('site-builder.permissions.mailing_lists', 'update settings');

        return is_string($permission) && (bool) auth()->user()?->can($permission);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return app(SiteBuilder::class)->navigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Mailing lists');
    }

    public static function getModelLabel(): string
    {
        return __('mailing list service');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Mailing lists');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Connection'))
                ->description(fn (MailingListProvider $record): HtmlString => self::connectionHelp($record))
                ->columnSpanFull()
                ->schema([
                    TextInput::make('api_key')
                        ->label(__('API key'))
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        // The saved key is never sent to the browser. Blank keeps it.
                        ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->required(fn (MailingListProvider $record): bool => ! $record->isConnected())
                        ->placeholder(fn (MailingListProvider $record): ?string => $record->isConnected() ? __('Saved. Leave blank to keep it.') : null)
                        ->rule(fn (MailingListProvider $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if (blank($value)) {
                                return;
                            }

                            try {
                                app(MailingListService::class)->client($record, (string) $value)->checkConnection();
                            } catch (MailingListException $exception) {
                                $fail(__('That API key didn\'t work. :error', ['error' => $exception->getMessage()]));
                            }
                        }),
                ]),
            Section::make(__('Sign-up list'))
                ->description(__('People who sign up on the website are added to this list, unless a Mailing list block chooses another.'))
                ->columnSpanFull()
                ->visible(fn (MailingListProvider $record): bool => $record->isConnected())
                ->schema([
                    Select::make('list_id')
                        ->label(__('Default list'))
                        ->options(fn (MailingListProvider $record): array => app(MailingListService::class)->lists($record))
                        ->searchable()
                        ->helperText(fn (MailingListProvider $record): ?string => app(MailingListService::class)->lists($record) === []
                            ? __('No lists found. Make a list in :provider, then click "Refresh lists".', ['provider' => $record->name])
                            : null)
                        ->hintAction(
                            Action::make('refreshLists')
                                ->label(__('Refresh lists'))
                                ->icon(Heroicon::OutlinedArrowPath)
                                ->action(function (MailingListProvider $record): void {
                                    app(MailingListService::class)->forgetLists($record);
                                    Notification::make()->title(__('Lists refreshed'))->success()->send();
                                }),
                        ),
                    Toggle::make('is_active')
                        ->label(__('Use for sign-up forms'))
                        ->helperText(__('One service at a time: turning this on turns the others off. Forms only show once a list is chosen.')),
                ]),
            Section::make(__('Double opt-in'))
                ->description(__('The service emails new subscribers a link to confirm, and only adds them once they click it. Best practice, and expected in some countries.'))
                ->columnSpanFull()
                ->collapsible()
                ->collapsed(fn (MailingListProvider $record): bool => ! $record->double_opt_in)
                ->visible(fn (MailingListProvider $record): bool => $record->isConnected() && $record->slug === MailingListProvider::BREVO)
                ->schema([
                    Toggle::make('double_opt_in')
                        ->label(__('Ask new subscribers to confirm'))
                        ->live(),
                    TextInput::make('double_opt_in_template_id')
                        ->label(__('Confirmation email template ID'))
                        ->hintIcon(PageBlock::HELP_ICON, __('In Brevo: Campaigns › Templates, a template with the {{ doubleoptin }} link (Brevo has a "Default template - Double opt-in"). Its ID is the number in its row.'))
                        ->numeric()
                        ->required(fn (Get $get): bool => (bool) $get('double_opt_in'))
                        ->visible(fn (Get $get): bool => (bool) $get('double_opt_in')),
                    TextInput::make('double_opt_in_redirect_url')
                        ->label(__('Page after confirming'))
                        ->hintIcon(PageBlock::HELP_ICON, __('Where the confirmation link takes them, e.g. a "You\'re subscribed" page on this site.'))
                        ->url()
                        ->maxLength(500)
                        ->placeholder(url('/'))
                        ->required(fn (Get $get): bool => (bool) $get('double_opt_in'))
                        ->visible(fn (Get $get): bool => (bool) $get('double_opt_in')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // With its logo, as SaaSykit shows its payment and email providers.
                TextColumn::make('name')
                    ->label(__('Service'))
                    ->formatStateUsing(fn (string $state, MailingListProvider $record): HtmlString => new HtmlString(
                        '<div class="flex items-center gap-2">'
                        .($record->iconUrl() ? '<img src="'.$record->iconUrl().'" alt="" class="h-6 w-6">' : '')
                        .e($state).'</div>'
                    )),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->state(fn (MailingListProvider $record): string => match (true) {
                        ! $record->isConnected() => __('Not connected'),
                        blank($record->list_id) => __('No list chosen'),
                        default => __('List: :list', ['list' => $record->list_name ?? $record->list_id]),
                    })
                    ->badge()
                    ->color(fn (MailingListProvider $record): string => match (true) {
                        ! $record->isConnected() => 'gray',
                        blank($record->list_id) => 'warning',
                        default => 'success',
                    }),
                ToggleColumn::make('is_active')
                    ->label(__('In use'))
                    ->disabled(fn (MailingListProvider $record): bool => ! $record->isConnected() || blank($record->list_id))
                    ->tooltip(fn (MailingListProvider $record): ?string => ! $record->isConnected() || blank($record->list_id)
                        ? __('Add an API key and choose a list first.')
                        : null),
            ])
            ->recordActions([EditAction::make()->label(__('Set up'))])
            ->defaultSort('name')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailingListProviders::route('/'),
            'edit' => EditMailingListProvider::route('/{record}/edit'),
        ];
    }

    private static function connectionHelp(MailingListProvider $record): HtmlString
    {
        if ($record->slug === MailingListProvider::BREVO) {
            return new HtmlString(__('In Brevo, go to <a href=":url" target="_blank" rel="noopener" class="underline">SMTP & API › API keys</a> and generate an API key. (It\'s the API key, not the SMTP key.) It\'s checked with Brevo when you save, and stored encrypted.', [
                'url' => 'https://app.brevo.com/settings/keys/api',
            ]));
        }

        return new HtmlString(e(__('The key is checked when you save, and stored encrypted.')));
    }
}
