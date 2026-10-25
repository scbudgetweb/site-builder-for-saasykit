<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\CreateSavedBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\EditSavedBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\ListSavedBlocks;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;

/**
 * Admin › Website › Block library, and linked "Saved block" blocks on pages (HB-94).
 */
class BlockLibraryTest extends PageTestCase
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

    public function test_the_library_sits_in_the_website_group_for_people_with_the_page_permissions(): void
    {
        $this->assertTrue(SavedBlockResource::canViewAny());
        $this->assertSame('Website', SavedBlockResource::getNavigationGroup());
        $this->assertSame(2, SavedBlockResource::getNavigationSort());

        $this->actingAs($this->user('someone@example.com'));
        $this->assertFalse(SavedBlockResource::canViewAny());
        $this->assertFalse(SavedBlockResource::canCreate());
    }

    public function test_a_linked_block_shows_the_library_block_and_follows_its_changes(): void
    {
        $item = $this->item('Book a room', 'call_to_action', ['heading' => 'Book a room today']);
        $this->page(['blocks' => [self::linked($item), self::block('text', ['content' => '<p>After the link</p>'])]]);

        $this->get('/about-us')->assertSuccessful()->assertSeeInOrder(['Book a room today', 'After the link']);

        $item->update(['blocks' => [self::block('call_to_action', ['heading' => 'Book the main hall'])]]);

        $this->get('/about-us')->assertSee('Book the main hall')->assertDontSee('Book a room today');
    }

    public function test_a_linked_block_works_in_a_sections_row_and_a_linked_section_keeps_its_rows(): void
    {
        $cta = $this->item('Call', 'call_to_action', ['heading' => 'Linked in a row']);
        $section = $this->item('Two columns', 'section', ['rows' => [['layout' => '2', 'blocks' => [
            self::block('text', ['content' => '<p>Library left</p>']),
            self::block('text', ['content' => '<p>Library right</p>']),
        ]]]]);

        $this->page(['blocks' => [
            self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::linked($cta)]]]]),
            self::linked($section),
        ]]);

        $this->get('/about-us')
            ->assertSuccessful()
            ->assertSeeInOrder(['Linked in a row', 'Library left', 'Library right'])
            ->assertSee('data-row-layout="2"', false);
    }

    public function test_missing_items_misplaced_sections_and_links_inside_the_library_show_nothing(): void
    {
        $section = $this->item('A section', 'section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::block('text', ['content' => '<p>Section text</p>'])]]]]);
        // Saved by hand: a library item holding a link (the editor never offers one).
        $loop = $this->item('Loop', 'text', ['content' => '<p>Unused</p>']);
        $loop->update(['blocks' => [self::linked($section)]]);

        $this->page(['blocks' => [
            self::block('saved', ['saved_block_id' => 999]),
            self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::linked($section), self::block('text', ['content' => '<p>Still here</p>'])]]]]),
            self::linked($loop),
        ]]);

        $this->get('/about-us')
            ->assertSuccessful()
            ->assertSee('Still here')
            ->assertDontSee('Section text');
    }

    public function test_a_linked_block_counts_as_the_block_a_built_in_page_needs(): void
    {
        $item = $this->item('Contact CTA', 'call_to_action', ['heading' => 'Get in touch']);

        $page = $this->page(['blocks' => [self::linked($item)]]);
        $this->assertTrue($page->hasBlock('call_to_action'));
        $this->assertTrue(Page::blocksContain(['x' => self::block('section', ['rows' => [['blocks' => [self::linked($item)]]]])], 'call_to_action'));
        $this->assertFalse($page->hasBlock('text'));
    }

    public function test_a_linked_blocks_card_and_picker_name_the_library_item(): void
    {
        $cta = $this->item('Join CTA', 'call_to_action', ['heading' => 'Join us']);
        $this->item('Two columns', 'section', ['rows' => [['blocks' => [self::block('text', ['content' => '<p>Hi</p>'])]]]]);
        $page = $this->page(['blocks' => [
            self::linked($cta),
            self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::block('saved', ['saved_block_id' => null])]]]]),
        ]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        [$linkKey, $sectionKey] = array_keys($component->get('data.blocks'));

        $component->assertSee(['Join CTA', 'Linked', 'Call to action, linked to the block library']);

        $component
            ->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $linkKey]))
            ->assertFormFieldExists('saved_block_id', 'mountedActionSchema0', fn ($field): bool => array_values($field->getOptions()) === ['Join CTA (Call to action)', 'Two columns (Section (rows of blocks))']);

        $component->unmountAction()->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]));
        $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
        $innerKey = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.blocks"));

        $component
            ->mountAction([
                TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]),
                TestAction::make('edit')->schemaComponent("rows.{$rowKey}.blocks", 'mountedActionSchema0')->arguments(['item' => $innerKey]),
            ])
            ->assertFormFieldExists('saved_block_id', 'mountedActionSchema1', fn ($field): bool => array_values($field->getOptions()) === ['Join CTA (Call to action)']);
    }

    public function test_used_on_lists_the_pages_linking_to_an_item_including_ones_in_the_bin(): void
    {
        $item = $this->item('Logos', 'text', ['content' => '<p>Logos</p>']);
        $other = $this->item('Other', 'text', ['content' => '<p>Other</p>']);
        $top = $this->page(['title' => 'Top', 'slug' => 'top', 'blocks' => [self::linked($item)]]);
        $inRow = $this->page(['title' => 'In a row', 'slug' => 'in-a-row', 'blocks' => [self::block('section', ['rows' => [['blocks' => [self::linked($item)]]]])]]);
        $binned = $this->page(['title' => 'Binned', 'slug' => 'binned', 'blocks' => [self::linked($item)]]);
        $binned->delete();
        $this->page(['title' => 'Unrelated', 'slug' => 'unrelated', 'blocks' => [self::linked($other)]]);

        $this->assertSame(['Binned', 'In a row', 'Top'], app(BlockLibrary::class)->usedOn($item)->pluck('title')->all());

        Livewire::test(EditSavedBlock::class, ['record' => $item->getRouteKey()])->assertSee(['Used on', 'In a row', '(in the bin)']);
        Livewire::test(ListSavedBlocks::class)->assertCanSeeTableRecords([$item, $other])->assertSee('3 pages');
    }

    public function test_an_item_in_use_cant_be_deleted_and_an_unused_one_can(): void
    {
        $used = $this->item('Used', 'text', ['content' => '<p>Used</p>']);
        $unused = $this->item('Unused', 'text', ['content' => '<p>Unused</p>']);
        $this->page(['blocks' => [self::linked($used)]]);

        Livewire::test(ListSavedBlocks::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($used))
            ->assertNotified('This block is still in use');
        $this->assertModelExists($used);

        Livewire::test(EditSavedBlock::class, ['record' => $used->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('This block is still in use');
        $this->assertModelExists($used);

        Livewire::test(ListSavedBlocks::class)->callAction(TestAction::make(DeleteAction::class)->table($unused));
        $this->assertModelMissing($unused);
    }

    public function test_save_to_library_links_the_block_on_the_page(): void
    {
        $page = $this->page(['blocks' => [self::block('call_to_action', ['heading' => 'Join us'])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $key = array_key_first($component->get('data.blocks'));

        $component
            ->callAction(TestAction::make('saveToLibrary')->schemaComponent('blocks')->arguments(['item' => $key]), ['name' => 'Join CTA', 'link' => true])
            ->assertHasNoActionErrors()
            ->assertNotified('Saved to the block library')
            ->call('save')
            ->assertHasNoFormErrors();

        $item = SavedBlock::query()->sole();
        $this->assertSame(['Join CTA', 'call_to_action', 'Join us'], [$item->name, $item->type, $item->block()['data']['heading']]);
        $this->assertSame([self::linked($item)], array_values($page->fresh()->blocks));
        $this->get('/about-us')->assertSee('Join us');
    }

    public function test_save_to_library_without_linking_keeps_the_block_and_works_inside_a_row(): void
    {
        $page = $this->page(['blocks' => [self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::block('text', ['content' => '<p>Row text</p>'])]]]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $sectionKey = array_key_first($component->get('data.blocks'));

        $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]));
        $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
        $innerKey = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.blocks"));

        $component
            ->mountAction([
                TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]),
                TestAction::make('saveToLibrary')->schemaComponent("rows.{$rowKey}.blocks", 'mountedActionSchema0')->arguments(['item' => $innerKey]),
            ])
            ->setActionData(['name' => 'Row text', 'link' => false])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('text', SavedBlock::query()->sole()->type);
        $this->assertSame('text', $component->get("mountedActions.0.data.rows.{$rowKey}.blocks.{$innerKey}.type"));
    }

    public function test_make_an_editable_copy_swaps_the_link_for_the_library_content(): void
    {
        $item = $this->item('Two columns', 'section', ['rows' => ['row' => ['layout' => '2', 'blocks' => ['inner' => self::block('text', ['content' => '<p>Copied</p>'])]]]]);
        $page = $this->page(['blocks' => [self::linked($item)]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $key = array_key_first($component->get('data.blocks'));

        $component
            ->assertActionHidden(TestAction::make('saveToLibrary')->schemaComponent('blocks')->arguments(['item' => $key]))
            ->callAction(TestAction::make('copySavedBlock')->schemaComponent('blocks')->arguments(['item' => $key]))
            ->call('save')
            ->assertHasNoFormErrors();

        $block = array_values($page->fresh()->blocks)[0];
        $this->assertSame('section', $block['type']);
        $this->assertStringContainsString('Copied', array_values(array_values($block['data']['rows'])[0]['blocks'])[0]['data']['content']);
        $this->assertNotContains('row', array_keys($block['data']['rows']), 'The copy gets its own row keys.');
        $this->assertSame([], app(BlockLibrary::class)->usedOn($item)->all());
    }

    public function test_the_library_editor_offers_every_block_except_a_link_and_no_library_actions(): void
    {
        $component = Livewire::test(CreateSavedBlock::class);
        $builder = $component->instance()->form->getComponent('blocks');
        $names = array_map(fn ($block) => $block->getName(), $builder->getBlocks());

        $this->assertContains('section', $names);
        $this->assertContains('call_to_action', $names);
        $this->assertNotContains('saved', $names);

        $component
            ->fillForm(['name' => 'Banner', 'blocks' => ['one' => self::block('banner', ['heading' => 'Hello'])]])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = SavedBlock::query()->sole();
        $this->assertSame('banner', $item->type);

        Livewire::test(EditSavedBlock::class, ['record' => $item->getRouteKey()])
            ->assertActionHidden(TestAction::make('saveToLibrary')->schemaComponent('blocks')->arguments(['item' => array_key_first($item->blocks)]));
    }

    public function test_a_problem_in_a_library_block_is_named(): void
    {
        Livewire::test(CreateSavedBlock::class)
            ->fillForm(['name' => 'Broken', 'blocks' => ['one' => self::block('call_to_action', ['heading' => ''])]])
            ->call('create')
            ->assertNotified('The library block wasn\'t saved');

        $this->assertSame(0, SavedBlock::query()->count());
    }

    public function test_images_shared_between_pages_and_the_library_are_kept_until_nothing_uses_them(): void
    {
        // Only the current version is kept, so an image removed on save has no older revision still using it.
        config(['site-builder.pages.revisions' => 1]);

        foreach (['shared.jpg', 'library.jpg'] as $file) {
            Storage::disk('public')->put("site-builder/pages/{$file}", 'image');
        }

        $page = $this->page(['blocks' => [self::block('image_text', ['image' => 'site-builder/pages/shared.jpg'])]]);
        $item = $this->item('Image', 'image_text', ['image' => 'site-builder/pages/shared.jpg']);

        // Save to library with "link": the page no longer holds the image itself, but the library does.
        $page->update(['blocks' => [self::linked($item)]]);
        Storage::disk('public')->assertExists('site-builder/pages/shared.jpg');

        // The item changes image: the old one goes, as nothing else uses it.
        $item->update(['blocks' => [self::block('image_text', ['image' => 'site-builder/pages/library.jpg'])]]);
        Storage::disk('public')->assertMissing('site-builder/pages/shared.jpg');

        // An editable copy shares the library's image, so deleting the item keeps it.
        $page->update(['blocks' => [self::block('image_text', ['image' => 'site-builder/pages/library.jpg'])]]);
        $item->delete();
        Storage::disk('public')->assertExists('site-builder/pages/library.jpg');

        $page->forceDelete();
        Storage::disk('public')->assertMissing('site-builder/pages/library.jpg');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function item(string $name, string $type, array $data): SavedBlock
    {
        return app(BlockLibrary::class)->save($name, $type, $data);
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private static function linked(SavedBlock $item): array
    {
        return self::block('saved', ['saved_block_id' => $item->getKey()]);
    }
}
