<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use ScBudgetWeb\SiteBuilder\Filament\Pages\Media\LibraryTable;
use ScBudgetWeb\SiteBuilder\Filament\Pages\Media\SettingsFields;
use ScBudgetWeb\SiteBuilder\Settings\AudioUploads;
use ScBudgetWeb\SiteBuilder\Settings\ImageUploads;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Admin › Website › Media, in tabs, as the tenant page's relation managers are:
 * - Library: every image and sound file uploaded to pages (LibraryTable), for page editors
 *   (site-builder.permissions.media_library);
 * - Images and Audio: what pages can upload (SettingsFields), for administrators (site-builder.permissions.media).
 * The tab is in the address (?tab=images), so a link or a refresh opens it again.
 *
 * @property-read Schema $imagesForm
 * @property-read Schema $audioForm
 */
class ManageMedia extends Page implements HasTable
{
    use InteractsWithTable;

    public const TABS = ['library', 'images', 'audio'];

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'media';

    #[Url(as: 'tab')]
    public ?string $activeTab = 'library';

    /** The library as "grid" (thumbnails) or "list" (a table with sizes and dates), remembered for the session. */
    #[Session(key: 'site-builder.media-layout')]
    public string $libraryLayout = 'grid';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $imageData = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $audioData = [];

    public static function getNavigationGroup(): ?string
    {
        return app(SiteBuilder::class)->navigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Media');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Media');
    }

    public function getSubheading(): ?string
    {
        return $this->activeTab === 'library'
            ? __('Images and sound files uploaded to your pages. A file stays here until you delete it, and one that\'s in use can\'t be deleted.')
            : __('What can be uploaded to your pages.');
    }

    public static function canAccess(): bool
    {
        return static::canSeeLibrary() || static::canChangeSettings();
    }

    public static function canSeeLibrary(): bool
    {
        return self::userCan(config('site-builder.permissions.media_library', 'update pages'));
    }

    public static function canChangeSettings(): bool
    {
        return self::userCan(config('site-builder.permissions.media', 'update settings'));
    }

    public function mount(ImageUploads $images, AudioUploads $audio, Settings $settings): void
    {
        $allowed = $this->allowedTabs();

        if (! in_array($this->activeTab, $allowed, true)) {
            $this->activeTab = $allowed[0] ?? 'library';
        }

        if (static::canChangeSettings()) {
            $this->imagesForm->fill(SettingsFields::imageState($images, $settings));
            $this->audioForm->fill(SettingsFields::audioState($audio));
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->key('mediaTabs')
                ->livewireProperty('activeTab')
                ->contained(false)
                ->tabs([
                    'library' => Tab::make(__('Library'))
                        ->icon(Heroicon::OutlinedPhoto)
                        ->visible(fn (): bool => static::canSeeLibrary())
                        ->schema([EmbeddedTable::make()]),
                    'images' => Tab::make(__('Images'))
                        ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                        ->visible(fn (): bool => static::canChangeSettings())
                        ->schema([EmbeddedSchema::make('imagesForm')]),
                    'audio' => Tab::make(__('Audio'))
                        ->icon(Heroicon::OutlinedMusicalNote)
                        ->visible(fn (): bool => static::canChangeSettings())
                        ->schema([EmbeddedSchema::make('audioForm')]),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return LibraryTable::configure($table, $this);
    }

    public function imagesForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([Section::make()->schema(SettingsFields::images())])
                    ->livewireSubmitHandler('saveImages')
                    ->footer([Actions::make([Action::make('saveImages')->label(__('Save changes'))->submit('saveImages')])]),
            ])
            ->statePath('imageData');
    }

    public function audioForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([Section::make()->schema(SettingsFields::audio())])
                    ->livewireSubmitHandler('saveAudio')
                    ->footer([Actions::make([Action::make('saveAudio')->label(__('Save changes'))->submit('saveAudio')])]),
            ])
            ->statePath('audioData');
    }

    public function saveImages(Settings $settings): void
    {
        abort_unless(static::canChangeSettings(), 403);

        $settings->set(SettingsFields::imageSettings($this->imagesForm->getState()));
        $this->savedNotification();
    }

    public function saveAudio(Settings $settings): void
    {
        abort_unless(static::canChangeSettings(), 403);

        $settings->set(SettingsFields::audioSettings($this->audioForm->getState()));
        $this->savedNotification();
    }

    protected function getHeaderActions(): array
    {
        $isLibrary = fn (): bool => $this->activeTab === 'library' && static::canSeeLibrary();

        return [
            Action::make('showList')
                ->label(__('List'))
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->visible(fn (): bool => $isLibrary() && $this->libraryLayout !== 'list')
                ->action(fn () => $this->useLayout('list')),
            Action::make('showGrid')
                ->label(__('Grid'))
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->visible(fn (): bool => $isLibrary() && $this->libraryLayout === 'list')
                ->action(fn () => $this->useLayout('grid')),
            LibraryTable::uploadAction()->visible($isLibrary),
        ];
    }

    public function useLayout(string $layout): void
    {
        $this->libraryLayout = $layout === 'list' ? 'list' : 'grid';
        $this->resetTable();
    }

    /**
     * @return array<int, string>
     */
    private function allowedTabs(): array
    {
        return array_values(array_filter(self::TABS, fn (string $tab): bool => $tab === 'library' ? static::canSeeLibrary() : static::canChangeSettings()));
    }

    private function savedNotification(): void
    {
        Notification::make()
            ->title(__('Media settings saved'))
            ->success()
            ->send();
    }

    private static function userCan(mixed $permission): bool
    {
        return is_string($permission) && (bool) auth()->user()?->can($permission);
    }
}
