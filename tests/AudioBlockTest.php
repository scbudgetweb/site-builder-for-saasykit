<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\Core\AudioBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Settings\AudioUploads;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

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

    public function test_soundcloud_and_spotify_links_make_their_players(): void
    {
        $this->assertSame(
            ['provider' => 'SoundCloud', 'url' => 'https://w.soundcloud.com/player/?url=https%3A%2F%2Fsoundcloud.com%2Fvillage-hall%2Fwelcome-talk&visual=false&show_comments=false&show_reposts=false', 'playUrl' => 'https://w.soundcloud.com/player/?url=https%3A%2F%2Fsoundcloud.com%2Fvillage-hall%2Fwelcome-talk&visual=false&show_comments=false&show_reposts=false&auto_play=true', 'height' => 166],
            AudioBlock::embed('soundcloud', 'https://soundcloud.com/village-hall/welcome-talk?si=abc'),
        );
        $this->assertSame(450, AudioBlock::embed('soundcloud', 'https://soundcloud.com/village-hall/sets/talks')['height']);
        $this->assertSame(
            ['provider' => 'Spotify', 'url' => 'https://open.spotify.com/embed/episode/4rOoJ6Egrf8K2IrywzwOMk', 'playUrl' => 'https://open.spotify.com/embed/episode/4rOoJ6Egrf8K2IrywzwOMk', 'height' => 152],
            AudioBlock::embed('spotify', 'https://open.spotify.com/intl-en/episode/4rOoJ6Egrf8K2IrywzwOMk?si=xyz'),
        );
        $this->assertSame(352, AudioBlock::embed('spotify', 'https://open.spotify.com/show/4rOoJ6Egrf8K2IrywzwOMk')['height']);

        foreach ([
            ['soundcloud', 'https://evil.example.com/village-hall/welcome-talk'],
            ['soundcloud', 'https://soundcloud.com/village-hall'],
            ['soundcloud', 'https://soundcloud.com.evil.example/a/b'],
            ['spotify', 'https://open.spotify.com/user/4rOoJ6Egrf8K2IrywzwOMk'],
            ['spotify', 'https://soundcloud.com/village-hall/welcome-talk'],
            ['upload', 'https://open.spotify.com/show/4rOoJ6Egrf8K2IrywzwOMk'],
            ['spotify', ['not a string']],
        ] as [$source, $link]) {
            $this->assertNull(AudioBlock::embed($source, $link), json_encode([$source, $link]));
        }
    }

    public function test_an_embedded_player_is_click_to_play_until_consent_and_never_cached(): void
    {
        $data = ['source' => 'spotify', 'embed_url' => 'https://open.spotify.com/episode/4rOoJ6Egrf8K2IrywzwOMk', 'title' => 'Welcome talk'];
        $this->page(['blocks' => [self::block('audio', $data)]]);

        $this->assertFalse(AudioBlock::isCacheable($data));
        $this->assertTrue(AudioBlock::isCacheable(['audio' => 'site-builder/pages/welcome.mp3']));

        $this->get('/about-us')
            ->assertSee('<template x-if="playing">', false)
            ->assertSee('Plays from Spotify, which may set cookies.')
            ->assertSee('aria-label="Play Welcome talk from Spotify"', false)
            ->assertDontSee('<audio', false);

        app(SiteBuilder::class)->consentChecker(fn (string $category): bool => $category === 'marketing');

        $this->get('/about-us')
            ->assertDontSee('<template x-if="playing">', false)
            ->assertSee('<iframe src="https://open.spotify.com/embed/episode/4rOoJ6Egrf8K2IrywzwOMk" title="Welcome talk" height="152"', false);
    }

    public function test_a_link_for_the_wrong_service_is_refused_and_no_upload_is_needed(): void
    {
        $page = $this->page(['blocks' => [self::block('audio', ['title' => 'Welcome talk'])]]);
        $editBlock = function (array $data) use ($page) {
            $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

            return $component->callAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]), $data);
        };

        $editBlock(['source' => 'soundcloud', 'embed_url' => 'https://open.spotify.com/episode/4rOoJ6Egrf8K2IrywzwOMk', 'title' => 'Welcome talk'])
            ->assertHasActionErrors(['embed_url']);

        $editBlock(['source' => 'soundcloud', 'embed_url' => 'https://soundcloud.com/village-hall/welcome-talk', 'title' => 'Welcome talk'])
            ->assertHasNoActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $data = Page::query()->find($page->id)->blockList()[0]['data'];
        $this->assertSame(['soundcloud', 'https://soundcloud.com/village-hall/welcome-talk'], [$data['source'], $data['embed_url']]);
    }
}
