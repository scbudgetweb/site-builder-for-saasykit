<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\Storage;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;

/**
 * What the SaaSykit replica test found missing: small headings, see-through buttons, logo alignment, testimonial
 * columns and stars, a Hero's social proof and image, images in tabs, code in rich text, centred lists, AVIF uploads.
 */
class ReplicaGapsTest extends PageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function upload(string $name): string
    {
        $path = 'site-builder/pages/'.$name;
        Storage::disk('public')->put($path, 'image');

        return $path;
    }

    public function test_blocks_can_have_a_small_heading_as_text_or_a_pill(): void
    {
        $this->page(['blocks' => [
            self::block('features', ['eyebrow' => 'A solid hall', 'heading' => 'Rooms for everyone', 'items' => [['title' => 'Main hall']]]),
            self::block('image_text', ['eyebrow' => 'Most popular', 'eyebrow_style' => 'pill', 'heading' => 'The main hall']),
            self::block('text', ['eyebrow' => 'Opening times', 'content' => '<p>Every day</p>']),
            self::block('call_to_action', ['eyebrow' => 'Ready?', 'heading' => 'Book a room']),
        ]]);

        $this->get('/about-us')
            ->assertSee('<p class="sb-eyebrow">A solid hall</p>', false)
            ->assertSee('<p class="sb-eyebrow sb-eyebrow-pill">Most popular</p>', false)
            ->assertSee('<p class="sb-eyebrow">Opening times</p>', false)
            ->assertSee('<p class="sb-eyebrow">Ready?</p>', false);
    }

    public function test_a_custom_button_with_no_background_is_see_through(): void
    {
        $look = PageBlock::buttonLook(['style' => PageBlock::CUSTOM_BUTTON_STYLE, 'text_color' => '#ffffff', 'border_color' => '#ffffff'], true);

        $this->assertStringContainsString('--site-button-bg: transparent;', $look['style']);
        $this->assertStringContainsString('--site-button-text: #ffffff;', $look['style']);
        $this->assertStringNotContainsString('site-button-1', $look['class'], 'Its own colours, not a Branding style.');

        $this->assertStringContainsString('--site-button-text: #ffffff;', PageBlock::buttonLook(['style' => PageBlock::CUSTOM_BUTTON_STYLE, 'bg_color' => '#000000'], true)['style'], 'No text colour: one that reads on the background.');
        $this->assertStringNotContainsString('--site-button-bg', PageBlock::buttonLook(['style' => PageBlock::CUSTOM_BUTTON_STYLE], true)['style'], 'No colours at all: not custom.');
    }

    public function test_logos_testimonials_tabs_and_a_heros_extras(): void
    {
        $this->page(['blocks' => [
            self::block('hero', [
                'heading' => 'Book the Village Hall',
                'proof_avatars' => [$this->upload('a.png'), $this->upload('b.png')],
                'proof_stars' => 5,
                'proof_text' => 'Loved by 900+ groups in Sometown',
                'feature_image' => $this->upload('hall.png'),
                'feature_image_alt' => 'The booking calendar',
            ]),
            self::block('logo_strip', ['align' => 'left', 'logos' => [['image' => $this->upload('logo.png'), 'name' => 'Sometown Council']]]),
            self::block('testimonials', ['columns' => 4, 'items' => [['quote' => 'Easy to book', 'name' => 'Sam', 'stars' => 4]]]),
            self::block('tabs', ['tabs_align' => 'center', 'items' => [
                ['title' => 'Rooms', 'content' => '<p>Three rooms</p>', 'image' => $this->upload('rooms.png'), 'alt' => 'The rooms'],
                ['title' => 'Prices', 'content' => '<p>From £10</p>'],
            ]]),
        ]]);

        $this->get('/about-us')
            ->assertSee('Loved by 900+ groups in Sometown')
            ->assertSee('aria-label="5 stars out of 5"', false)
            ->assertSee('site-builder/pages/b.png', false)
            ->assertSee('alt="The booking calendar"', false)
            ->assertSee('flex flex-wrap items-center justify-start gap-x-10', false)
            ->assertSee('lg:grid-cols-4', false)
            ->assertSee('aria-label="4 stars out of 5"', false)
            ->assertSee('alt="The rooms"', false)
            ->assertSee('flex flex-wrap justify-center gap-2 border-b', false);
    }

    public function test_rich_text_keeps_code_and_alignment_and_avif_images_are_accepted(): void
    {
        $this->page(['blocks' => [self::block('text', ['content' => '<p>Run <code>php artisan migrate</code></p><pre><code>$ ./vendor/bin/dep deploy</code></pre><p style="text-align: center">Centred</p>'])]]);

        $this->get('/about-us')
            ->assertSee('<code>php artisan migrate</code>', false)
            ->assertSee('<pre><code', false)
            ->assertSee('$ ./vendor/bin/dep deploy', false)
            ->assertSee('text-align: center', false);

        $this->assertContains('image/avif', PageBlock::IMAGE_FILE_TYPES);
    }
}
