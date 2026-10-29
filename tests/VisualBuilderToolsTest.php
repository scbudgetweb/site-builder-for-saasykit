<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockTree;
use ScBudgetWeb\SiteBuilder\Blocks\Core\FeaturesBlock;
use ScBudgetWeb\SiteBuilder\Blocks\StyleClipboard;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

/**
 * The visual builder's tools: undo and redo, revisions, and copying and pasting styles.
 */
class VisualBuilderToolsTest extends PageTestCase
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

    private const HEADING_STYLE = ['heading' => ['colour' => '#0a3d2e', 'weight' => '700']];

    /**
     * Two calls to action (one in a Section) and a text block.
     */
    private function stylePage(): Page
    {
        return $this->page(['blocks' => [
            self::block('call_to_action', ['heading' => 'Book a room', 'design' => ['typography' => self::HEADING_STYLE], 'appearance' => ['background' => 'dark', 'anchor' => 'book']]),
            self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::block('call_to_action', ['heading' => 'Our prices', 'appearance' => ['background' => 'light']])]]]]),
            self::block('text', ['content' => '<p>Opening times</p>']),
        ]]);
    }

    private function editor(Page $page): Testable
    {
        return Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()]);
    }

    private function draftBlocks(Page $page): array
    {
        return app(VisualDrafts::class)->open($page, $this->user())->blocks ?? [];
    }

    private function nestedUid(Page $page): string
    {
        return array_values(array_values($page->blocks[1]['data']['rows'])[0]['columns'])[0]['blocks'][array_key_first(array_values(array_values($page->blocks[1]['data']['rows'])[0]['columns'])[0]['blocks'])]['uid'];
    }

    public function test_undo_and_redo_from_the_toolbar_or_the_keyboard(): void
    {
        $page = $this->stylePage();
        $drafts = app(VisualDrafts::class);
        $uid = $page->blocks[2]['uid'];
        $drafts->change($drafts->open($page, $this->user()), BlockTree::update($page->blocks, $uid, fn (array $block): array => [...$block, 'data' => ['content' => '<p>Changed</p>']]));

        $this->editor($page)
            ->assertActionEnabled('undo')
            ->assertActionDisabled('redo')
            ->call('undo')
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => str_contains($params['html'], 'Opening times'))
            ->assertActionEnabled('redo')
            ->call('redo')
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => str_contains($params['html'], 'Changed'));

        $this->assertSame('<p>Changed</p>', BlockTree::find($this->draftBlocks($page), $uid)['node']['data']['content']);
    }

    public function test_a_revision_restores_into_the_draft_and_undo_takes_it_back(): void
    {
        $page = $this->stylePage();
        $first = $page->revisions()->sole();
        $page->update(['blocks' => [self::block('text', ['content' => '<p>Newer version</p>'])]]);

        $this->editor($page)
            ->mountAction('revisions')
            ->assertActionMounted('revisions')
            ->call('restoreRevision', $first->id)
            ->assertNotified('Revision loaded')
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => str_contains($params['html'], 'Book a room'));

        $this->assertStringContainsString('Newer version', json_encode($page->fresh()->blocks), 'Nothing is live until Publish.');
        $this->assertCount(3, $this->draftBlocks($page));

        $this->editor($page)->call('undo');
        $this->assertStringContainsString('Newer version', json_encode($this->draftBlocks($page)));
    }

    public function test_style_groups_copy_only_style_settings_and_paste_replaces_them(): void
    {
        $data = [
            'heading' => 'Book a room',
            'appearance' => ['background' => 'gradient', 'gradient_start' => '#000000', 'gradient_end' => '#ffffff', 'anchor' => 'book', 'heading_size' => 'xl'],
            'design' => ['typography' => self::HEADING_STYLE, 'spacing' => ['margin' => ['desktop' => ['top' => '8px']]]],
        ];

        $this->assertSame(
            ['appearance' => ['background' => 'gradient', 'gradient_start' => '#000000', 'gradient_end' => '#ffffff']],
            StyleClipboard::extract($data, StyleClipboard::paths('background')),
        );

        $target = ['heading' => 'Our prices', 'appearance' => ['background' => 'light', 'gradient_middle' => '#cccccc', 'anchor' => 'prices']];
        $pasted = StyleClipboard::apply($target, StyleClipboard::extract($data, StyleClipboard::paths('all')), StyleClipboard::paths('all'));

        $this->assertSame('Our prices', $pasted['heading'], 'Content stays.');
        $this->assertSame('prices', $pasted['appearance']['anchor'], 'So does the anchor.');
        $this->assertSame('gradient', $pasted['appearance']['background']);
        $this->assertArrayNotHasKey('gradient_middle', $pasted['appearance'], 'A setting the copy doesn\'t have is cleared.');
        $this->assertSame(self::HEADING_STYLE, $pasted['design']['typography']);

        $this->assertContains('card', FeaturesBlock::ownStyleKeys(), 'A block\'s own style settings, e.g. its cards.');
        $this->assertContains('columns', FeaturesBlock::ownStyleKeys());
    }

    public function test_copy_a_blocks_style_and_paste_it_on_another(): void
    {
        $page = $this->stylePage();
        $nested = $this->nestedUid($page);

        $this->editor($page)
            ->call('copyStyle', $page->blocks[0]['uid'], 'heading')
            ->assertDispatched('site-builder-style-copied', label: 'Heading text (Call to action)')
            ->call('pasteStyle', $nested)
            ->assertNotified('Pasted: Heading text (Call to action)');

        $pasted = BlockTree::find($this->draftBlocks($page), $nested)['node']['data'];
        $this->assertSame(self::HEADING_STYLE, $pasted['design']['typography']);
        $this->assertSame(['background' => 'light'], $pasted['appearance'], 'Only the heading group is pasted.');
        $this->assertSame('Our prices', $pasted['heading']);

        $this->editor($page)->call('undo');
        $this->assertArrayNotHasKey('design', BlockTree::find($this->draftBlocks($page), $nested)['node']['data'], 'Undo takes the paste back.');
    }

    public function test_all_styles_between_types_skip_the_blocks_own_settings(): void
    {
        $page = $this->page(['blocks' => [
            self::block('features', ['card' => ['background' => 'light'], 'columns' => 2, 'appearance' => ['background' => 'dark']]),
            self::block('text', ['content' => '<p>Hi</p>']),
        ]]);

        $this->editor($page)->call('copyStyle', $page->blocks[0]['uid'], 'all')->call('pasteStyle', $page->blocks[1]['uid']);

        $text = BlockTree::find($this->draftBlocks($page), $page->blocks[1]['uid'])['node']['data'];
        $this->assertSame('dark', $text['appearance']['background']);
        $this->assertArrayNotHasKey('card', $text);
        $this->assertArrayNotHasKey('columns', $text);
    }

    public function test_give_all_blocks_of_a_type_one_blocks_style_in_one_undoable_change(): void
    {
        $page = $this->stylePage();
        $nested = $this->nestedUid($page);

        $this->editor($page)
            ->call('applyStyleToAll', $page->blocks[0]['uid'], 'heading')
            ->assertNotified('Heading text applied to 1 other Call to action block');

        $blocks = $this->draftBlocks($page);
        $this->assertSame(self::HEADING_STYLE, BlockTree::find($blocks, $nested)['node']['data']['design']['typography'], 'Including blocks in a Section.');
        $this->assertArrayNotHasKey('design', BlockTree::find($blocks, $page->blocks[2]['uid'])['node']['data'], 'Other types are left alone.');

        $this->editor($page)->call('undo');
        $this->assertArrayNotHasKey('design', BlockTree::find($this->draftBlocks($page), $nested)['node']['data']);

        $this->editor($page)->call('applyStyleToAll', $page->blocks[2]['uid'], 'all')->assertNotified('There are no other Text blocks on this page.');
    }

    public function test_a_library_block_and_unknown_groups_are_left_alone(): void
    {
        $item = app(BlockLibrary::class)->save('Book', 'call_to_action', ['heading' => 'Book']);
        $page = $this->page(['blocks' => [
            self::block('saved', ['saved_block_id' => $item->id]),
            self::block('call_to_action', ['heading' => 'Book a room', 'design' => ['typography' => self::HEADING_STYLE]]),
        ]]);

        $this->editor($page)
            ->call('copyStyle', $page->blocks[1]['uid'], 'nonsense')
            ->assertNotDispatched('site-builder-style-copied')
            ->call('copyStyle', $page->blocks[1]['uid'], 'heading')
            ->call('pasteStyle', $page->blocks[0]['uid'])
            ->assertNotified('Copied: Heading text (Call to action)');

        $this->assertSame(['saved_block_id' => $item->id], $this->draftBlocks($page)[0]['data']);
        $this->assertFalse($this->editor($page)->instance()->canvasNodes()[$page->blocks[0]['uid']]['hasStyle']);
    }
}
