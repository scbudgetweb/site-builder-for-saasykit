<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockTree;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

/**
 * The visual builder's Section, row and column panels: a Section's panel holds its own settings only; a
 * row's lists its columns, each opening in the row's place (saving the row first); a column's has a way back to its row.
 * Rows and columns have a block's settings too: a background image, Link, CSS ID & classes and Admin label.
 */
class PanelNavigationTest extends PageTestCase
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
     * @return array{0: Page, 1: array{section: string, row: string, column: string, column2: string, block: string}}
     */
    private function sectionPage(string $layout = '2'): array
    {
        $page = $this->page(['blocks' => [self::block('section', ['rows' => [
            ['layout' => $layout, 'columns' => [['blocks' => [self::block('text', ['content' => '<p>Opening times</p>'])]], ['blocks' => []]]],
        ]])]]);
        $section = $page->blocks[0];
        $row = $section['data']['rows'][0];

        return [$page, [
            'section' => $section['uid'],
            'row' => $row['uid'],
            'column' => $row['columns'][0]['uid'],
            'column2' => $row['columns'][1]['uid'] ?? '',
            'block' => $row['columns'][0]['blocks'][0]['uid'],
        ]];
    }

    private function draftBlocks(Page $page): array
    {
        return app(VisualDrafts::class)->open($page, $this->user())->blocks;
    }

    public function test_the_trail_runs_from_the_section_down(): void
    {
        [$page, $uids] = $this->sectionPage();

        $kinds = fn (string $uid): array => array_column(BlockTree::trail($page->blocks, $uid), 'kind');

        $this->assertSame(['block', 'row', 'column', 'block'], $kinds($uids['block']));
        $this->assertSame(['block', 'row', 'column'], $kinds($uids['column']));
        $this->assertSame(['block', 'row'], $kinds($uids['row']));
        $this->assertSame(['block'], $kinds($uids['section']));
        $this->assertSame([], BlockTree::trail($page->blocks, 'no-such-uid'));
    }

    public function test_a_sections_panel_holds_only_its_own_settings_and_keeps_its_rows(): void
    {
        [$page, $uids] = $this->sectionPage();

        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uids['section']]));

        $this->assertArrayNotHasKey('rows', $component->instance()->mountedActions[0]['data'], 'No rows in the Section\'s panel.');
        $this->assertIsString($component->instance()->getMountedAction()->getModalHeading(), 'A plain title, no breadcrumb.');

        $component->set('mountedActions.0.data.admin_label', 'Opening hours band')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $draft = $this->draftBlocks($page);
        $this->assertSame('Opening hours band', $draft[0]['data']['admin_label']);
        $this->assertSame($uids['block'], $draft[0]['data']['rows'][0]['columns'][0]['blocks'][0]['uid'], 'Its rows are kept.');
    }

    public function test_a_rows_panel_lists_its_columns_and_opens_one_in_its_place(): void
    {
        [$page, $uids] = $this->sectionPage();

        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editRow')->arguments(['uid' => $uids['row']]));

        $columns = $this->columnsList($component->instance());
        $this->assertStringContainsString('Column 1', $columns);
        $this->assertStringContainsString('Column 2', $columns);
        $this->assertStringContainsString('1 block', $columns);
        $this->assertStringContainsString('No blocks', $columns);
        $this->assertStringContainsString("goToNode('{$uids['column2']}')", $columns);

        // A row's panel previews live.
        $component->set('mountedActions.0.data.background', 'custom')
            ->set('mountedActions.0.data.background_color', '#0b1f44')
            ->call('previewBlock')
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => ($params['isPreview'] ?? false) && str_contains($params['html'], 'background-color: #0b1f44'));

        $component->call('goToNode', $uids['column2'])
            ->assertCount('mountedActions', 1)
            ->assertSet('mountedActions.0.name', 'editColumn')
            ->assertSet('mountedActions.0.arguments.uid', $uids['column2']);

        $this->assertSame('#0b1f44', $this->draftBlocks($page)[0]['data']['rows'][0]['background_color'], 'The row was saved on the way.');

        // The column's title is the way back to its row.
        $heading = $component->instance()->getMountedAction()->getModalHeading()->toHtml();
        $this->assertStringContainsString('Column settings', $heading);
        $this->assertStringContainsString("goToNode('{$uids['row']}')", $heading);

        $component->set('mountedActions.0.data.background', 'dark')
            ->call('goToNode', $uids['row'])
            ->assertCount('mountedActions', 1)
            ->assertSet('mountedActions.0.name', 'editRow');

        $this->assertSame('dark', $this->draftBlocks($page)[0]['data']['rows'][0]['columns'][1]['background'], 'The column was saved on the way back.');
    }

    public function test_only_rows_and_columns_open_from_a_panel(): void
    {
        [$page, $uids] = $this->sectionPage();

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->call('goToNode', $uids['block'])
            ->assertSet('mountedActions', [])
            ->call('goToNode', $uids['section'])
            ->assertSet('mountedActions', [])
            ->call('goToNode', 'no-such-uid')
            ->assertSet('mountedActions', [])
            ->call('goToNode', $uids['column'])
            ->assertSet('mountedActions.0.name', 'editColumn');
    }

    public function test_a_one_column_rows_column_opens_and_its_settings_reach_the_page(): void
    {
        [$page, $uids] = $this->sectionPage('stacked');

        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editRow')->arguments(['uid' => $uids['row']]));

        $columns = $this->columnsList($component->instance());
        $this->assertStringContainsString('Column 1', $columns);
        $this->assertStringContainsString("goToNode('{$uids['column']}')", $columns);

        $component->call('goToNode', $uids['column'])
            ->assertSet('mountedActions.0.name', 'editColumn')
            ->set('mountedActions.0.data.background', 'custom')
            ->set('mountedActions.0.data.background_color', '#0b1f44')
            ->set('mountedActions.0.data.anchor', 'opening-times')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $page->update(['blocks' => app(VisualDrafts::class)->open($page, $this->user())->blocks]);

        $html = $this->get('/about-us')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="min-w-0[^"]*" id="opening-times"\s+style="background-color: #0b1f44;"\s+data-text-scheme="light">/', $html, 'The column is drawn as one cell with its settings.');
        $this->assertStringContainsString('Opening times', $html);
    }

    public function test_a_one_column_row_without_column_settings_is_drawn_as_before(): void
    {
        [$page] = $this->sectionPage('stacked');

        $html = $this->get('/about-us')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="min-w-0"\s*><section/', $html, 'Each block is a cell of its own, with no column settings.');
    }

    public function test_a_rows_background_colours_it_and_its_text(): void
    {
        $this->page(['blocks' => [self::block('section', ['rows' => [
            ['layout' => 'stacked', 'background' => 'custom', 'background_color' => '#0b1f44', 'columns' => [['blocks' => [self::block('text', ['content' => '<p>On navy</p>'])]]]],
        ]])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();
        $this->assertStringContainsString('background-color: #0b1f44', $html);
        $this->assertStringContainsString('data-text-scheme="light"', $html);
    }

    public function test_a_columns_panel_saves_a_link_anchor_classes_and_admin_label(): void
    {
        [$page, $uids] = $this->sectionPage();

        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editColumn')->arguments(['uid' => $uids['column']]), [
                'link_url' => '/contact',
                'link_new_tab' => true,
                'anchor' => 'opening-times',
                'css_class' => 'promo',
                'admin_label' => 'Times',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('Times', $component->instance()->canvasNodes()[$uids['column']]['label'], 'The canvas names it by its admin label.');

        $column = $this->draftBlocks($page)[0]['data']['rows'][0]['columns'][0];
        $this->assertSame(['/contact', true, 'opening-times', 'promo', 'Times'], [$column['link_url'], $column['link_new_tab'], $column['anchor'], $column['css_class'], $column['admin_label']]);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editColumn')->arguments(['uid' => $uids['column']]), ['link_url' => 'javascript:alert(1)'])
            ->assertHasActionErrors(['link_url']);
    }

    public function test_rows_and_columns_draw_their_image_link_anchor_and_classes(): void
    {
        Storage::fake('public');
        $this->page(['blocks' => [self::block('section', ['rows' => [
            [
                'layout' => '2',
                'background' => 'image',
                'background_image' => 'site-builder/pages/hall.jpg',
                'overlay_opacity' => 40,
                'anchor' => 'times-row',
                'css_class' => 'band',
                'columns' => [
                    ['blocks' => [self::block('text', ['content' => '<p>Left</p>'])], 'link_url' => '/contact', 'link_new_tab' => true, 'anchor' => 'left', 'css_class' => 'promo'],
                    ['blocks' => [self::block('text', ['content' => '<p>Right</p>'])], 'link_url' => 'javascript:alert(1)', 'anchor' => 'bad id!'],
                ],
            ],
        ]])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('id="times-row"', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*\bband\b[^"]*\bsb-node-layered\b/', $html, 'The row is a layer of its own for its image.');
        $this->assertStringContainsString('site-builder/pages/hall.jpg', $html);
        $this->assertStringContainsString('data-text-scheme="light"', $html, 'Light text on an image, as for a block.');
        $this->assertStringContainsString('id="left"', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*\bpromo\b[^"]*\bsb-node-layered sb-has-link\b/', $html);
        $this->assertStringContainsString('<a href="/contact" class="sb-block-link" aria-label="Open link" target="_blank" rel="noopener"></a>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('bad id!', $html);
    }

    public function test_saving_the_section_as_a_form_keeps_what_only_the_panels_set(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site-builder/pages/hall.jpg', 'image');
        $page = $this->page(['blocks' => [self::block('section', ['rows' => [
            [
                'layout' => '2',
                'background' => 'custom',
                'background_color' => '#0b1f44',
                'admin_label' => 'Times band',
                'link_url' => '/contact',
                'columns' => [
                    ['blocks' => [self::block('text', ['content' => '<p>Left</p>'])], 'anchor' => 'left', 'css_class' => 'promo', 'background' => 'image', 'background_image' => 'site-builder/pages/hall.jpg'],
                    ['blocks' => []],
                ],
            ],
        ]])]]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $row = array_values($page->fresh()->blocks[0]['data']['rows'])[0];
        $column = array_values($row['columns'])[0];
        $this->assertSame(['custom', '#0b1f44', 'Times band', '/contact'], [$row['background'] ?? null, $row['background_color'] ?? null, $row['admin_label'] ?? null, $row['link_url'] ?? null]);
        $this->assertSame(['left', 'promo', 'image'], [$column['anchor'] ?? null, $column['css_class'] ?? null, $column['background'] ?? null]);
    }

    public function test_each_panel_offers_only_the_design_options_that_suit_it(): void
    {
        [$page, $uids] = $this->sectionPage();
        $schema = 'mountedActionSchema0';
        $editor = fn () => Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()]);

        // A Section, row or column has no text settings: its blocks do.
        $editor()->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uids['section']]))
            ->assertFormFieldDoesNotExist('design.typography.heading.font', $schema)
            ->assertFormFieldDoesNotExist('appearance.heading_decoration', $schema)
            ->assertFormFieldExists('design.dividers.top_shape', $schema)
            ->assertFormFieldExists('design.spacing.margin', $schema);

        $editor()->mountAction(TestAction::make('editRow')->arguments(['uid' => $uids['row']]))
            ->assertFormFieldDoesNotExist('design.typography.body.size', $schema)
            ->assertFormFieldDoesNotExist('design.dividers.top_shape', $schema)
            ->assertFormFieldExists('design.spacing.max_width', $schema)
            ->assertFormFieldExists('design.spacing.margin', $schema)
            ->assertFormFieldExists('design.position.rotate', $schema);

        // A column fills its share of the row: no Sizing or margin, only padding.
        $editor()->mountAction(TestAction::make('editColumn')->arguments(['uid' => $uids['column']]))
            ->assertFormFieldDoesNotExist('design.typography.body.size', $schema)
            ->assertFormFieldDoesNotExist('design.spacing.max_width', $schema)
            ->assertFormFieldDoesNotExist('design.spacing.margin', $schema)
            ->assertFormFieldExists('design.spacing.padding', $schema)
            ->assertFormFieldExists('design.borders.border_width', $schema);

        // A block keeps them all.
        $editor()->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uids['block']]))
            ->assertFormFieldExists('design.typography.body.size', $schema)
            ->assertFormFieldExists('design.spacing.margin', $schema);
    }

    public function test_text_and_column_sizing_saved_before_dont_apply(): void
    {
        $this->page(['blocks' => [self::block('section', [
            'design' => ['typography' => ['heading' => ['colour' => '#ff0000']]],
            'rows' => [[
                'layout' => '2',
                'design' => ['typography' => ['body' => ['colour' => '#00ff00']], 'spacing' => ['margin' => ['desktop' => ['top' => '40px']]]],
                'columns' => [
                    ['blocks' => [self::block('text', ['content' => '<p>Left</p>'])], 'design' => ['spacing' => ['margin' => ['desktop' => ['top' => '77px']], 'padding' => ['desktop' => ['top' => '13px']]]]],
                    ['blocks' => []],
                ],
            ]],
        ])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringNotContainsString('#ff0000', $html);
        $this->assertStringNotContainsString('#00ff00', $html);
        $this->assertStringContainsString('40px', $html, 'A row keeps its margin.');
        $this->assertStringNotContainsString('77px', $html, 'A column has no margin.');
        $this->assertStringContainsString('13px', $html, 'A column keeps its padding.');
    }

    public function test_deleting_or_undoing_what_the_open_panel_edits_closes_it_so_the_canvas_keeps_working(): void
    {
        foreach (['block' => 'editBlock', 'row' => 'editRow', 'column' => 'editColumn'] as $part => $panel) {
            [$page, $uids] = $this->sectionPage();
            // Deleting a column means deleting its row (a row always has its layout's columns).
            $deleted = $part === 'column' ? $uids['row'] : $uids[$part];

            Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
                ->mountAction(TestAction::make($panel)->arguments(['uid' => $uids[$part]]))
                ->call('deleteNode', $deleted)
                ->assertSet('mountedActions', [])
                // Opening another part works again.
                ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uids['section']]))
                ->assertSet('mountedActions.0.name', 'editBlock');

            $page->forceDelete();
        }

        // Undoing a block's adding while its panel is open.
        [$page, $uids] = $this->sectionPage();
        // Adding a block opens its panel.
        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('addBlock')->arguments(['target' => $uids['column'], 'position' => 'in']))
            ->setActionData(['type' => 'text'])
            ->callMountedAction()
            ->assertSet('mountedActions.0.name', 'editBlock')
            ->call('undo')
            ->assertSet('mountedActions', []);
    }

    public function test_the_canvas_can_close_a_panel_left_open_out_of_sight(): void
    {
        [$page, $uids] = $this->sectionPage();

        // What the canvas calls when the server still has a panel open that isn't on screen.
        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editRow')->arguments(['uid' => $uids['row']]))
            ->call('unmountAction', true)
            ->assertSet('mountedActions', [])
            ->mountAction(TestAction::make('editColumn')->arguments(['uid' => $uids['column']]))
            ->assertSet('mountedActions.0.name', 'editColumn')
            ->assertSee('closeOpenPanel', false);
    }

    public function test_deleting_a_button_in_a_blocks_panel_keeps_the_panel_working(): void
    {
        $page = $this->page(['blocks' => [
            self::block('buttons', ['buttons' => [['label' => 'Book the hall', 'url' => '/book'], ['label' => 'Call us', 'url' => '/call']]]),
        ]]);
        $uid = $page->blocks[0]['uid'];

        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]));
        $first = array_key_first($component->get('mountedActions.0.data.buttons'));

        $component->callAction(TestAction::make('delete')->schemaComponent('buttons', 'mountedActionSchema0')->arguments(['item' => $first]))
            ->assertSet('mountedActions.0.name', 'editBlock')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertSet('mountedActions', []);

        $this->assertSame(['Call us'], array_column($this->draftBlocks($page)[0]['data']['buttons'], 'label'));
    }

    /**
     * The row's column list as it's drawn in the open panel (Filament draws a modal's contents in the browser).
     */
    private function columnsList(VisualEditor $editor): string
    {
        return view('site-builder::filament.blocks.panel-structure', ['getLivewire' => fn (): VisualEditor => $editor])->render();
    }
}
