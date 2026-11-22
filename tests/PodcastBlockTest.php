<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\Core\PodcastBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Podcasts\PodcastFeeds;

/**
 * The Podcast block: a podcast's episodes from its RSS feed, read safely and at most once an hour, drawn in the
 * site's own styles with one shared player.
 */
class PodcastBlockTest extends PageTestCase
{
    private const FEED = 'https://podcasts.example.com/village-hall-talks.rss';

    /** A 1×1 PNG, for the podcast's cover. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

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
        // Every host is on the public internet unless a test says otherwise; no real DNS or network.
        PodcastFeeds::resolveHostsUsing(fn (string $host): array => ['93.184.216.34']);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        PodcastFeeds::resolveHostsUsing(null);

        parent::tearDown();
    }

    private static function rss(): string
    {
        return (string) file_get_contents(__DIR__.'/Fixtures/podcasts/village-hall-talks.rss');
    }

    private function fakeFeed(): void
    {
        Http::fake([
            self::FEED => Http::response(self::rss(), 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']),
            'https://podcasts.example.com/cover.png' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    /**
     * A new request: PodcastFeeds remembers what it read for one request only.
     */
    private function feeds(): PodcastFeeds
    {
        app()->forgetScopedInstances();

        return app(PodcastFeeds::class);
    }

    public function test_the_layout_section_and_the_episodes_design_section_have_different_names(): void
    {
        $layoutTitle = (new \ReflectionMethod(PodcastBlock::class, 'styleToggleTitle'))->invoke(null);

        $this->assertSame('Episode layout', $layoutTitle);
        $this->assertSame('Episodes', PodcastBlock::itemParts()->label);
    }

    public function test_a_feed_link_or_an_audioboom_podcast_page_gives_the_feed(): void
    {
        $this->assertSame('https://audioboom.com/channels/4937603.rss', PodcastFeeds::feedUrl('https://audioboom.com/channels/4937603'));
        $this->assertSame('https://audioboom.com/channels/4937603.rss', PodcastFeeds::feedUrl(' https://www.audioboom.com/channels/4937603-village-hall-talks '));
        $this->assertSame('https://audioboom.com/channels/4937603.rss', PodcastFeeds::feedUrl('https://audioboom.com/channels/4937603.rss'));
        $this->assertSame(self::FEED, PodcastFeeds::feedUrl('http://podcasts.example.com/village-hall-talks.rss'));

        foreach (['', 'not a link', 'ftp://podcasts.example.com/feed', 'javascript:alert(1)', ['https://example.com']] as $link) {
            $this->assertNull(PodcastFeeds::feedUrl($link), json_encode($link));
        }
    }

    public function test_a_feed_is_read_as_plain_text_episodes_with_playable_audio_newest_first(): void
    {
        $podcast = $this->feeds()->parse(self::rss());

        $this->assertSame('Village Hall Talks', $podcast['title']);
        $this->assertSame('https://podcasts.example.com/village-hall-talks', $podcast['link']);
        $this->assertSame(['Ep3: Booking the hall', 'Ep2: The summer fair', 'Ep1: Welcome'], array_column($podcast['episodes'], 'title'));
        $this->assertSame([3723, 2710, 600], array_column($podcast['episodes'], 'seconds'));
        $this->assertSame('How bookings work.', $podcast['episodes'][0]['description']);
        $this->assertSame('https://podcasts.example.com/ep3.jpg', $podcast['episodes'][0]['imageUrl']);
        $this->assertSame('Planning the summer fair in Sometown. alert(1)', $podcast['episodes'][1]['description']);
        $this->assertSame('https://podcasts.example.com/audio/2.mp3?source=rss', $podcast['episodes'][1]['audioUrl']);

        $this->assertNull($this->feeds()->parse('<html><body>Not a feed</body></html>'));
        // External entities aren't read: the title stays empty, so the podcast gets the standard name.
        $withEntity = $this->feeds()->parse('<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/hosts">]><rss><channel><title>&x;</title><item><title>One</title><enclosure url="https://example.com/a.mp3" type="audio/mpeg"/></item></channel></rss>');
        $this->assertSame('Podcast', $withEntity['title']);
        $this->assertSame(['1 hr 2 min', '45 min', ''], [PodcastBlock::length(3723), PodcastBlock::length(2710), PodcastBlock::length(null)]);
    }

