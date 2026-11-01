<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use ScBudgetWeb\SiteBuilder\Settings\AudioUploads;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Admin › Website › Media: what can be uploaded to pages. Its Audio tab sets the sound files Audio blocks take: the
 * types, from a fixed list of plain sound formats, and the largest file, shown against the server's own limit, which
 * the site can't raise by itself.
 *
 * @property-read Schema $form
 */
class ManageMedia extends Page
{
    protected string $view = 'site-builder::filament.pages.manage-media';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'media';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

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
        return __('What can be uploaded to your pages.');
    }

    public static function canAccess(): bool
    {
        $permission = config('site-builder.permissions.media', 'update settings');

        return is_string($permission) && (bool) auth()->user()?->can($permission);
    }

    public function mount(AudioUploads $audio): void
    {
        $this->form->fill([
            'audio_types' => $audio->types(),
            'audio_max_megabytes' => $audio->chosenMegabytes(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Tabs::make()
                        ->persistTabInQueryString('media-tab')
                        ->tabs([
                            Tab::make(__('Audio'))
                                ->icon(Heroicon::OutlinedMusicalNote)
                                ->schema($this->audioFields()),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Save changes'))
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(Settings $settings): void
    {
        $data = $this->form->getState();

        $settings->set([
            AudioUploads::TYPES_SETTING => array_values(array_intersect(array_keys(AudioUploads::TYPES), (array) ($data['audio_types'] ?? []))),
            AudioUploads::MEGABYTES_SETTING => (int) $data['audio_max_megabytes'],
        ]);

        Notification::make()
            ->title(__('Media settings saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<int, mixed>
     */
    private function audioFields(): array
    {
        $serverKilobytes = app(AudioUploads::class)->serverMaxKilobytes();

        return [
            CheckboxList::make('audio_types')
                ->label(__('File types Audio blocks take'))
                ->helperText(__('MP3 plays in every browser; M4A, OGG and WAV in every current one. FLAC and WebM don\'t play in every browser, so offer them only alongside a download.'))
                ->options(array_map(fn (array $type): string => $type[0], AudioUploads::TYPES))
                ->descriptions(array_map(fn (array $type): string => '.'.implode(', .', $type[1]), AudioUploads::TYPES))
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->required()
                ->minItems(1),
            TextInput::make('audio_max_megabytes')
                ->label(__('Largest sound file'))
                ->helperText(__('An hour\'s talk at 128 kbps is about 60 MB. Only page editors upload files, and each file stays on your site\'s storage until nothing uses it.'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(AudioUploads::MAX_MEGABYTES)
                ->suffix('MB')
                ->required()
                ->live(onBlur: true),
            Callout::make(__('Your server takes uploads up to :size', ['size' => $serverKilobytes === null ? __('any size') : AudioUploads::megabytes($serverKilobytes)]))
                ->description(fn (Get $get): string => $serverKilobytes !== null && is_numeric($get('audio_max_megabytes')) && (int) $get('audio_max_megabytes') * 1024 > $serverKilobytes
                    ? __('That\'s less than the size above, so Audio blocks take files up to :size until your host raises PHP\'s upload_max_filesize and post_max_size (and the web server\'s own limit, such as nginx\'s client_max_body_size).', ['size' => AudioUploads::megabytes($serverKilobytes)])
                    : __('Set by PHP\'s upload_max_filesize and post_max_size. If large files still fail to upload, ask your host about the web server\'s own limit too (such as nginx\'s client_max_body_size).'))
                ->color(fn (Get $get): string => $serverKilobytes !== null && is_numeric($get('audio_max_megabytes')) && (int) $get('audio_max_megabytes') * 1024 > $serverKilobytes ? 'warning' : 'info')
                ->icon(Heroicon::OutlinedServer),
        ];
    }
}
