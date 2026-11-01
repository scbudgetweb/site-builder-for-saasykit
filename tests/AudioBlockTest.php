<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Settings\AudioUploads;
use ScBudgetWeb\SiteBuilder\Settings\Settings;

/**
 * The Audio block: an uploaded MP3, M4A, OGG or WAV file in a card, played by the browser's own player, which loads
 * nothing until it's played.
 */
class AudioBlockTest extends PageTestCase
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

        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
    }

    /**
     * Opens the page's first block in its panel and uploads $file as its sound file.
     */
    private function uploadToAudioBlock(Page $page, UploadedFile $file): Testable
    {
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        return $component->callAction(
            TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]),
            ['audio' => $file, 'title' => 'Welcome talk'],
        );
    }

    public function test_an_mp3_can_be_uploaded(): void
    {
        $page = $this->page(['blocks' => [self::block('audio', ['title' => 'Welcome talk'])]]);

        $this->uploadToAudioBlock($page, UploadedFile::fake()->create('welcome.mp3', 200, 'audio/mpeg'))
            ->assertHasNoFormErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $path = (string) Page::query()->find($page->id)->blockList()[0]['data']['audio'];
        $this->assertStringStartsWith('site-builder/pages/', $path);
        $this->assertStringEndsWith('.mp3', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_other_files_and_files_too_large_are_refused(): void
    {
        $page = $this->page(['blocks' => [self::block('audio', ['title' => 'Welcome talk'])]]);

        $this->uploadToAudioBlock($page, UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'))->assertHasActionErrors(['audio']);

        app(Settings::class)->set([AudioUploads::MEGABYTES_SETTING => 1]);

        $this->uploadToAudioBlock($page, UploadedFile::fake()->create('long.mp3', 1100, 'audio/mpeg'))->assertHasActionErrors(['audio']);

        $this->assertSame([], Storage::disk('public')->allFiles('site-builder/pages'));
    }

    public function test_only_the_types_chosen_in_media_are_taken(): void
    {
        $page = $this->page(['blocks' => [self::block('audio', ['title' => 'Welcome talk'])]]);

        $this->uploadToAudioBlock($page, UploadedFile::fake()->create('welcome.flac', 20, 'audio/flac'))->assertHasActionErrors(['audio']);

        app(Settings::class)->set([AudioUploads::TYPES_SETTING => ['flac']]);

        $this->uploadToAudioBlock($page, UploadedFile::fake()->create('welcome.flac', 20, 'audio/flac'))->assertHasNoActionErrors();
        $this->uploadToAudioBlock($page, UploadedFile::fake()->create('welcome.mp3', 20, 'audio/mpeg'))->assertHasActionErrors(['audio']);
    }

    public function test_the_player_loads_nothing_until_played(): void
    {
        $this->page(['blocks' => [self::block('audio', [
            'audio' => 'site-builder/pages/welcome.mp3',
            'title' => 'Welcome talk',
            'artist' => 'Sam Taylor',
            'description' => 'A short welcome.',
            'cover' => 'site-builder/pages/cover.jpg',
        ])]]);

        $this->get('/about-us')
            ->assertOk()
            ->assertSee('<audio controls preload="none" src="/storage/site-builder/pages/welcome.mp3"', false)
            ->assertSee('aria-label="Welcome talk, by Sam Taylor"', false)
            ->assertSee('controlslist="nodownload"', false)
            ->assertSee('/storage/site-builder/pages/cover.jpg', false)
            ->assertSeeInOrder(['sb-item-title', 'Welcome talk', 'sb-item-meta', 'Sam Taylor', 'sb-item-text', 'A short welcome.'], false)
            ->assertDontSee(' download ', false);
    }

    public function test_a_download_link_can_be_offered(): void
    {
        $this->page(['blocks' => [self::block('audio', ['audio' => 'site-builder/pages/welcome.mp3', 'title' => 'Welcome talk', 'download' => true])]]);

        $this->get('/about-us')
            ->assertDontSee('controlslist="nodownload"', false)
            ->assertSee('href="/storage/site-builder/pages/welcome.mp3" download', false);
    }

    public function test_only_page_uploads_are_played(): void
    {
        $this->page(['blocks' => [self::block('audio', ['audio' => 'site-builder/branding/secret.mp3', 'title' => 'Not a page upload'])]]);

        $this->get('/about-us')
            ->assertOk()
            ->assertDontSee('<audio', false)
            ->assertDontSee('secret.mp3');
    }
}
