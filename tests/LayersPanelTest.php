<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockTree;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

/**
 * The visual builder's layers panel: the page as a tree of Sections, rows, columns and blocks; hiding one on every
 * screen; renaming one (its admin label). Selecting and moving use the canvas's own actions (VisualBuilderStructureTest).
 */
class LayersPanelTest extends PageTestCase
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
     * A Hero, then a Section with a two-column row (a Heading | a Text) and a named stacked row (a Text).
     */
    private function layeredPage(): Page
    {
        return $this->page(['blocks' => [
            self::block('hero', ['heading' => 'Welcome to the Village Hall']),
            self::block('section', ['rows' => [
                ['layout' => '2', 'columns' => [
                    ['blocks' => [self::block('heading', ['heading' => 'Rooms to hire', 'admin_label' => 'Rooms title'])]],
                    ['blocks' => [self::block('text', ['content' => '<p>The main hall seats   <strong>120</strong> people.</p><p>Book online.</p>'])]],
                ]],
                ['layout' => 'stacked', 'admin_label' => 'Opening hours', 'blocks' => [self::block('text', ['content' => '<p>Open every day</p>'])]],
            ]]),
        ]]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function layers(Page $page): array
    {
        return Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->instance()->layers();
    }

    /**
     * @return array<string, mixed>
     */
    private function layer(Page $page, string $label): array
    {
        return collect($this->layers($page))->firstWhere('label', $label) ?? [];
    }

    public function test_the_page_is_listed_as_a_tree(): void
    {
        $layers = $this->layers($this->layeredPage());

        $this->assertSame(
            ['Hero', 'Section (rows of blocks)', 'Row 1', 'Column 1', 'Rooms title', 'Column 2', 'Text', 'Opening hours', 'Column 1', 'Text'],
            array_column($layers, 'label'),
        );
        $this->assertSame(['block', 'block', 'row', 'column', 'block', 'column', 'block', 'row', 'column', 'block'], array_column($layers, 'kind'));
        $this->assertSame([0, 0, 1, 2, 3, 2, 3, 1, 2, 3], array_column($layers, 'depth'));
        $this->assertSame([$layers[1]['uid'], $layers[2]['uid'], $layers[5]['uid']], $layers[6]['ancestors'], 'Folding a Section, row or column hides what\'s inside.');

        $this->assertSame('Welcome to the Village Hall', $layers[0]['detail'], 'A few words to tell blocks apart.');
        $this->assertSame('The main hall seats 120 people. Book onl...', $layers[6]['detail'], 'Words in different tags stay apart; cut to 40 characters.');
        $this->assertSame('Two equal columns', $layers[2]['detail']);
        $this->assertSame('1 block', $layers[3]['detail']);
        $this->assertTrue($layers[1]['isSection']);
        $this->assertTrue($layers[1]['hasChildren']);
        $this->assertFalse($layers[0]['hasChildren']);
        $this->assertFalse($layers[0]['hidden']);
    }

    public function test_a_layer_can_be_hidden_on_every_screen_and_shown_again(): void
    {
        $page = $this->layeredPage();
        $text = $this->layer($page, 'Text');
        $row = $this->layer($page, 'Opening hours');

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->call('toggleLayerHidden', $text['uid'])
            ->assertDispatched('site-builder-canvas-updated', fn (string $event, array $params): bool => collect($params['layers'])->firstWhere('uid', $text['uid'])['hidden'] === true)
            ->call('toggleLayerHidden', $row['uid']);

        $blocks = app(VisualDrafts::class)->open($page, $this->user())->blocks;
        $this->assertSame(['phone', 'tablet', 'desktop'], BlockTree::find($blocks, $text['uid'])['node']['data']['design']['effects']['hide_on']);
        $this->assertSame(['phone', 'tablet', 'desktop'], BlockTree::find($blocks, $row['uid'])['node']['design']['effects']['hide_on']);
        $this->assertSame([], $page->fresh()->blocks[1]['data']['rows'][0]['columns'][1]['blocks'][0]['data']['design'] ?? [], 'Only in the draft until it\'s published.');

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->call('toggleLayerHidden', $text['uid']);
        $this->assertSame([], BlockTree::find(app(VisualDrafts::class)->open($page, $this->user())->blocks, $text['uid'])['node']['data']['design']['effects']['hide_on']);
        $this->assertTrue($this->layer($page, 'Opening hours')['hidden']);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->call('toggleLayerHidden', 'no-such-uid')->assertNotified('That can\'t be done there.');
    }

    public function test_hidden_on_some_screens_shows_as_partly_hidden(): void
    {
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Desktop only</p>', 'design' => ['effects' => ['hide_on' => ['phone', 'tablet']]]])]]);
        $layer = $this->layers($page)[0];

        $this->assertFalse($layer['hidden']);
        $this->assertTrue($layer['partlyHidden']);
    }

    public function test_a_layer_can_be_renamed(): void
    {
        $page = $this->layeredPage();
        $text = $this->layer($page, 'Text');
        $row = $this->layer($page, 'Row 1');

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->call('renameLayer', $text['uid'], '  Hall   size ')
            ->call('renameLayer', $row['uid'], str_repeat('Long name ', 10));

        $blocks = app(VisualDrafts::class)->open($page, $this->user())->blocks;
        $this->assertSame('Hall size', BlockTree::find($blocks, $text['uid'])['node']['data']['admin_label']);
        $this->assertLessThanOrEqual(60, mb_strlen(BlockTree::find($blocks, $row['uid'])['node']['admin_label']), 'Cut to 60 characters.');
        $this->assertNotEmpty($this->layer($page, 'Hall size'));

        $title = $this->layer($page, 'Rooms title');
        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->call('renameLayer', $title['uid'], ' ');
        $this->assertArrayNotHasKey('admin_label', BlockTree::find(app(VisualDrafts::class)->open($page, $this->user())->blocks, $title['uid'])['node']['data'], 'Blank: its usual name again.');
        $this->assertSame('Heading', collect($this->layers($page))->firstWhere('uid', $title['uid'])['label']);
    }

    public function test_a_linked_library_block_is_one_layer_that_cant_be_hidden_here(): void
    {
        $saved = SavedBlock::query()->create(['name' => 'Opening times', 'blocks' => [self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::block('text', ['content' => '<p>Open</p>'])]]]])]]);
        $page = $this->page(['blocks' => [self::block('saved', ['saved_block_id' => $saved->getKey()])]]);
        $layers = $this->layers($page);

        $this->assertCount(1, $layers);
        $this->assertTrue($layers[0]['isLinked']);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->call('toggleLayerHidden', $layers[0]['uid'])->assertNotified('That can\'t be done there.');
    }

    public function test_the_visual_builder_has_the_layers_panel(): void
    {
        $page = $this->layeredPage();

        $this->get(PageResource::getUrl('visual', ['record' => $page]))
            ->assertSuccessful()
            ->assertSee('toggleLayers()', false)
            ->assertSee('role="tree"', false)
            ->assertSee('Click to edit, double-click to rename, drag to move.');
    }
}
