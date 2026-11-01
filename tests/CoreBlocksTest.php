<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\MapBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SocialFollowBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\StatsBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\VideoBlock;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

class CoreBlocksTest extends PageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_every_core_block_is_in_the_picker(): void
    {
        $this->assertSame([
            'section', 'hero', 'banner', 'heading', 'text', 'image_text', 'image', 'features', 'image_cards', 'steps', 'stats', 'countdown', 'circle_counters', 'bar_counters', 'pricing_table', 'call_to_action', 'mailing_list', 'contact_form',
            'buttons', 'icon', 'testimonials', 'team', 'faq', 'toggle', 'tabs', 'gallery', 'video', 'map', 'logo_strip', 'social_follow', 'divider', 'latest_posts',
            'site_logo', 'site_menu', 'site_account_links', 'site_footer_links', 'site_copyright',
            'post_title', 'post_meta', 'post_image', 'post_content', 'post_share', 'post_nav', 'related_posts', 'archive_title', 'post_list', 'saved',
        ], array_keys(app(BlockRegistry::class)->all()));
    }

    public function test_a_page_with_every_block_renders(): void
    {
        config(['app.social_links' => ['facebook' => 'https://facebook.com/hall', 'x' => 'https://x.com/hall']]);

        $this->page(['blocks' => [
            self::block('hero', ['heading' => 'Welcome to the hall']),
            self::block('image', ['image' => 'site-builder/pages/a.jpg', 'alt' => 'The hall', 'caption' => 'Main room', 'link' => 'javascript:alert(1)']),
            self::block('features', ['heading' => 'Why us', 'items' => [['icon' => 'bx:regular/bx-wifi', 'title' => 'Free wifi', 'text' => 'Fast', 'link_url' => '/wifi']]]),
            self::block('image_cards', ['cards' => [['media' => 'icon', 'icon' => 'bx:regular/bx-car', 'title' => 'Parking', 'text' => 'Ten spaces']]]),
            self::block('steps', ['items' => [['title' => 'Choose a date'], ['title' => 'Book', 'icon' => 'bx:regular/bx-calendar-check']]]),
            self::block('stats', ['items' => [['source' => 'custom', 'value' => 1200, 'suffix' => '+', 'label' => 'Bookings']]]),
            self::block('buttons', ['buttons' => [['label' => 'Book now', 'url' => '/book', 'icon' => 'bx:regular/bx-calendar']], 'align' => 'right']),
            self::block('testimonials', ['items' => [['quote' => 'Lovely hall', 'name' => 'Sam', 'role' => 'Choir']]]),
            self::block('team', ['members' => [['name' => 'Alex', 'role' => 'Caretaker', 'linkedin' => 'https://linkedin.com/in/alex']]]),
            self::block('faq', ['items' => [['question' => 'Is there parking?', 'answer' => '<p>Yes, ten spaces.</p>']]]),
            self::block('tabs', ['items' => [['title' => 'Main hall', 'content' => '<p>Seats 100</p>'], ['title' => 'Small room', 'content' => '<p>Seats 20</p>']]]),
            self::block('gallery', ['images' => [['image' => 'site-builder/pages/b.jpg', 'alt' => 'Kitchen'], ['image' => 'site-builder/branding/logo.png', 'alt' => 'Not a page upload']]]),
            self::block('video', ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'A tour of the hall']),
            self::block('logo_strip', ['logos' => [['image' => 'site-builder/pages/c.png', 'name' => 'Parish council', 'url' => 'https://example.com']]]),
            self::block('social_follow', ['heading' => 'Follow us']),
            self::block('divider', ['label' => 'More']),
            self::block('heading', ['heading' => 'Our rooms', 'level' => 'h3']),
            self::block('countdown', ['heading' => 'Open day', 'Weekends booked', 'Rooms in use', 'Community', '£0', 'ends_at' => now()->addDays(3)->format('Y-m-d H:i:s')]),
            self::block('circle_counters', ['items' => [['label' => 'Weekends booked', 'value' => 85]]]),
            self::block('bar_counters', ['items' => [['label' => 'Rooms in use', 'value' => 60]]]),
            self::block('pricing_table', ['plans' => [['name' => 'Community', 'price' => 0, 'period' => 'per hour', 'features' => "Main hall\n- Kitchen"]]]),
            self::block('toggle', ['title' => 'Can we bring our own food?', 'content' => '<p>Yes, the kitchen is yours.</p>', 'open' => true]),
            self::block('icon', ['icon' => 'bx:regular/bx-accessibility', 'label' => 'Wheelchair access']),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        foreach ([
            'Welcome to the hall</h1>', 'py-24 sm:py-36', 'Main room', 'Free wifi', 'href="/wifi"', 'Parking', 'Choose a date',
            'Step 2', '1,200', 'Book now', 'justify-end', 'Lovely hall', 'Caretaker', 'Alex on LinkedIn', 'Is there parking?',
            'role="tablist"', 'aria-controls="tabpanel-', 'Kitchen', 'A tour of the hall', 'Parish council', 'Follow us', 'More', 'Our rooms</h3>', 'aria-label="Wheelchair access"', 'Can we bring our own food?', 'the kitchen is yours', 'Open day',
        ] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('site-builder/branding/logo.png', $html, 'Galleries only show page uploads.');
        $this->assertSame(1, substr_count($html, '<h1'));
        // Icons are inline SVGs from the package, never loaded from elsewhere.
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'aria-hidden="true" focusable="false"'));
        $this->assertStringContainsString('M18.244 2.25h3.308', $html, 'The X logo is drawn inline.');
    }

    public function test_the_faq_adds_safe_structured_data(): void
    {
        $this->page(['blocks' => [self::block('faq', ['items' => [['question' => 'Can I </script> break it?', 'answer' => '<p>No.</p>']]])]]);

        $html = $this->get('/about-us')->getContent();

        $this->assertStringContainsString('<script type="application/ld+json">', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringNotContainsString('Can I </script>', $html);
    }

    public function test_videos_are_click_to_play_until_consent_is_given(): void
    {
        $this->page(['blocks' => [self::block('video', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Tour'])]]);

        $this->get('/about-us')
            ->assertSee('<template x-if="playing">', false)
            ->assertSee('Plays from YouTube, which may set cookies.')
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1', false);

        app(SiteBuilder::class)->consentChecker(fn (string $category): bool => $category === 'marketing');

        $this->get('/about-us')
            ->assertDontSee('<template x-if="playing">', false)
            ->assertSee('<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0"', false);
    }

    public function test_maps_show_a_placeholder_until_consent_is_given(): void
    {
        $this->page(['blocks' => [self::block('map', ['address' => 'Village Hall, High Street, Sometown', 'zoom' => 'street'])]]);

        $this->get('/about-us')
            ->assertOk()
            ->assertSee('<template x-if="showing">', false)
            ->assertSee('Show map')
            ->assertSee('The map loads from Google Maps, which may set cookies.')
            ->assertSee('https://www.google.com/maps?q=Village%20Hall%2C%20High%20Street%2C%20Sometown&amp;z=17&amp;hl=en&amp;output=embed', false)
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=Village%20Hall%2C%20High%20Street%2C%20Sometown', false)
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=Village%20Hall%2C%20High%20Street%2C%20Sometown', false)
            ->assertSee('Get directions');

        app(SiteBuilder::class)->consentChecker(fn (string $category): bool => $category === 'marketing');

        $this->get('/about-us')
            ->assertDontSee('<template x-if="showing">', false)
            ->assertDontSee('Show map')
            ->assertSee('<iframe src="https://www.google.com/maps?q=Village%20Hall', false)
            ->assertSee('title="Map of Village Hall, High Street, Sometown"', false);
    }

    public function test_a_map_can_show_its_address_and_leave_out_directions(): void
    {
        $this->page(['blocks' => [self::block('map', [
            'address' => 'Village Hall, Sometown',
            'show_address' => true,
            'address_text' => "Village Hall\nHigh Street\nSometown",
            'show_directions' => false,
            'height' => 'large',
        ])]]);

        $this->get('/about-us')
            ->assertSee('<address class="block-text not-italic">', false)
            ->assertSeeInOrder(['Village Hall', '<br>', 'High Street', '<br>', 'Sometown'], false)
            ->assertSee('sm:h-[32rem]', false)
            ->assertDontSee('Get directions');
    }

    public function test_map_links_carry_only_the_encoded_address(): void
    {
        $urls = MapBlock::urls("  Hall\" onload=\"alert(1)&output=x\n#top  ", 'not-a-zoom');

        $this->assertStringStartsWith('https://www.google.com/maps?q=Hall%22%20onload%3D%22alert%281%29%26output%3Dx%20%23top&z=15&', $urls['embedUrl']);
        $this->assertStringEndsWith('&output=embed', $urls['embedUrl']);
        $this->assertSame(1, substr_count($urls['embedUrl'], 'output='));
        $this->assertNull(MapBlock::urls('   '));
        $this->assertNull(MapBlock::urls(['not a string']));
    }

    public function test_a_failing_consent_checker_means_no_consent(): void
    {
        app(SiteBuilder::class)->consentChecker(fn (): bool => throw new RuntimeException('Cookie banner broke'));

        $this->assertFalse(app(SiteBuilder::class)->consentAllows('marketing'));
    }

    public function test_video_links_are_recognised(): void
    {
        $this->assertSame('dQw4w9WgXcQ', VideoBlock::embed('https://www.youtube.com/shorts/dQw4w9WgXcQ')['id']);
        $this->assertSame('76979871', VideoBlock::embed('https://vimeo.com/76979871')['id']);
        $this->assertSame('Adilo', VideoBlock::embed('https://adilo.bigcommand.com/watch/abc123XY')['provider']);
        $this->assertNull(VideoBlock::embed('https://evil.example.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(VideoBlock::embed(['not a string']));
    }

    public function test_stats_show_typed_numbers_and_the_apps_live_figures(): void
    {
        app(SiteBuilder::class)->stats([
            'halls' => ['Halls listed', fn (): int => 42],
            'broken' => ['Broken figure', fn (): int => throw new RuntimeException('Down')],
        ]);

        $this->assertSame(['custom' => 'A number I type in', 'live:halls' => 'Live: Halls listed', 'live:broken' => 'Live: Broken figure'], StatsBlock::sources());

        $this->page(['blocks' => [self::block('stats', ['animate' => false, 'items' => [
            ['source' => 'live:halls', 'label' => 'Halls'],
            ['source' => 'live:broken', 'label' => 'Broken'],
            ['source' => 'live:gone', 'label' => 'Gone'],
            ['source' => 'custom', 'value' => '7', 'label' => 'Rooms'],
        ]])]]);

        $this->get('/about-us')
            ->assertSeeInOrder(['42', 'Halls', '7', 'Rooms'])
            ->assertDontSee('Broken')
            ->assertDontSee('Gone');
    }

    public function test_a_live_figure_is_worked_out_once_and_the_last_one_shown_while_it_refreshes(): void
    {
        $counted = 0;
        app(SiteBuilder::class)->stats(['halls' => ['Halls listed', function () use (&$counted): int {
            return 40 + ++$counted;
        }]]);
        $this->page(['blocks' => [self::block('stats', ['animate' => false, 'items' => [['source' => 'live:halls', 'label' => 'Halls']]])]]);

        $this->get('/about-us')->assertSee('41');
        $this->get('/about-us')->assertSee('41');
        $this->assertSame(1, $counted);

        // Once it's due a refresh, the last figure is still shown; the new one is worked out after the response.
        $this->travel(11)->minutes();
        $this->get('/about-us')->assertSee('41');
    }

    public function test_image_cards_become_a_carousel_with_more_cards_than_columns(): void
    {
        $cards = array_fill(0, 4, ['media' => 'none', 'title' => 'Room']);

        $this->page(['blocks' => [self::block('image_cards', ['cards' => $cards, 'columns' => 3])]]);
        $this->get('/about-us')->assertSee('snap-x', false)->assertSee(__('Next cards'));

        $this->page(['slug' => 'grid', 'blocks' => [self::block('image_cards', ['cards' => $cards, 'columns' => 4, 'carousel' => true])]]);
        $this->get('/grid')->assertDontSee('snap-x', false)->assertSee('lg:grid-cols-4', false);
    }

    public function test_a_moving_logo_strip_can_be_paused_and_its_copies_are_hidden_from_screen_readers(): void
    {
        $this->page(['blocks' => [self::block('logo_strip', ['display' => 'loop', 'logos' => [['image' => 'site-builder/pages/c.png', 'name' => 'Council']]])]]);

        $this->get('/about-us')
            ->assertSee('logo-loop-track', false)
            ->assertSee('aria-hidden="true" inert', false)
            ->assertSee(__('Pause logos'));
    }

    public function test_social_links_that_arent_web_addresses_arent_linked(): void
    {
        config(['app.social_links' => [
            'facebook' => 'javascript:alert(1)',
            'instagram' => '//instagram.com/hall',
            'youtube' => 'https://youtube.com/@villagehall',
        ]]);
        $this->page(['blocks' => [self::block('social_follow', ['heading' => 'Follow us'])]]);

        $this->get('/about-us')
            ->assertSee('https://youtube.com/@villagehall', false)
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('//instagram.com/hall', false);

        // The theme footer uses the same check.
        $this->assertTrue(SocialFollowBlock::isWebAddress('http://facebook.com/hall'));
        $this->assertFalse(SocialFollowBlock::isWebAddress(' JavaScript:alert(1)'));
        $this->assertFalse(SocialFollowBlock::isWebAddress('https:///evil'));
        $this->assertFalse(SocialFollowBlock::isWebAddress(['https://x.com']));
    }

    public function test_social_follow_shows_nothing_without_social_links(): void
    {
        config(['app.social_links' => []]);
        $this->page(['blocks' => [self::block('social_follow', ['heading' => 'Follow us'])]]);

        $this->get('/about-us')->assertDontSee('Follow us');
    }
}
