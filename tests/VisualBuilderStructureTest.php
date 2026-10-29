<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockTree;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

/**
 * The visual builder's stage 2: adding, duplicating, deleting and moving blocks and rows (by uid, on the server), and
 * row and column settings.
 */
class VisualBuilderStructureTest extends PageTestCase
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
     * Text "Intro", then a Section with a two-column row (A | B) and a stacked row (C).
     */
    private function structurePage(): Page
    {
        return $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Intro</p>']),
            self::block('section', ['rows' => [
                ['layout' => '2', 'columns' => [
                    ['blocks' => [self::block('text', ['content' => '<p>A</p>'])]],
                    ['blocks' => [self::block('text', ['content' => '<p>B</p>'])]],
                ]],
                ['layout' => 'stacked', 'blocks' => [self::block('text', ['content' => '<p>C</p>'])]],
            ]]),
        ]]);
    }

    /**
     * The uids in a page's blocks, by what they hold: "Intro", "A", "B", "C", "section", "row1", "row2", "col1", "col2", "col3".
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<string, string>
     */
    private static function uids(array $blocks): array
    {
        $uids = ['Intro' => $blocks[0]['uid'], 'section' => $blocks[1]['uid']];
        $rows = array_values($blocks[1]['data']['rows']);

        foreach ($rows as $rowIndex => $row) {
            $uids['row'.($rowIndex + 1)] = $row['uid'];

            foreach (array_values($row['columns']) as $column) {
                $uids['col'.(count(array_filter(array_keys($uids), fn (string $key): bool => str_starts_with($key, 'col'))) + 1)] = $column['uid'];

                foreach ($column['blocks'] as $block) {
                    $uids[strip_tags($block['data']['content'])] = $block['uid'];
                }
            }
        }

        return $uids;
    }

    /**
     * The text of each block, in page order, with "|" between columns and "/" between rows, e.g. "Intro [A | B / C]".
     *
     * @param  array<int|string, mixed>  $blocks
     */
    private static function outline(array $blocks): string
    {
        return implode(' ', array_map(function (array $block): string {
            if ($block['type'] !== 'section') {
                return strip_tags((string) ($block['data']['content'] ?? $block['type']));
            }

            return '['.implode(' / ', array_map(
                fn (array $row): string => implode(' | ', array_map(fn (array $column): string => self::outline(array_values($column['blocks'])) ?: '-', array_values($row['columns']))),
                array_values($block['data']['rows'] ?? []),
            )).']';
        }, array_values($blocks)));
    }

    private function draftOutline(Page $page): string
    {
        return self::outline(app(VisualDrafts::class)->open($page, $this->user())->blocks ?? []);
    }

    private function editor(Page $page): Testable
    {
        return Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()]);
    }

    public function test_blocks_and_rows_move_insert_and_copy_by_uid(): void
    {
        $blocks = $this->structurePage()->blocks;
        $u = self::uids($blocks);

        $this->assertSame('Intro [A | B / C]', self::outline($blocks));
        $this->assertSame('[A | B / C] Intro', self::outline(BlockTree::move($blocks, $u['Intro'], $u['section'], 'after')));
        $this->assertSame('[- | B A / C] Intro', self::outline(BlockTree::move(BlockTree::move($blocks, $u['Intro'], $u['section'], 'after'), $u['A'], $u['B'], 'after')), 'After a block in another column: into that column.');
        $this->assertSame('Intro [A | B C / -]', self::outline(BlockTree::move($blocks, $u['C'], $u['col2'], 'in')), 'Into a column, at its end.');
        $this->assertSame('[A | B / C Intro]', self::outline(BlockTree::move($blocks, $u['Intro'], $u['C'], 'after')), 'From the page into a stacked row.');
        $this->assertSame('Intro [C / A | B]', self::outline(BlockTree::move($blocks, $u['row2'], $u['row1'], 'before')), 'Rows move within a Section.');
        $this->assertSame('Intro [- | B A / C]', self::outline(BlockTree::step($blocks, $u['A'], 'right')), 'To the end of the next column.');
        $this->assertSame('[A | B / C] Intro', self::outline(BlockTree::step($blocks, $u['Intro'], 'down')));
        $this->assertNull(BlockTree::step($blocks, $u['Intro'], 'up'), 'Nothing above it.');
        $this->assertNull(BlockTree::step($blocks, $u['B'], 'right'), 'No column to its right.');

        $this->assertNull(BlockTree::move($blocks, $u['section'], $u['A'], 'after'), 'A Section can\'t go into itself.');
        $this->assertNull(BlockTree::move($blocks, $u['section'], $u['col1'], 'in'));
        $this->assertNull(BlockTree::insert($blocks, ['uid' => 'x', 'type' => 'hero', 'data' => []], $u['A'], 'after'), 'No Hero in a column.');
        $this->assertNull(BlockTree::insert($blocks, ['uid' => 'x', 'layout' => 'stacked', 'columns' => []], $u['Intro'], 'after'), 'A row only goes in a Section.');
        $this->assertNull(BlockTree::remove($blocks, $u['col1']), 'A row\'s layout sets its columns.');

        [$copied, $copyUid] = BlockTree::duplicate($blocks, $u['row1']);
        $this->assertSame('Intro [A | B / A | B / C]', self::outline($copied));
        $this->assertNotContains($copyUid, $u);
        $this->assertCount(count(array_unique(self::uids($blocks))) + 1, array_unique([...array_values(self::uids($blocks)), $copyUid]));

        $this->assertSame('Intro [A | - / C]', self::outline(BlockTree::remove($blocks, $u['B'])));
        $this->assertSame('Intro [A | B]', self::outline(BlockTree::remove($blocks, $u['row2'])));
    }

    public function test_adding_a_block_opens_its_settings_and_columns_only_offer_what_fits(): void
    {
        $page = $this->structurePage();
        $u = self::uids($page->blocks);

        $this->editor($page)
            ->mountAction(TestAction::make('addBlock')->arguments(['target' => $u['col1'], 'position' => 'in']))
            ->assertFormFieldExists('type', 'mountedActionSchema0', fn ($field): bool => ! isset($field->getOptions()['section']) && ! isset($field->getOptions()['hero']) && isset($field->getOptions()['call_to_action']))
            ->setActionData(['type' => 'call_to_action'])
            ->callMountedAction()
            ->assertSet('mountedActions.0.name', 'editBlock');

        $this->assertSame('Intro [A call_to_action | B / C]', $this->draftOutline($page));

        $this->editor($page)
            ->mountAction(TestAction::make('addBlock')->arguments(['target' => $u['Intro'], 'position' => 'before']))
            ->assertFormFieldExists('type', 'mountedActionSchema0', fn ($field): bool => isset($field->getOptions()['section']))
            ->setActionData(['type' => 'section'])
            ->callMountedAction();

        $this->assertStringStartsWith('[] Intro', $this->draftOutline($page));

        $empty = $this->page(['slug' => 'empty']);
        $this->editor($empty)->callAction(TestAction::make('addBlock'), ['type' => 'text'])->assertSet('mountedActions.0.name', 'editBlock');
        $this->assertSame('text', app(VisualDrafts::class)->open($empty, $this->user())->blocks[0]['type']);
    }

    public function test_the_canvas_menus_structure_actions_are_each_one_undoable_change(): void
    {
        $page = $this->structurePage();
        $u = self::uids($page->blocks);

        $this->editor($page)->call('duplicateNode', $u['Intro']);
        $this->assertSame('Intro Intro [A | B / C]', $this->draftOutline($page));

        $this->editor($page)->call('deleteNode', $u['A'])->assertNotified('Deleted');
        $this->assertSame('Intro Intro [- | B / C]', $this->draftOutline($page));

        $this->editor($page)->call('stepNode', $u['B'], 'left');
        $this->assertSame('Intro Intro [B | - / C]', $this->draftOutline($page));

        $this->editor($page)->call('dropNode', $u['C'], $u['B'], 'before');
        $this->assertSame('Intro Intro [C B | - / -]', $this->draftOutline($page));

        $this->editor($page)->call('addRow', $u['row1']);
        $this->assertSame('Intro Intro [C B | - / - / -]', $this->draftOutline($page));

        $this->editor($page)->call('dropNode', $u['section'], $u['C'], 'after')->assertNotified('That can\'t be done there.');

        $this->editor($page)->call('undo');
        $this->assertSame('Intro Intro [C B | - / -]', $this->draftOutline($page), 'Undo takes back the last one.');
    }

    public function test_row_and_column_settings_open_on_their_own(): void
    {
        $page = $this->structurePage();
        $u = self::uids($page->blocks);

        $this->editor($page)
            ->callAction(TestAction::make('editRow')->arguments(['uid' => $u['row1']]), ['layout' => 'stacked', 'gap' => 'none'])
            ->assertHasNoActionErrors();

        $this->assertSame('Intro [A B / C]', $this->draftOutline($page), 'Fewer columns: the extra column\'s blocks join the last one kept.');

        $this->editor($page)
            ->callAction(TestAction::make('editColumn')->arguments(['uid' => $u['col3']]), ['background' => 'dark'])
            ->assertHasNoActionErrors();

        $column = BlockTree::find(app(VisualDrafts::class)->open($page, $this->user())->blocks, $u['col3'])['node'];
        $this->assertSame(['dark', 'C'], [$column['background'], strip_tags(array_values($column['blocks'])[0]['data']['content'])]);

        $this->editor($page)->assertActionHidden(TestAction::make('editRow')->arguments(['uid' => $u['Intro']]));
        $this->editor($page)->assertActionHidden(TestAction::make('editColumn')->arguments(['uid' => $u['row1']]));
    }

    public function test_a_block_with_nothing_to_show_and_an_empty_page_can_still_be_clicked_on_the_canvas(): void
    {
        $page = $this->page(['blocks' => [self::block('faq', ['items' => []])]]);
        $draft = app(VisualDrafts::class)->open($page, $this->user());

        $this->get($draft->canvasUrl())->assertSee('data-sb-node="block" data-sb-uid="'.$page->blocks[0]['uid'].'"', false);
        $this->get('/about-us')->assertDontSee('nothing to show yet');

        $empty = $this->page(['slug' => 'empty']);
        $this->get(app(VisualDrafts::class)->open($empty, $this->user())->canvasUrl())->assertSee('data-sb-add-first', false);
    }
}
