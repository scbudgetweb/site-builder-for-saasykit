<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Models\Page;

class SectionTest extends PageTestCase
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
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $data
     * @return array{type: string, data: array<string, mixed>}
     */
    private static function section(array $rows, array $data = []): array
    {
        return self::block('section', ['rows' => $rows, ...$data]);
    }

    public function test_a_section_renders_its_rows_in_their_layouts(): void
    {
        $this->page(['blocks' => [self::section([
            ['layout' => '1-2', 'align' => 'center', 'reverse_on_phones' => true, 'blocks' => [
                self::block('image_text', ['heading' => 'Left column']),
                self::block('text', ['content' => '<p>Right column</p>']),
            ]],
            ['layout' => 'stacked', 'blocks' => [self::block('call_to_action', ['heading' => 'Underneath'])]],
        ], ['appearance' => ['background' => 'brand_dark']])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]*class="relative bg-primary-900[^"]*text-white[^"]*"[^>]*data-block="section"/', $html);
        $this->assertStringContainsString('data-row-layout="1-2"', $html);
        $this->assertStringContainsString('md:grid-cols-3', $html);
        $this->assertStringContainsString('items-center', $html);
        $this->assertMatchesRegularExpression('/md:col-span-1[^>]*>.*Left column.*md:col-span-2 max-md:order-first[^>]*>.*Right column/s', $html);
        $this->assertSeeInOrder(['Left column', 'Right column', 'Underneath'], $html);
    }

    private function assertSeeInOrder(array $values, string $html): void
    {
        $position = 0;

        foreach ($values as $value) {
            $found = strpos($html, $value, $position);
            $this->assertNotFalse($found, "Expected to find \"{$value}\" in order.");
            $position = $found + strlen($value);
        }
    }

    public function test_blocks_in_a_section_take_its_text_colour_and_decoration_and_fill_their_column(): void
    {
        $this->page(['blocks' => [self::section([
            ['layout' => '2', 'blocks' => [
                self::block('image_text', ['heading' => 'Inherits']),
                self::block('image_text', ['heading' => 'Own choice', 'appearance' => ['heading_decoration' => 'none', 'background' => 'light']]),
            ]],
        ], ['appearance' => ['background' => 'dark', 'heading_decoration' => 'short_above']])]]);

        $html = $this->get('/about-us')->getContent();

        // The inner block: no spacing or width of its own, no text colour (it inherits), the section's decoration.
        $this->assertMatchesRegularExpression('/<section\s+class="relative heading-decoration-short-above scroll-mt-24"\s+data-block="image_text"\s*>/', $html);
        $this->assertStringContainsString('no-heading-decoration', $html);
        $this->assertStringContainsString('bg-gray-50', $html);
        $this->assertSame(0, substr_count($html, '<h1 class="sb-h1 text-sb-banner-heading">About us</h1>') - 1, 'The page title is the only h1.');
    }

    public function test_a_same_height_row_lets_its_blocks_line_up_their_buttons(): void
    {
        $card = fn (string $heading): array => self::block('image_text', ['image_side' => 'above', 'heading' => $heading, 'buttons' => [['label' => 'Book', 'url' => '/book']]]);

        $this->page(['blocks' => [self::section([
            ['layout' => '3', 'align' => 'stretch', 'blocks' => [$card('One'), $card('Two'), $card('Three')]],
            ['layout' => '3', 'align' => 'start', 'blocks' => [$card('Four')]],
        ])]]);

        $html = $this->get('/about-us')->getContent();

        $this->assertSame(1, substr_count($html, 'sb-row-stretch'), 'Only the Same height row stretches its blocks.');
        $this->assertSame(4, substr_count($html, 'class="sb-block-buttons'), 'Buttons below the text are marked, so they can drop to the bottom.');
        $this->assertStringContainsString('items-stretch', $html);
    }

    public function test_a_row_can_have_a_border_shadow_corners_and_padding(): void
    {
        $this->page(['blocks' => [self::section([
            ['layout' => '2', 'border_style' => 'solid', 'border_color' => '#cccccc', 'shadow' => 'soft', 'corners' => 'large', 'blocks' => [self::block('text', ['content' => '<p>Framed</p>'])]],
            ['layout' => 'stacked', 'padding' => 'large', 'blocks' => [self::block('text', ['content' => '<p>Padded</p>'])]],
        ])]]);

        $html = $this->get('/about-us')->getContent();

        $this->assertMatchesRegularExpression('/class="[^"]*rounded-3xl p-6 sm:p-8"[^>]*style="border: 1px solid #cccccc; box-shadow: [^"]+"/', $html, 'A framed row gets automatic padding.');
        $this->assertMatchesRegularExpression('/data-row-layout="stacked"/', $html);
        $this->assertStringContainsString('p-8 sm:p-12', $html);
    }

    public function test_hero_banner_and_section_are_skipped_inside_a_section(): void
    {
        $this->page(['blocks' => [self::section([
            ['layout' => 'stacked', 'blocks' => [
                self::block('banner', ['heading' => 'Hidden banner']),
                self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::block('text', ['content' => '<p>Too deep</p>'])]]]]),
                self::block('text', ['content' => '<p>Shown</p>']),
            ]],
            'not a row',
        ])]]);

        $this->get('/about-us')->assertOk()->assertSee('Shown')->assertDontSee('Hidden banner')->assertDontSee('Too deep');
    }

    public function test_blocks_and_images_in_rows_count_as_on_the_page(): void
    {
        // Only the current version is kept, so an image removed on save has no older revision still using it.
        config(['site-builder.pages.revisions' => 1]);

        Storage::disk('public')->put('site-builder/pages/inner.jpg', 'image');

        $page = $this->page(['blocks' => [self::section([
            ['layout' => '2', 'blocks' => [self::block('image', ['image' => 'site-builder/pages/inner.jpg', 'alt' => 'Hall']), self::block('call_to_action', ['heading' => 'Hi'])]],
        ])]]);

        $this->assertTrue($page->hasBlock('call_to_action'));
        $this->assertFalse($page->hasBlock('faq'));

        $page->update(['blocks' => []]);
        Storage::disk('public')->assertMissing('site-builder/pages/inner.jpg');
    }

    public function test_the_section_panel_offers_rows_and_only_section_friendly_blocks(): void
    {
        $page = $this->page(['blocks' => [self::section([['layout' => '2', 'blocks' => [self::block('text', ['content' => '<p>Hi</p>'])]]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component
            ->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]))
            ->assertFormFieldExists('rows')
            ->assertFormFieldExists('gap')
            ->assertFormFieldExists('appearance.background');

        $registry = app(BlockRegistry::class);
        $names = array_map(fn ($block) => $block->getName(), $registry->sectionBuilderBlocks());

        $this->assertNotContains('hero', $names);
        $this->assertNotContains('banner', $names);
        $this->assertNotContains('section', $names);
        $this->assertContains('text', $names);
    }

    public function test_save_page_from_a_block_panel_inside_a_section_keeps_both_panels_changes(): void
    {
        $page = $this->page(['blocks' => [self::section([['layout' => '2', 'blocks' => [self::block('text', ['content' => '<p>Old</p>'])]]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $sectionKey = array_key_first($component->get('data.blocks'));

        $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]));

        $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
        $innerKey = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.blocks"));

        $component
            ->set('mountedActions.0.data.gap', 'large')
            ->mountAction([
                TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]),
                TestAction::make('edit')->schemaComponent("rows.{$rowKey}.blocks", 'mountedActionSchema0')->arguments(['item' => $innerKey]),
            ]);

        $this->assertCount(2, $component->get('mountedActions'), 'The block opens in a second panel over the section\'s.');

        $component
            ->set('mountedActions.1.data.content', '<p>New</p>')
            ->call('saveFromEditor')
            ->assertHasNoFormErrors();

        $section = Page::query()->find($page->id)->blockList()[0]['data'];
        $this->assertSame('large', $section['gap']);
        $this->assertStringContainsString('New', array_values(array_values($section['rows'])[0]['blocks'])[0]['data']['content']);
    }

    public function test_a_problem_in_a_rows_block_names_the_section_row_and_block(): void
    {
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['blocks' => [
                'intro' => self::block('text', ['content' => '<p>Intro</p>']),
                'sec' => self::section([
                    'r1' => ['layout' => 'stacked', 'blocks' => ['ok' => self::block('text', ['content' => '<p>Fine</p>'])]],
                    'r2' => ['layout' => '2', 'blocks' => ['fine' => self::block('text', ['content' => '<p>Fine</p>']), 'bad' => self::block('call_to_action', ['heading' => ''])]],
                ]),
            ]])
            ->call('save');

        // As Notification::assertNotified() reads them, but keeping the body.
        app(NotificationMailbox::class)->flush();
        $notifications = new Notifications;
        $notifications->mount();
        $notifications->pullNotificationsFromSession();
        $notification = $notifications->notifications->first(fn (Notification $sent): bool => $sent->getTitle() === 'The page wasn\'t saved');

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Block 2 (Section (rows of blocks)) › Row 2 › Block 2 (Call to action)', (string) $notification->getBody());
    }
}