    public function test_a_feed_is_read_once_an_hour_and_the_last_good_copy_stays_when_it_fails(): void
    {
        $this->fakeFeed();

        $podcast = $this->feeds()->podcast(self::FEED);
        $this->assertCount(3, $podcast['episodes']);
        // The cover is kept on the site.
        $this->assertStringStartsWith('/storage/site-builder/podcasts/', $podcast['coverUrl']);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $podcast['coverUrl']));
        Http::assertSentCount(2);

        // Within the hour: from the copy, no request.
        $this->travel(30)->minutes();
        $this->feeds()->podcast(self::FEED);
        $this->app->terminate();
        Http::assertSentCount(2);

        // Over the hour: shown from the copy straight away, refreshed after the response; the host is down.
        Http::fake([self::FEED => Http::response('Down', 503)]);
        $this->travel(31)->minutes();
        $this->assertCount(3, $this->feeds()->podcast(self::FEED)['episodes']);
        $this->app->terminate();
        Http::assertSent(fn ($request): bool => $request->url() === self::FEED);
        $this->assertCount(3, $this->feeds()->podcast(self::FEED)['episodes']);
    }

    public function test_only_https_addresses_on_the_public_internet_are_read(): void
    {
        PodcastFeeds::resolveHostsUsing(fn (string $host): array => $host === 'inside.example.com' ? ['10.0.0.5'] : ['93.184.216.34']);
        Http::fake([
            'https://inside.example.com/*' => Http::response(self::rss(), 200, ['Content-Type' => 'application/rss+xml']),
            'https://podcasts.example.com/moved.rss' => Http::response('', 302, ['Location' => 'https://inside.example.com/feed.rss']),
            'https://podcasts.example.com/plain.rss' => Http::response('', 301, ['Location' => 'http://podcasts.example.com/village-hall-talks.rss']),
            'https://podcasts.example.com/page.html' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
            'https://podcasts.example.com/huge.rss' => Http::response(str_repeat('x', PodcastFeeds::MAX_BYTES + 1), 200, ['Content-Type' => 'application/rss+xml']),
            'https://127.0.0.1/*' => Http::response(self::rss(), 200),
        ]);

        foreach ([
            'https://inside.example.com/feed.rss',
            'https://podcasts.example.com/moved.rss',
            'https://podcasts.example.com/plain.rss',
            'https://podcasts.example.com/page.html',
            'https://podcasts.example.com/huge.rss',
            'https://127.0.0.1/feed.rss',
            'https://podcasts.example.com:8443/feed.rss',
            'https://user:secret@podcasts.example.com/feed.rss',
        ] as $url) {
            $this->assertNull($this->feeds()->refresh($url), $url);
        }

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'inside.example.com') || str_contains($request->url(), '127.0.0.1'));
    }

    public function test_the_page_shows_the_episodes_with_a_player_and_show_more(): void
    {
        $this->fakeFeed();
        $this->page(['blocks' => [self::block('podcast', ['feed_url' => self::FEED, 'count' => 2, 'heading' => 'Listen'])]]);

        $html = $this->get('/about-us')->assertSuccessful()
            ->assertSeeInOrder(['Village Hall Talks', 'Ep3: Booking the hall', 'Ep2: The summer fair', 'Ep1: Welcome', 'Show more episodes'])
            ->assertDontSee('All episodes')
            ->assertDontSee('href="https://podcasts.example.com/village-hall-talks"', false)
            ->assertDontSee('href="https://podcasts.example.com/episodes/2"', false)
            ->assertSee('<audio x-ref="audio" preload="none"', false)
            ->assertSee('aria-label="Play: Ep3: Booking the hall"', false)
            ->assertSee('17 Nov 2026')
            ->assertSee('1 hr 2 min')
            ->assertSee('line-clamp-2', false)
            ->assertSee('/storage/site-builder/podcasts/', false)
            ->assertDontSee('<iframe', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->getContent();

        // Artwork as standard: each episode's image on the left (or the podcast's cover, kept on the site), play on the right.
        // Ep3 has its own image; the header and Ep1 and Ep2 show the cover.
        $this->assertStringContainsString('src="https://podcasts.example.com/ep3.jpg"', $html);
        $this->assertSame(3, substr_count($html, 'src="/storage/site-builder/podcasts/'));
        $this->assertSame(3, substr_count($html, 'order-last self-center'));

        // The third is hidden until Show more; without JavaScript each has a Listen link.
        $this->assertMatchesRegularExpression('/x-show="2 < shown" style="display: none"/', $html);
        $this->assertStringContainsString('<a href="https://podcasts.example.com/audio/1.m4a"', $html);
    }

    public function test_without_artwork_the_play_button_leads(): void
    {
        $this->fakeFeed();
        $this->page(['blocks' => [self::block('podcast', ['feed_url' => self::FEED, 'show' => ['date'], 'show_header' => false])]]);

        $this->get('/about-us')->assertSuccessful()
            ->assertSee('aria-label="Play: Ep3: Booking the hall"', false)
            ->assertDontSee('https://podcasts.example.com/ep3.jpg', false)
            ->assertDontSee('/storage/site-builder/podcasts/', false)
            ->assertDontSee('order-last', false);
    }

    public function test_links_to_the_podcast_host_show_only_when_turned_on(): void
    {
        $this->fakeFeed();
        $this->page(['blocks' => [self::block('podcast', ['feed_url' => self::FEED, 'link_to_host' => true])]]);

        $this->get('/about-us')->assertSuccessful()
            ->assertSee('All episodes')
            ->assertSee('href="https://podcasts.example.com/village-hall-talks"', false)
            ->assertSee('href="https://podcasts.example.com/episodes/2"', false)
            ->assertSee('aria-label="Play: Ep2: The summer fair"', false);
    }

    public function test_what_each_episode_shows_its_order_and_cards_follow_the_settings(): void
    {
        $this->fakeFeed();
        $this->page(['blocks' => [self::block('podcast', [
            'feed_url' => self::FEED,
            'count' => 5,
            'order' => 'oldest',
            'show' => ['artwork'],
            'show_header' => false,
            'show_more' => false,
            'columns' => 2,
            'card' => ['background' => true, 'border' => true],
        ])]]);

        $this->get('/about-us')->assertSuccessful()
            ->assertSeeInOrder(['Ep1: Welcome', 'Ep2: The summer fair', 'Ep3: Booking the hall'])
            ->assertSee('https://podcasts.example.com/ep3.jpg', false)
            ->assertSee('sm:grid-cols-2', false)
            ->assertSee('rounded-sb-card', false)
            ->assertDontSee('All episodes')
            ->assertDontSee('Show more episodes')
            ->assertDontSee('45 min')
            ->assertDontSee('How bookings work.');
    }

    public function test_a_feed_that_cant_be_read_hides_the_block_or_shows_the_message(): void
    {
        Http::fake(['*' => Http::response('Down', 503)]);
        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Before</p>']),
            self::block('podcast', ['feed_url' => self::FEED, 'heading' => 'Our podcast', 'when_empty' => 'message', 'empty_message' => 'Episodes are on their way.']),
        ]]);

        $this->get('/about-us')->assertSuccessful()->assertSee('Before')->assertSee('Episodes are on their way.')->assertDontSee('Show more episodes');
        $this->assertFalse(PodcastBlock::isCacheable([]));
    }

    public function test_the_feed_is_checked_when_the_block_is_saved(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $this->fakeFeed();
        Http::fake(['https://podcasts.example.com/page.html' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

        $page = $this->page(['blocks' => [self::block('podcast', ['feed_url' => self::FEED])]]);
        $editBlock = function (array $data) use ($page) {
            $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

            return $component->callAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]), $data);
        };

        $editBlock(['feed_url' => 'not a link'])->assertHasActionErrors(['feed_url']);
        $editBlock(['feed_url' => 'https://podcasts.example.com/page.html'])->assertHasActionErrors(['feed_url']);
        $editBlock(['feed_url' => self::FEED])->assertHasNoActionErrors()->call('save')->assertHasNoFormErrors();

        $this->assertSame(self::FEED, Page::query()->find($page->id)->blockList()[0]['data']['feed_url']);
    }
}
