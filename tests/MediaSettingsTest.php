<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageMedia;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Settings\AudioUploads;
use ScBudgetWeb\SiteBuilder\Settings\ImageUploads;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\Support\LivewireLimits;

/**
 * Admin › Website › Media settings: what image fields and Audio blocks take (types and largest size), held to the server's own limit,
 * with Livewire's upload limit raised to match.
 */
class MediaSettingsTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::define('update settings', fn ($user): bool => $user->getAttribute('email') === 'admin@example.com');
    }

    /**
     * Audio uploads on a server that takes uploads up to $serverKilobytes (null: any size).
     */
    private function serverTakes(?int $serverKilobytes): void
    {
        $this->app->bind(AudioUploads::class, fn ($app) => new class($app->make(Settings::class), $serverKilobytes) extends AudioUploads
        {
            public function __construct(Settings $settings, private ?int $serverKilobytes)
            {
                parent::__construct($settings);
            }

            protected function phpUploadLimit(): int|float
            {
                return $this->serverKilobytes === null ? 0 : $this->serverKilobytes * 1024;
            }
        });
    }

    public function test_settings_tabs_are_for_people_who_can_update_settings(): void
    {
        $this->actingAs($this->user());
        $this->assertTrue(ManageMedia::canChangeSettings());
        $this->assertSame('Website', ManageMedia::getNavigationGroup());

        Livewire::withQueryParams(['tab' => 'audio'])->test(ManageMedia::class)
            ->assertSet('activeTab', 'audio')
            ->assertSee(['Library', 'Images', 'Audio', 'Largest sound file'])
            ->assertActionHidden('upload');

        // Settings only (not a page editor): the Library tab isn't there, so it opens on Images.
        Gate::define('update pages', fn (): bool => false);
        Livewire::withQueryParams(['tab' => 'library'])->test(ManageMedia::class)
            ->assertSet('activeTab', 'images')
            ->assertSee('Largest image')
            ->assertActionHidden('upload');

        $this->actingAs($this->user('someone@example.com'));
        $this->assertFalse(ManageMedia::canAccess());
    }

    public function test_it_opens_with_the_standard_choices_and_saves_new_ones(): void
    {
        $this->serverTakes(null);
        $this->actingAs($this->user());

        Livewire::withQueryParams(['tab' => 'audio'])->test(ManageMedia::class)
            ->assertSee(['Audio', 'File types Audio blocks take', 'Largest sound file', 'Your server takes uploads up to any size'])
            ->assertSet('audioData.audio_types', AudioUploads::DEFAULT_TYPES)
            ->assertSet('audioData.audio_max_megabytes', AudioUploads::DEFAULT_MEGABYTES)
            ->fillForm(['audio_types' => ['flac', 'mp3'], 'audio_max_megabytes' => 500], 'audioForm')
            ->call('saveAudio')
            ->assertHasNoFormErrors([], 'audioForm')
            ->assertNotified('Media settings saved');

        $audio = app(AudioUploads::class);
        $this->assertSame(['mp3', 'flac'], $audio->types());
        $this->assertSame(500, $audio->chosenMegabytes());
        $this->assertSame(500 * 1024, $audio->maxKilobytes());
        $this->assertSame('MP3 or FLAC', $audio->typeLabels());
        $this->assertContains('audio/flac', $audio->mimeTypes());
        $this->assertNotContains('audio/wav', $audio->mimeTypes());
    }

    public function test_image_settings_are_saved_and_used(): void
    {
        $this->serverTakes(null);
        $this->actingAs($this->user());

        Livewire::withQueryParams(['tab' => 'images'])->test(ManageMedia::class)
            ->assertSee(['Images', 'File types image fields take', 'Shrink large photos', 'Remove location data'])
            ->assertSet('imageData.image_types', ImageUploads::DEFAULT_TYPES)
            ->assertSet('imageData.image_max_megabytes', 2)
            ->fillForm(['image_types' => ['jpg', 'png'], 'image_max_megabytes' => 10, 'image_max_width' => '2560', 'image_strip_location' => true], 'imagesForm')
            ->call('saveImages')
            ->assertHasNoFormErrors([], 'imagesForm');

        $images = app(ImageUploads::class);
        $this->assertSame(['jpg', 'png'], $images->types());
        $this->assertFalse($images->allowsSvg());
        $this->assertSame(['image/jpeg', 'image/png'], $images->mimeTypesFor(true));
        $this->assertSame(10 * 1024, $images->maxKilobytes());
        $this->assertSame(2560, $images->maxWidth());
        $this->assertTrue($images->stripsLocation());
        $this->assertSame('JPG or PNG', $images->typeLabelsFor(true));

        Livewire::withQueryParams(['tab' => 'images'])->test(ManageMedia::class)
            ->assertSet('imageData.image_max_width', '2560')
            ->fillForm(['image_max_width' => '', 'image_strip_location' => false], 'imagesForm')
            ->call('saveImages');

        app()->forgetScopedInstances();
        $this->assertNull(app(ImageUploads::class)->maxWidth());
        $this->assertFalse(app(ImageUploads::class)->stripsLocation());
    }

    public function test_an_svg_or_a_large_image_is_refused_as_media_settings_say(): void
    {
        $this->actingAs($this->user());
        Storage::fake('public');
        $page = $this->page(['blocks' => [self::block('image', ['alt' => 'Village Hall'])]]);
        $upload = function (UploadedFile $file) use ($page): Testable {
            $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

            return $component->callAction(
                TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]),
                ['image' => $file, 'alt' => 'Village Hall'],
            );
        };
        $svg = fn (): UploadedFile => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><rect width="1" height="1"/></svg>');

        $upload($svg())->assertHasNoActionErrors();
        $upload(UploadedFile::fake()->image('big.jpg')->size(3000))->assertHasActionErrors(['image']);

        app(Settings::class)->set([ImageUploads::TYPES_SETTING => ['jpg'], ImageUploads::MEGABYTES_SETTING => 5]);

        $upload($svg())->assertHasActionErrors(['image']);
        $upload(UploadedFile::fake()->image('big.jpg')->size(3000))->assertHasNoActionErrors();
    }

    public function test_it_needs_a_type_and_a_sensible_size(): void
    {
        $this->actingAs($this->user());

        Livewire::withQueryParams(['tab' => 'audio'])->test(ManageMedia::class)
            ->fillForm(['audio_types' => [], 'audio_max_megabytes' => 0], 'audioForm')
            ->call('saveAudio')
            ->assertHasFormErrors(['audio_types', 'audio_max_megabytes'], 'audioForm');

        Livewire::withQueryParams(['tab' => 'audio'])->test(ManageMedia::class)
            ->fillForm(['audio_max_megabytes' => AudioUploads::MAX_MEGABYTES + 1], 'audioForm')
            ->call('saveAudio')
            ->assertHasFormErrors(['audio_max_megabytes'], 'audioForm');
    }

    public function test_the_server_limit_wins_when_its_lower_and_the_page_says_so(): void
    {
        $this->serverTakes(64 * 1024);
        $this->actingAs($this->user());
        app(Settings::class)->set([AudioUploads::MEGABYTES_SETTING => 200]);

        $audio = app(AudioUploads::class);
        $this->assertSame(64 * 1024, $audio->maxKilobytes());
        $this->assertTrue($audio->isLimitedByServer());

        Livewire::withQueryParams(['tab' => 'audio'])->test(ManageMedia::class)
            ->assertSee('Your server takes uploads up to 64 MB')
            ->assertSee('Audio blocks take files up to 64 MB until your host raises');
    }

    public function test_saved_settings_that_no_longer_make_sense_fall_back(): void
    {
        $this->serverTakes(null);
        app(Settings::class)->set([AudioUploads::TYPES_SETTING => ['exe', 'html'], AudioUploads::MEGABYTES_SETTING => 'lots']);

        $audio = app(AudioUploads::class);
        $this->assertSame(AudioUploads::DEFAULT_TYPES, $audio->types());
        $this->assertSame(AudioUploads::DEFAULT_MEGABYTES, $audio->chosenMegabytes());
    }

    public function test_photo_options_are_off_where_laravels_image_tools_are_missing(): void
    {
        $this->actingAs($this->user());
        app(Settings::class)->set([ImageUploads::WIDTH_SETTING => 1600, ImageUploads::STRIP_LOCATION_SETTING => true]);
        $this->app->bind(ImageUploads::class, fn ($app) => new class($app->make(Settings::class)) extends ImageUploads
        {
            public function canProcess(): bool
            {
                return false;
            }
        });

        $this->assertNull(app(ImageUploads::class)->maxWidth());
        $this->assertFalse(app(ImageUploads::class)->stripsLocation());

        Livewire::withQueryParams(['tab' => 'images'])->test(ManageMedia::class)
            ->assertSee('These need Laravel 13 and Intervention Image 4')
            ->assertFormFieldDisabled('image_max_width', 'imagesForm')
            ->assertFormFieldDisabled('image_strip_location', 'imagesForm');
    }

    public function test_livewires_upload_limit_is_raised_but_never_lowered(): void
    {
        config(['livewire.temporary_file_upload.rules' => null, 'livewire.temporary_file_upload.max_upload_time' => null]);
        LivewireLimits::raiseUploadSize(100 * 1024);
        $this->assertSame(['required', 'file', 'max:102400'], config('livewire.temporary_file_upload.rules'));
        $this->assertSame(30, config('livewire.temporary_file_upload.max_upload_time'));

        config(['livewire.temporary_file_upload.rules' => 'required|file|max:512000', 'livewire.temporary_file_upload.max_upload_time' => 60]);
        LivewireLimits::raiseUploadSize(100 * 1024);
        $this->assertSame('required|file|max:512000', config('livewire.temporary_file_upload.rules'));
        $this->assertSame(60, config('livewire.temporary_file_upload.max_upload_time'));

        config(['livewire.temporary_file_upload.rules' => null, 'site-builder.livewire_upload_size' => false]);
        LivewireLimits::raiseUploadSize(100 * 1024);
        $this->assertNull(config('livewire.temporary_file_upload.rules'));
    }
}
