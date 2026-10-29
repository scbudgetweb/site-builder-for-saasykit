<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockTree;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\LibraryVisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

/**
 * The block library in the visual builder: an item edited on its own in the theme, only inside its one block, and
 * saved (changing every page that uses it) with a warning first.
 */
class LibraryVisualEditorTest extends PageTestCase
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
        $this->actingAs($this->user());
    }

    /**
     * A library Section with one stacked row holding "Opening times", used on a page.
     */
    private function item(): SavedBlock
    {
        $item = app(BlockLibrary::class)->save('Opening times', 'section', ['rows' => [
            ['layout' => 'stacked', 'blocks' => [self::block('text', ['content' => '<p>Opening times</p>'])]],
        ]]);

        $this->page(['blocks' => [self::block('saved', ['saved_block_id' => $item->id])]]);

        return $item->fresh();
    }

    public function test_a_library_item_opens_in_the_visual_builder_on_its_own(): void
    {
        $item = $this->item();
        $draft = app(VisualDrafts::class)->open($item, $this->user());

        $this->get(SavedBlockResource::getUrl('visual', ['record' => $item], panel: 'admin'))
            ->assertSuccessful()
            ->assertSee('/library/'.$item->id.'/canvas/', false);

        $this->get($draft->canvasUrl())
            ->assertSuccessful()
            ->assertSee('Opening times')
            ->assertSee('data-sb-node="row"', false);

        $this->actingAs($this->user('someone@example.com'));
        $this->withExceptionHandling()->get(SavedBlockResource::getUrl('visual', ['record' => $item], panel: 'admin'))->assertForbidden();
    }

    public function test_changes_stay_in_the_draft_until_saved_and_save_warns_about_the_pages_using_it(): void
    {
        $item = $this->item();
        $inner = BlockTree::locate($item->blocks, array_values(array_values($item->blocks[0]['data']['rows'])[0]['columns'])[0]['blocks'][0]['uid'] ?? '');
        $innerUid = $inner['node']['uid'];

        $component = Livewire::test(LibraryVisualEditor::class, ['record' => $item->getRouteKey()])
            ->callAction(TestAction::make('editBlock')->arguments(['uid' => $innerUid]), ['content' => '<p>Open every day</p>'])
            ->assertHasNoActionErrors();

        $this->get('/about-us')->assertSee('Opening times')->assertDontSee('Open every day');

        $this->assertTrue($component->instance()->getAction('publish')->isConfirmationRequired());
        $this->assertStringContainsString('used on 1 page', (string) $component->instance()->getAction('publish')->getModalDescription());

        $component->callAction('publish')->assertNotified('Saved to the block library');

        $this->get('/about-us')->assertSee('Open every day');
        $this->assertNull(app(VisualDrafts::class)->find($item, $this->user()));
    }

    public function test_only_whats_inside_the_item_can_be_added_or_moved(): void
    {
        $item = $this->item();
        $sectionUid = $item->blocks[0]['uid'];
        $row = array_values($item->blocks[0]['data']['rows'])[0];
        $columnUid = array_values($row['columns'])[0]['uid'];

        Livewire::test(LibraryVisualEditor::class, ['record' => $item->getRouteKey()])
            ->assertSet('isPreviewing', false)
            ->call('deleteNode', $sectionUid)
            ->assertNotified('That can\'t be done there.')
            ->call('duplicateNode', $sectionUid)
            ->callAction(TestAction::make('addBlock')->arguments(['target' => $sectionUid, 'position' => 'after']), ['type' => 'text']);

        $this->assertFalse(app(VisualDrafts::class)->hasChanges(app(VisualDrafts::class)->open($item, $this->user()), $item->fresh()));

        Livewire::test(LibraryVisualEditor::class, ['record' => $item->getRouteKey()])
            ->callAction(TestAction::make('addBlock')->arguments(['target' => $columnUid, 'position' => 'in']), ['type' => 'call_to_action'])
            ->assertSet('mountedActions.0.name', 'editBlock');

        $blocks = app(VisualDrafts::class)->open($item, $this->user())->blocks;
        $this->assertCount(1, $blocks, 'Still one block.');
        $this->assertSame(['text', 'call_to_action'], array_column(array_values(array_values(array_values($blocks[0]['data']['rows'])[0]['columns'])[0]['blocks']), 'type'));

        $this->assertFalse(Livewire::test(LibraryVisualEditor::class, ['record' => $item->getRouteKey()])->instance()->getAction('revisions')->isVisible(), 'Library items have no revisions.');
    }
}
