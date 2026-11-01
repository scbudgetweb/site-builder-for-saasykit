<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageMedia;
use ScBudgetWeb\SiteBuilder\Settings\AudioUploads;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\Support\LivewireLimits;

/**
 * Admin › Website › Media: the sound files Audio blocks take (types and largest size), held to the server's own limit,
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

    public function test_people_who_can_update_settings_can_open_it_in_the_website_group(): void
    {
        $this->actingAs($this->user());
        $this->assertTrue(ManageMedia::canAccess());
        $this->assertSame('Website', ManageMedia::getNavigationGroup());

        $this->actingAs($this->user('someone@example.com'));
        $this->assertFalse(ManageMedia::canAccess());
    }

    public function test_it_opens_with_the_standard_choices_and_saves_new_ones(): void
    {
        $this->serverTakes(null);
        $this->actingAs($this->user());

        Livewire::test(ManageMedia::class)
            ->assertSee(['Audio', 'File types Audio blocks take', 'Largest sound file', 'Your server takes uploads up to any size'])
            ->assertSet('data.audio_types', AudioUploads::DEFAULT_TYPES)
            ->assertSet('data.audio_max_megabytes', AudioUploads::DEFAULT_MEGABYTES)
            ->fillForm(['audio_types' => ['flac', 'mp3'], 'audio_max_megabytes' => 500])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Media settings saved');

        $audio = app(AudioUploads::class);
        $this->assertSame(['mp3', 'flac'], $audio->types());
        $this->assertSame(500, $audio->chosenMegabytes());
        $this->assertSame(500 * 1024, $audio->maxKilobytes());
        $this->assertSame('MP3 or FLAC', $audio->typeLabels());
        $this->assertContains('audio/flac', $audio->mimeTypes());
        $this->assertNotContains('audio/wav', $audio->mimeTypes());
    }

    public function test_it_needs_a_type_and_a_sensible_size(): void
    {
        $this->actingAs($this->user());

        Livewire::test(ManageMedia::class)
            ->fillForm(['audio_types' => [], 'audio_max_megabytes' => 0])
            ->call('save')
            ->assertHasFormErrors(['audio_types', 'audio_max_megabytes']);

        Livewire::test(ManageMedia::class)
            ->fillForm(['audio_max_megabytes' => AudioUploads::MAX_MEGABYTES + 1])
            ->call('save')
            ->assertHasFormErrors(['audio_max_megabytes']);
    }

    public function test_the_server_limit_wins_when_its_lower_and_the_page_says_so(): void
    {
        $this->serverTakes(64 * 1024);
        $this->actingAs($this->user());
        app(Settings::class)->set([AudioUploads::MEGABYTES_SETTING => 200]);

        $audio = app(AudioUploads::class);
        $this->assertSame(64 * 1024, $audio->maxKilobytes());
        $this->assertTrue($audio->isLimitedByServer());

        Livewire::test(ManageMedia::class)
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
