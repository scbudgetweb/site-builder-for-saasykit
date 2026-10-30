<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockTree;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\ListPages;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\PageDraft;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

/**
 * The visual builder (stage 1): drafts saved as you go, the canvas, editing a block from it, and publishing.
 */
class VisualEditorTest extends PageTestCase
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
     * A text block and a Section with a call to action in its second column.
     */
    private function sectionPage(): Page
    {
        return $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Welcome to the Village Hall</p>']),
            self::block('section', ['rows' => [['layout' => '2', 'columns' => [
                ['blocks' => []],
                ['blocks' => [self::block('call_to_action', ['heading' => 'Book a room'])]],
            ]]]]),
        ]]);
    }

    private function draft(Page $page): PageDraft
    {
        return app(VisualDrafts::class)->open($page, $this->user());
    }

    public function test_a_draft_starts_as_the_page_and_keeps_changes_for_undo_and_redo(): void
    {
        $page = $this->sectionPage();
        $drafts = app(VisualDrafts::class);
        $draft = $this->draft($page);

        $this->assertEquals($page->blocks, $draft->blocks);
        $this->assertFalse($drafts->hasChanges($draft, $page));
        $this->assertFalse($drafts->isOutOfDate($draft, $page));

        $uid = $draft->blocks[0]['uid'];
        $drafts->change($draft, BlockTree::update($draft->blocks, $uid, fn (array $block): array => [...$block, 'data' => ['content' => '<p>Changed</p>']]));

        $this->assertTrue($drafts->hasChanges($draft->fresh(), $page->fresh()));
        $this->assertStringContainsString('Welcome', $page->fresh()->blocks[0]['data']['content'], 'The page itself is unchanged.');

        $drafts->undo($draft);
        $this->assertFalse($drafts->hasChanges($draft->fresh(), $page->fresh()));
        $this->assertTrue($drafts->canRedo($draft));

        $drafts->redo($draft);
        $this->assertSame('<p>Changed</p>', $draft->fresh()->blocks[0]['data']['content']);
        $this->assertSame($uid, $draft->fresh()->blocks[0]['uid'], 'Uids stay the same through undo and redo.');
    }

    public function test_block_tree_finds_and_changes_blocks_rows_and_columns_by_uid(): void
    {
        $blocks = $this->sectionPage()->blocks;
        $row = $blocks[1]['data']['rows'][array_key_first($blocks[1]['data']['rows'])];
        $column = array_values($row['columns'])[1];
        $inner = array_values($column['blocks'])[0];

        $this->assertSame(['block', false], [BlockTree::find($blocks, $blocks[0]['uid'])['kind'], BlockTree::find($blocks, $blocks[0]['uid'])['isNested']]);
        $this->assertSame('row', BlockTree::find($blocks, $row['uid'])['kind']);
        $this->assertSame('column', BlockTree::find($blocks, $column['uid'])['kind']);
        $this->assertSame(['call_to_action', true], [BlockTree::find($blocks, $inner['uid'])['node']['type'], BlockTree::find($blocks, $inner['uid'])['isNested']]);
        $this->assertNull(BlockTree::find($blocks, 'no-such-uid'));

        $changed = BlockTree::update($blocks, $inner['uid'], fn (array $block): array => [...$block, 'data' => ['heading' => 'Book the main hall']]);
        $this->assertSame('Book the main hall', BlockTree::find($changed, $inner['uid'])['node']['data']['heading']);
        $this->assertSame($blocks, BlockTree::update($blocks, 'no-such-uid', fn (array $block): array => []));
    }

    public function test_the_canvas_shows_the_draft_marked_up_and_only_through_a_signed_link(): void
    {
        $page = $this->sectionPage();
        $draft = $this->draft($page);
        app(VisualDrafts::class)->change($draft, BlockTree::update($draft->blocks, $draft->blocks[0]['uid'], fn (array $block): array => [...$block, 'data' => ['content' => '<p>Only in the draft</p>']]));

        $html = $this->get($draft->canvasUrl())
            ->assertSuccessful()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Only in the draft')
            ->assertSee('data-sb-node="block" data-sb-uid="'.$draft->blocks[0]['uid'].'"', false)
            ->assertSee('data-sb-node="row"', false)
            ->assertSee('data-sb-node="column"', false)
            ->assertSee('data-sb-empty', false)
            ->getContent();

        $this->assertStringNotContainsString('Preview of this page', $html);

        $this->get('/about-us')->assertSee('Welcome to the Village Hall')->assertDontSee('data-sb-node', false)->assertDontSee('data-sb-empty', false);

        $this->withExceptionHandling()->get(route('site-builder.pages.canvas', ['page' => $page, 'user' => $draft->user_id]))->assertForbidden();

        // Another page's canvas, or someone without a draft, shows that page as it's saved.
        $other = $this->page(['slug' => 'other', 'blocks' => [self::block('text', ['content' => '<p>The other page</p>'])]]);
        $this->get($this->draft($other)->canvasUrl())->assertSee('The other page')->assertDontSee('Only in the draft');
        $this->get($this->draft($page)->canvasUrl())->assertSee('Only in the draft');
        $this->get(URL::temporarySignedRoute('site-builder.pages.canvas', now()->addHour(), ['page' => $page->id, 'user' => 123]))->assertSee('Welcome to the Village Hall');
    }

    public function test_an_admin_can_open_the_visual_builder_and_others_cannot(): void
    {
        $page = $this->sectionPage();

        $this->get(PageResource::getUrl('visual', ['record' => $page], panel: 'admin'))
            ->assertSuccessful()
            ->assertSee('/canvas/', false)
            ->assertSee(__('Edit as a form'));

        $this->actingAs($this->user('someone@example.com'));
        $this->withExceptionHandling()->get(PageResource::getUrl('visual', ['record' => $page], panel: 'admin'))->assertForbidden();
    }

    public function test_pages_open_in_the_visual_builder_and_a_page_in_the_bin_opens_as_a_form(): void
    {
        $page = $this->sectionPage();

        Livewire::test(ListPages::class)
            ->assertTableActionExists('visual')
            ->assertSeeHtml(PageResource::getUrl('visual', ['record' => $page]));

        $page->delete();

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->assertRedirect(PageResource::getUrl('edit', ['record' => $page]));
    }

    public function test_clicking_a_block_edits_it_in_the_draft_and_sends_the_canvas_only_its_sections_new_html(): void
    {
        $page = $this->sectionPage();
        $inner = array_values(array_values($page->blocks[1]['data']['rows'])[0]['columns'])[1]['blocks'];
        $innerUid = array_values($inner)[0]['uid'];

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $innerUid]))
            ->assertActionMounted('editBlock')
            ->assertSchemaStateSet(['heading' => 'Book a room'], 'mountedActionSchema0')
            ->setActionData(['heading' => 'Book the main hall'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => str_contains($params['html'], 'Book the main hall')
                && ! str_contains($params['html'], 'Welcome to the Village Hall')
                && $params['uid'] === $page->blocks[1]['uid']
                && ($params['nodes'][$innerUid]['kind'] ?? null) === 'block');

        $this->assertSame('Book the main hall', BlockTree::find($this->draft($page)->blocks, $innerUid)['node']['data']['heading']);
        $this->assertSame('Book a room', BlockTree::find($page->fresh()->blocks, $innerUid)['node']['data']['heading'], 'The page changes only when it\'s published.');
    }

    public function test_a_blocks_settings_float_over_the_canvas_and_saving_without_changes_leaves_nothing_to_undo(): void
    {
        $page = $this->sectionPage();
        $uid = $page->blocks[0]['uid'];
        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]));

        $panel = $component->instance()->getMountedAction();
        $this->assertFalse($panel->isModalSlideOver());
        $this->assertSame('sb-floating-panel', $panel->getExtraModalWindowAttributes()['class'] ?? null);
        $this->assertArrayHasKey('data-sb-wide', $component->instance()->getAction([['name' => 'editBlock', 'arguments' => ['uid' => $page->blocks[1]['uid']]]])->getExtraModalWindowAttributes());

        $drafts = app(VisualDrafts::class);
        $draft = $this->draft($page);
        $drafts->change($draft, $draft->blocks);
        $drafts->change($draft, $draft->blocks);

        $this->assertFalse($drafts->canUndo($draft), 'The same blocks change nothing.');
    }

    public function test_the_canvas_previews_a_panels_unsaved_changes_and_cancel_puts_it_back(): void
    {
        $page = $this->sectionPage();
        $uid = $page->blocks[0]['uid'];

        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]))
            ->set('mountedActions.0.data.content', '<p>Typed but not saved</p>')
            ->call('previewBlock')
            ->assertSet('isPreviewing', true)
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => ($params['isPreview'] ?? false) === true
                && $params['uid'] === $uid
                && str_contains($params['html'], 'Typed but not saved')
                && ! str_contains($params['html'], 'Book a room'));

        $this->assertSame(0, PageDraft::query()->count(), 'A preview saves nothing.');

        $component->call('unmountAction')
            ->assertSet('isPreviewing', false)
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => ($params['isPreview'] ?? false) === false && $params['uid'] === null
                && str_contains($params['html'], 'Welcome to the Village Hall')
                && ! str_contains($params['html'], 'Typed but not saved'));
    }

    public function test_only_an_open_block_panel_is_previewed(): void
    {
        Livewire::test(VisualEditor::class, ['record' => $this->sectionPage()->getRouteKey()])
            ->call('previewBlock')
            ->assertNotDispatched('site-builder-canvas-updated')
            ->assertSet('isPreviewing', false);
    }

    public function test_an_unknown_uid_or_a_row_opens_no_block_panel(): void
    {
        $page = $this->sectionPage();
        $rowUid = array_values($page->blocks[1]['data']['rows'])[0]['uid'];

        foreach (['no-such-uid', $rowUid] as $uid) {
            Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
                ->assertActionHidden(TestAction::make('editBlock')->arguments(['uid' => $uid]));
        }

        $this->assertFalse(app(VisualDrafts::class)->hasChanges($this->draft($page), $page->fresh()));
        $this->assertSame(0, PageDraft::query()->count(), 'Opening the builder stores nothing until something changes.');
    }

    public function test_publishing_copies_the_draft_to_the_page_and_records_a_revision(): void
    {
        $page = $this->sectionPage();
        $draft = $this->draft($page);
        $uid = $draft->blocks[0]['uid'];
        app(VisualDrafts::class)->change($draft, BlockTree::update($draft->blocks, $uid, fn (array $block): array => [...$block, 'data' => ['content' => '<p>Published from the builder</p>']]));

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->assertActionEnabled('publish')
            ->callAction('publish')
            ->assertNotified('Published');

        $this->get('/about-us')->assertSee('Published from the builder');
        $this->assertSame(0, PageDraft::query()->count());
        $this->assertSame(2, $page->revisions()->count());
        $this->assertSame($uid, $page->fresh()->blocks[0]['uid']);
    }

    public function test_publish_is_off_without_changes_and_asks_first_when_the_page_was_saved_elsewhere(): void
    {
        $page = $this->sectionPage();

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->assertActionDisabled('publish');

        $draft = $this->draft($page);
        app(VisualDrafts::class)->change($draft, BlockTree::update($draft->blocks, $draft->blocks[0]['uid'], fn (array $block): array => [...$block, 'data' => ['content' => '<p>Mine</p>']]));

        Carbon::setTestNow(now()->addMinute());
        $page->update(['title' => 'Saved in the form editor']);

        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()]);
        $this->assertTrue($component->instance()->getAction('publish')->isConfirmationRequired());
        $this->assertTrue(app(VisualDrafts::class)->isOutOfDate($draft->fresh(), $page->fresh()));

        Carbon::setTestNow();
    }

    public function test_discarding_returns_to_the_page_as_published(): void
    {
        $page = $this->sectionPage();
        $draft = $this->draft($page);
        app(VisualDrafts::class)->change($draft, []);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction('discard')
            ->assertRedirect(PageResource::getUrl('visual', ['record' => $page]));

        $this->assertNull(PageDraft::query()->find($draft->id));
        $this->assertCount(2, $page->fresh()->blocks);
    }

    public function test_the_form_editor_says_when_there_are_unpublished_visual_changes(): void
    {
        $page = $this->sectionPage();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertActionHasLabel('visual', 'Visual builder');

        app(VisualDrafts::class)->change($this->draft($page), []);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertActionHasLabel('visual', 'Visual builder (unpublished changes)');
    }

    public function test_an_image_only_in_a_draft_is_kept(): void
    {
        Storage::disk('public')->put('site-builder/pages/draft.jpg', 'image');
        $page = $this->sectionPage();
        $draft = $this->draft($page);

        app(VisualDrafts::class)->change($draft, [...$draft->blocks, self::block('image', ['image' => 'site-builder/pages/draft.jpg', 'alt' => 'The hall'])]);
        $this->assertTrue(app(PageService::class)->isUploadInUse('site-builder/pages/draft.jpg'));

        // Undone, it's still in the draft's history, so redo can bring it back.
        app(VisualDrafts::class)->undo($draft);
        $this->assertTrue(app(PageService::class)->isUploadInUse('site-builder/pages/draft.jpg'));

        $draft->delete();
        $this->assertFalse(app(PageService::class)->isUploadInUse('site-builder/pages/draft.jpg'));
    }

    public function test_livewire_leaves_the_canvas_component_in_place_when_the_page_changes(): void
    {
        $view = (string) file_get_contents(__DIR__.'/../resources/views/filament/pages/visual-editor.blade.php');

        $this->assertMatchesRegularExpression('/<div\s+wire:ignore\.self\s+x-data="siteBuilderCanvas\(/', $view, 'A new nodes list in x-data would start a canvas component without the iframe, so a moved block would stay where it was until a reload.');
    }
}
