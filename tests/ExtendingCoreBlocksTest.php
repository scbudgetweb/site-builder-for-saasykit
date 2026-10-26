<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;
use ScBudgetWeb\SiteBuilder\Blocks\PageRenderer;
use ScBudgetWeb\SiteBuilder\Facades\SiteBuilder as SiteBuilderFacade;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blocks\HighlightBannerBlock;

/**
 * A host block that extends a core block and replaces it by name, with the example Banner subclass.
 */
class ExtendingCoreBlocksTest extends PageTestCase
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

        SiteBuilderFacade::blocks([HighlightBannerBlock::class]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
    }

    public function test_the_subclass_replaces_the_core_block_in_its_place_in_the_picker(): void
    {
        $registry = app(BlockRegistry::class);
        $names = array_keys($registry->all());

        $this->assertSame(HighlightBannerBlock::class, $registry->find('banner'));
        // Same place as the core Banner, which isn't listed twice.
        $this->assertSame(array_map(fn (string $block): string => $block::name(), BlockRegistry::CORE_BLOCKS), $names);
        // Banners still can't go in a Section.
        $this->assertNotContains('banner', array_map(fn ($block) => $block->getName(), $registry->sectionBuilderBlocks()));
    }

    public function test_the_subclass_keeps_the_banners_fields_and_adds_its_own(): void
    {
        $page = $this->page(['blocks' => [self::block('banner', ['heading' => 'Welcome', 'show_highlight' => true, 'highlight' => 'Open today'])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component
            ->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]))
            ->assertFormFieldExists('heading')
            ->assertFormFieldExists('buttons')
            ->assertFormFieldExists('height')
            ->assertFormFieldExists('style')
            ->assertFormFieldExists('show_highlight')
            ->assertFormFieldExists('highlight_position');

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['blocks' => ['one' => self::block('banner', ['heading' => 'Welcome', 'show_highlight' => true, 'highlight' => ''])]])
            ->call('save')
            ->assertNotified('The page wasn\'t saved');
    }

    public function test_the_extra_content_shows_below_or_beside_the_banner_text(): void
    {
        $this->page(['blocks' => [
            self::block('banner', ['heading' => 'Welcome', 'subtitle' => 'All about us', 'show_highlight' => true, 'highlight' => 'Open today']),
            self::block('banner', ['heading' => 'Second banner', 'show_highlight' => true, 'highlight' => 'Book now', 'highlight_position' => 'beside']),
            self::block('banner', ['heading' => 'Plain banner', 'highlight' => 'Not shown']),
        ]]);

        $response = $this->get('/about-us')->assertSuccessful();

        $response->assertSeeInOrder(['<h1', 'Welcome', 'All about us', 'data-banner-extra="below"', 'Open today'], false);
        $response->assertSeeInOrder(['lg:grid-cols-2', 'Second banner', 'data-banner-extra="beside"', 'Book now'], false);
        $response->assertSee('Plain banner')->assertDontSee('Not shown');
    }

    public function test_the_replacement_is_used_for_library_blocks_and_cached_pages(): void
    {
        $item = app(BlockLibrary::class)->save('Welcome banner', 'banner', ['heading' => 'From the library', 'show_highlight' => true, 'highlight' => 'Linked highlight']);
        $page = $this->page(['blocks' => [self::block('saved', ['saved_block_id' => $item->id])]]);
        $renderer = app(PageRenderer::class);

        $this->assertTrue($renderer->canCache($page, $page->blockList()));
        $this->assertStringContainsString('Linked highlight', (string) $renderer->render($page));
        $this->assertStringContainsString('Linked highlight', (string) $renderer->render($page->fresh()));
    }

    public function test_a_replaced_block_inside_a_sections_row_uses_the_replacement(): void
    {
        $uncacheableText = new class extends TextBlock
        {
            public static function isCacheable(array $data): bool
            {
                return false;
            }
        };
        SiteBuilderFacade::blocks([$uncacheableText::class]);

        $registry = app(BlockRegistry::class);
        $this->assertCount(1, array_filter($registry->sectionBuilderBlocks(), fn ($block): bool => $block->getName() === 'text'));

        $page = $this->page(['blocks' => [
            self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::block('text', ['content' => '<p>In a row</p>'])]]]]),
        ]]);

        // Only the replacement says it can't be cached, so the renderer looked it up for the row's block.
        $this->assertFalse(app(PageRenderer::class)->canCache($page, $page->blockList()));
        $this->get('/about-us')->assertSuccessful()->assertSee('In a row');
    }
}
