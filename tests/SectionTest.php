<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationMailbox;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\PageRevision;

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

    public function test_a_sections_panel_is_wider_than_other_blocks_panels(): void
    {
        $page = $this->page(['blocks' => [
            self::section([['layout' => '3', 'blocks' => [self::block('text'), self::block('text'), self::block('text')]]]),
            self::block('text', ['content' => '<p>Hi</p>']),
        ]]);
        $panelWidth = function (int|string $block, string $action = 'edit') use ($page): mixed {
            $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
            $arguments = is_int($block) ? ['item' => array_keys($component->get('data.blocks'))[$block]] : ['block' => $block];

            return $component
                ->mountAction(TestAction::make($action)->schemaComponent('blocks')->arguments($arguments))
                ->instance()->getMountedAction()->getModalWidth();
        };

        $this->assertSame(Width::SevenExtraLarge, $panelWidth(0), 'Editing a section.');
        $this->assertSame(Width::FourExtraLarge, $panelWidth(1), 'Editing a text block.');
        $this->assertSame(Width::SevenExtraLarge, $panelWidth('section', 'add'), 'Adding a section.');
        $this->assertSame(Width::FourExtraLarge, $panelWidth('text', 'add'), 'Adding a text block.');
    }

    public function test_a_block_panel_in_a_section_is_titled_with_where_the_block_is(): void
    {
        $page = $this->page(['blocks' => [self::section([
            ['layout' => '2', 'columns' => [['blocks' => []], ['blocks' => [self::block('call_to_action', ['heading' => 'Book a room'])]]]],
            ['layout' => 'stacked', 'blocks' => [self::block('text', ['content' => '<p>Underneath</p>'])]],
        ])]]);
        // A block's panel, opened over the section's in a fresh editor: the block (or Add, with a block type) in a column.
        $panelTitle = function (int $row, int $column, ?string $add = null) use ($page): string {
            $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
            $openSection = TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]);
            $component->mountAction($openSection);
            $rowKey = array_keys($component->get('mountedActions.0.data.rows'))[$row];
            $columnKey = array_keys($component->get("mountedActions.0.data.rows.{$rowKey}.columns"))[$column];
            $blocks = "rows.{$rowKey}.columns.{$columnKey}.blocks";
            $arguments = $add === null ? ['item' => array_key_first($component->get("mountedActions.0.data.{$blocks}"))] : ['block' => $add];

            $component->mountAction([$openSection, TestAction::make($add === null ? 'edit' : 'add')->schemaComponent($blocks, 'mountedActionSchema0')->arguments($arguments)]);

            return (string) $component->instance()->getMountedAction(1)->getModalHeading();
        };

        $this->assertSame('Section › Row 1 › Column 2 › Call to action: Book a room', $panelTitle(0, 1));
        $this->assertSame('Section › Row 2 › Text', $panelTitle(1, 0), 'A row of one column has no column number.');
        $this->assertSame('Section › Row 1 › Column 2 › Add Text', $panelTitle(0, 1, 'text'));
    }

    public function test_the_section_panel_explains_itself_to_someone_new(): void
    {
        $page = $this->page(['blocks' => [self::section([['layout' => '2', 'columns' => [
            ['blocks' => [self::block('text')]],
            ['blocks' => [self::block('call_to_action', ['heading' => 'Book a room'])]],
        ]]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]));
        $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
        $columnKey = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.columns"));
        $tooltip = fn (string $text): \Closure => fn (Field $field): bool => str_starts_with((string) $field->getHintIconTooltip(), $text);

        $component
            ->assertFormFieldExists('rows', 'mountedActionSchema0', fn (Repeater $field): bool => $field->getItemLabel($rowKey) === 'Row 1 · Two equal columns: Text | Call to action')
            ->assertFormFieldExists("rows.{$rowKey}.layout", 'mountedActionSchema0', $tooltip('How the row is split into columns'))
            ->assertFormFieldExists("rows.{$rowKey}.align", 'mountedActionSchema0', $tooltip('Where a shorter column sits next to a taller one'))
            ->assertFormFieldExists("rows.{$rowKey}.gap", 'mountedActionSchema0', $tooltip('The gap between this row'))
            ->assertFormFieldExists("rows.{$rowKey}.columns.{$columnKey}.background", 'mountedActionSchema0', $tooltip('The colour or picture behind it'))
            ->assertFormFieldExists("rows.{$rowKey}.columns.{$columnKey}.border_style", 'mountedActionSchema0', $tooltip('A line around the edge'))
            ->assertFormFieldExists("rows.{$rowKey}.columns", 'mountedActionSchema0', fn (Repeater $field): bool => $field->getMoveDownAction()->getTooltip() === 'Swap with the column on the right')
            ->assertFormFieldExists('rows', 'mountedActionSchema0', fn (Repeater $field): bool => $field->getCloneAction()->getTooltip() === 'Duplicate this row')
            ->assertFormFieldExists("rows.{$rowKey}.columns.{$columnKey}.blocks", 'mountedActionSchema0', fn (Builder $field): bool => $field->getDeleteAction()->getTooltip() === 'Delete this block'
                && $field->getEditAction()->getTooltip() === 'Edit this block');

        $options = SectionBlock::layoutOptions();
        $this->assertSame(['One column', '2 columns', '3 columns', 'Four or five columns'], array_keys($options));
        $this->assertSame(array_keys(SectionBlock::LAYOUTS), array_merge(...array_map(array_keys(...), array_values($options))), 'Every layout is offered once.');
        $this->assertSame('Narrow left, wide right (⅓ + ⅔)', $options['2 columns']['1-2']);
    }

    public function test_save_page_from_a_block_panel_inside_a_section_keeps_both_panels_changes(): void
    {
        $page = $this->page(['blocks' => [self::section([['layout' => '2', 'blocks' => [self::block('text', ['content' => '<p>Old</p>'])]]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $sectionKey = array_key_first($component->get('data.blocks'));

        $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]));

        $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
        $columnKey = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.columns"));
        $innerKey = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.columns.{$columnKey}.blocks"));

        $component
            ->set('mountedActions.0.data.gap', 'large')
            ->mountAction([
                TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]),
                TestAction::make('edit')->schemaComponent("rows.{$rowKey}.columns.{$columnKey}.blocks", 'mountedActionSchema0')->arguments(['item' => $innerKey]),
            ]);

        $this->assertCount(2, $component->get('mountedActions'), 'The block opens in a second panel over the section\'s.');

        $component
            ->set('mountedActions.1.data.content', '<p>New</p>')
            ->call('saveFromEditor')
            ->assertHasNoFormErrors();

        $section = Page::query()->find($page->id)->blockList()[0]['data'];
        $this->assertSame('large', $section['gap']);
        $this->assertStringContainsString('New', array_values(array_values(array_values($section['rows'])[0]['columns'])[0]['blocks'])[0]['data']['content']);
    }

    public function test_a_problem_in_a_rows_block_names_the_section_row_and_block(): void
    {
        $fine = self::block('text', ['content' => '<p>Fine</p>']);
        $bad = self::block('call_to_action', ['heading' => '']);
        $page = $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Intro</p>']),
            self::section([
                ['layout' => 'stacked', 'columns' => [['blocks' => [$fine]]]],
                ['layout' => '2', 'columns' => [['blocks' => [$fine]], ['blocks' => [$fine, $bad]]]],
                ['layout' => 'stacked', 'columns' => [['blocks' => [$fine, $bad]]]],
            ]),
        ]]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->call('save');

        // As Notification::assertNotified() reads them, but keeping the body.
        app(NotificationMailbox::class)->flush();
        $notifications = new Notifications;
        $notifications->mount();
        $notifications->pullNotificationsFromSession();
        $notification = $notifications->notifications->first(fn (Notification $sent): bool => $sent->getTitle() === 'The page wasn\'t saved');

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Block 2 (Section (rows of blocks)) › Row 2 › Column 2 › Block 2 (Call to action)', (string) $notification->getBody());
        $this->assertStringContainsString('Block 2 (Section (rows of blocks)) › Row 3 › Block 2 (Call to action)', (string) $notification->getBody(), 'A stacked row has no column to name.');
    }

    public function test_each_column_holds_its_own_blocks_one_under_another(): void
    {
        $this->page(['blocks' => [self::section([
            ['layout' => '2', 'align' => 'stretch', 'columns' => [
                ['blocks' => [
                    self::block('text', ['content' => '<p>First text</p>']),
                    self::block('text', ['content' => '<p>Second text</p>']),
                    self::block('image', ['image' => 'site-builder/pages/small.jpg', 'alt' => 'Small']),
                ]],
                ['blocks' => [self::block('image', ['image' => 'site-builder/pages/big.jpg', 'alt' => 'Big'])]],
            ]],
            ['layout' => '3', 'columns' => [['blocks' => []], ['blocks' => [self::block('text', ['content' => '<p>Middle only</p>'])]], ['blocks' => []]]],
            ['layout' => '2', 'columns' => [['blocks' => []], ['blocks' => []]]],
        ])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="min-w-0\s+flex flex-col gap-8 lg:gap-12"\s+data-column="1"\s*>.*First text.*Second text.*alt="Small".*<\/div>\s*<div class="min-w-0\s*"\s+data-column="2"\s*>.*alt="Big"/s', $html);
        $this->assertMatchesRegularExpression('/<div class="min-w-0\s*"\s+data-column="1"\s*><\/div>\s*<div class="min-w-0\s*"\s+data-column="2"\s*>.*Middle only.*<\/div>\s*<div class="min-w-0\s*"\s+data-column="3"\s*><\/div>/s', $html, 'An empty column keeps its place.');
        $this->assertSame(2, substr_count($html, 'data-row-layout='), 'A row with no blocks at all isn\'t shown.');
    }

    public function test_rows_saved_before_columns_held_blocks_are_read_into_columns(): void
    {
        $legacyRow = ['layout' => '2', 'align' => 'center', 'blocks' => [
            'a' => self::block('text', ['content' => '<p>One</p>']),
            'b' => self::block('text', ['content' => '<p>Two</p>']),
            'c' => self::block('text', ['content' => '<p>Three</p>']),
        ]];

        $this->assertSame([
            'layout' => '2',
            'align' => 'center',
            'columns' => [
                ['blocks' => ['a' => $legacyRow['blocks']['a'], 'c' => $legacyRow['blocks']['c']]],
                ['blocks' => ['b' => $legacyRow['blocks']['b']]],
            ],
        ], SectionBlock::normaliseRow($legacyRow), 'Each column gets the blocks that sat in it.');

        $page = $this->page(['blocks' => [self::section([$legacyRow])]]);
        $stored = json_decode((string) DB::table('site_builder_pages')->where('id', $page->id)->value('blocks'), true);
        $this->assertArrayNotHasKey('blocks', $stored[0]['data']['rows'][0], 'Saved in the new shape.');
        $this->assertCount(2, $stored[0]['data']['rows'][0]['columns']);

        // A page saved by 1.4 and still in the database in the old shape (version 1) opens with its blocks in columns.
        DB::table('site_builder_pages')->where('id', $page->id)->update(['blocks' => json_encode([self::section([$legacyRow])]), 'blocks_version' => 1]);
        $row = Page::query()->find($page->id)->blocks[0]['data']['rows'][0];
        $this->assertSame(['a', 'c'], array_keys($row['columns'][0]['blocks']));

        $this->assertSame(SectionBlock::normaliseRow($legacyRow), SectionBlock::normaliseRow(SectionBlock::normaliseRow($legacyRow)), 'Reading a row twice changes nothing.');
        $this->get('/about-us')->assertOk()->assertSeeInOrder(['One', 'Three', 'Two']);
    }

    public function test_a_revision_from_before_columns_restores_into_columns(): void
    {
        $revision = new PageRevision(['snapshot' => ['title' => 'Old', 'blocks' => [self::section([['layout' => 'stacked', 'blocks' => [self::block('text', ['content' => '<p>Old</p>'])]]])]]]);

        $row = $revision->attributesToRestore()['blocks'][0]['data']['rows'][0];

        $this->assertArrayNotHasKey('blocks', $row);
        $this->assertCount(1, $row['columns']);
    }

    public function test_fewer_columns_pass_their_blocks_to_the_last_column_kept(): void
    {
        $columns = ['x' => ['blocks' => ['a' => 'A']], 'y' => ['blocks' => ['b' => 'B']], 'z' => ['blocks' => ['c' => 'C']]];

        $this->assertSame(['x' => ['blocks' => ['a' => 'A', 'b' => 'B', 'c' => 'C']]], SectionBlock::fitColumns($columns, 1));
        $this->assertSame(['x' => ['blocks' => ['a' => 'A']], 'y' => ['blocks' => ['b' => 'B', 'c' => 'C']]], SectionBlock::fitColumns($columns, 2));
        $this->assertSame([...$columns, 'new' => ['blocks' => []]], SectionBlock::fitColumns($columns, 4, fn (): string => 'new'));
    }

    public function test_the_row_editor_shows_a_column_each_and_changing_the_layout_keeps_the_blocks(): void
    {
        $page = $this->page(['blocks' => [self::section([['layout' => '2', 'columns' => [
            ['blocks' => [self::block('text', ['content' => '<p>Left</p>'])]],
            ['blocks' => [self::block('call_to_action', ['heading' => 'Right'])]],
        ]]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $sectionKey = array_key_first($component->get('data.blocks'));

        $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]));
        $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));

        [$left, $right] = array_keys($component->get("mountedActions.0.data.rows.{$rowKey}.columns"));

        $component
            ->assertFormFieldExists("rows.{$rowKey}.columns", 'mountedActionSchema0', fn (Repeater $field): bool => $field->getItemLabel($left) === 'Column 1' && $field->getItemLabel($right) === 'Column 2')
            ->assertFormFieldExists("rows.{$rowKey}.columns.{$right}.blocks", 'mountedActionSchema0', fn (Builder $field): bool => $field->getAddActionLabel() === 'Add block to column 2');
        $this->assertSame('Two equal columns: Text | Call to action', SectionBlock::rowLabel($component->get("mountedActions.0.data.rows.{$rowKey}")));

        $component->set("mountedActions.0.data.rows.{$rowKey}.layout", '3');
        $columns = $component->get("mountedActions.0.data.rows.{$rowKey}.columns");
        $this->assertCount(3, $columns);
        $this->assertSame([], array_values($columns)[2]['blocks']);

        $component->set("mountedActions.0.data.rows.{$rowKey}.layout", 'stacked');
        $columns = $component->get("mountedActions.0.data.rows.{$rowKey}.columns");
        $this->assertCount(1, $columns);
        $this->assertSame(['text', 'call_to_action'], array_column(array_values(array_values($columns)[0]['blocks']), 'type'));
        $component
            ->assertFormFieldExists("rows.{$rowKey}.columns.{$left}.blocks", 'mountedActionSchema0', fn (Builder $field): bool => $field->getAddActionLabel() === 'Add block to this row')
            ->assertFormFieldHidden("rows.{$rowKey}.reverse_on_phones", 'mountedActionSchema0');
    }

    public function test_a_block_can_move_to_the_next_column_and_back(): void
    {
        $page = $this->page(['blocks' => [self::section([['layout' => '2', 'columns' => [
            ['blocks' => [self::block('text', ['content' => '<p>Mover</p>']), self::block('text', ['content' => '<p>Stays</p>'])]],
            ['blocks' => [self::block('image', ['alt' => 'Big'])]],
        ]]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $sectionKey = array_key_first($component->get('data.blocks'));

        $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]));
        $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
        [$left, $right] = array_keys($component->get("mountedActions.0.data.rows.{$rowKey}.columns"));
        [$mover, $stays] = array_keys($component->get("mountedActions.0.data.rows.{$rowKey}.columns.{$left}.blocks"));
        $image = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.columns.{$right}.blocks"));
        $move = fn (string $action, string $column): array => [
            TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]),
            TestAction::make($action)->schemaComponent("rows.{$rowKey}.columns.{$column}.blocks", 'mountedActionSchema0')->arguments(['item' => $mover]),
        ];
        $blocksIn = fn (string $column): array => array_keys($component->get("mountedActions.0.data.rows.{$rowKey}.columns.{$column}.blocks") ?? []);

        $component->mountAction($move('moveToNextColumn', $left))->callMountedAction();

        $this->assertSame([$stays], $blocksIn($left));
        $this->assertSame([$image, $mover], $blocksIn($right), 'It goes to the end of the next column.');

        $component->mountAction($move('moveToPreviousColumn', $right))->callMountedAction();

        $this->assertSame([$stays, $mover], $blocksIn($left));
        $this->assertSame([$image], $blocksIn($right));
    }

    public function test_a_block_in_the_first_column_stays_put_when_moved_left(): void
    {
        $page = $this->page(['blocks' => [self::section([['layout' => '2', 'columns' => [
            ['blocks' => [self::block('text', ['content' => '<p>Left</p>'])]],
            ['blocks' => []],
        ]]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $sectionKey = array_key_first($component->get('data.blocks'));

        $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]));
        $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
        $left = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.columns"));
        $block = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.columns.{$left}.blocks"));

        $component->mountAction([
            TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]),
            TestAction::make('moveToPreviousColumn')->schemaComponent("rows.{$rowKey}.columns.{$left}.blocks", 'mountedActionSchema0')->arguments(['item' => $block]),
        ]);

        // There's no column to the left, so the button is hidden, Filament won't run it, and the block stays put.
        $this->assertSame([$block], array_keys($component->get("mountedActions.0.data.rows.{$rowKey}.columns.{$left}.blocks")));
    }

    public function test_columns_have_their_own_background_padding_border_and_alignment(): void
    {
        $this->page(['blocks' => [self::section([
            ['layout' => '2', 'columns' => [
                ['background' => 'dark', 'align' => 'center', 'blocks' => [self::block('text', ['content' => '<p>On dark</p>'])]],
                ['padding' => 'large', 'corners' => 'medium', 'border_style' => 'solid', 'border_color' => '#336699', 'blocks' => [self::block('text', ['content' => '<p>Framed</p>'])]],
            ]],
            ['layout' => 'stacked', 'columns' => [['background' => 'dark', 'blocks' => [self::block('text', ['content' => '<p>Stacked</p>'])]]]],
        ])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="min-w-0 bg-gray-900 text-white p-6 sm:p-8 self-center"\s+data-column="1"\s+data-text-scheme="light"><section\s+class="relative scroll-mt-24"\s+data-block="text"\s*>.*On dark/s', $html, 'A dark column pads itself, and its blocks take its light text.');
        $this->assertMatchesRegularExpression('/<div class="min-w-0 p-8 sm:p-12 rounded-xl"\s+data-column="2"\s+style="border: 1px solid #336699;"\s*>.*Framed/s', $html);
        $this->assertMatchesRegularExpression('/<div class="min-w-0"\s*><section[^>]*>.*Stacked/s', $html, 'Column settings only apply side by side.');
    }

    public function test_a_column_with_a_light_background_in_a_dark_section_has_dark_text(): void
    {
        $this->page(['blocks' => [self::section(
            [['layout' => '2', 'columns' => [
                ['background' => 'light', 'blocks' => [self::block('call_to_action', ['heading' => 'Light column'])]],
                ['blocks' => [self::block('call_to_action', ['heading' => 'Dark section'])]],
            ]]],
            ['appearance' => ['background' => 'dark']],
        )]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-column="1"\s+data-text-scheme="dark">.*Light column/s', $html);
        $this->assertMatchesRegularExpression('/<div class="min-w-0"\s+data-column="2"\s*>/', $html, 'A column without settings follows the section.');
    }

    public function test_rows_can_have_their_own_column_gap_and_uneven_layouts(): void
    {
        $this->page(['blocks' => [self::section([
            ['layout' => '1-3', 'gap' => 'none', 'columns' => [
                ['blocks' => [self::block('text', ['content' => '<p>Quarter</p>'])]],
                ['blocks' => [self::block('text', ['content' => '<p>Three quarters</p>'])]],
            ]],
            ['layout' => '1-2-1', 'columns' => [
                ['blocks' => [self::block('text', ['content' => '<p>Left</p>'])]],
                ['blocks' => [self::block('text', ['content' => '<p>Middle</p>'])]],
                ['blocks' => [self::block('text', ['content' => '<p>Right</p>'])]],
            ]],
            ['layout' => '5', 'gap' => 'made-up', 'columns' => array_fill(0, 5, ['blocks' => [self::block('text', ['content' => '<p>Fifth</p>'])]])],
        ])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="grid grid-cols-1 gap-0 md:grid-cols-4 items-start\s*"[^>]*data-row-layout="1-3"/', $html);
        $this->assertMatchesRegularExpression('/<div class="min-w-0 md:col-span-1"\s+data-column="1"\s*>.*Quarter.*<div class="min-w-0 md:col-span-3"\s+data-column="2"\s*>.*Three quarters/s', $html);
        $this->assertMatchesRegularExpression('/data-row-layout="1-2-1".*md:col-span-1"\s+data-column="1"\s*>.*md:col-span-2"\s+data-column="2"\s*>.*md:col-span-1"\s+data-column="3"\s*>/s', $html);
        $this->assertMatchesRegularExpression('/<div class="grid grid-cols-1 gap-8 lg:gap-12 md:grid-cols-3 lg:grid-cols-5 items-start\s*"[^>]*data-row-layout="5"/', $html, 'An unknown gap uses the section\'s.');
        $this->assertSame(5, substr_count($html, 'Fifth'));
    }

    public function test_column_settings_are_offered_side_by_side_and_saved_from_the_editor(): void
    {
        $page = $this->page(['blocks' => [self::section([['layout' => '2', 'columns' => [
            ['blocks' => [self::block('text', ['content' => '<p>Left</p>'])]],
            ['blocks' => []],
        ]]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $sectionKey = array_key_first($component->get('data.blocks'));

        $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $sectionKey]));
        $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
        $left = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.columns"));

        $component
            ->assertFormFieldVisible("rows.{$rowKey}.columns.{$left}.background", 'mountedActionSchema0')
            ->assertFormFieldVisible("rows.{$rowKey}.gap", 'mountedActionSchema0')
            ->set("mountedActions.0.data.rows.{$rowKey}.gap", 'small')
            ->set("mountedActions.0.data.rows.{$rowKey}.columns.{$left}.background", 'brand')
            ->set("mountedActions.0.data.rows.{$rowKey}.columns.{$left}.align", 'end')
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $row = $page->fresh()->blocks[0]['data']['rows'][0];
        $this->assertSame(['small', 'brand', 'end'], [$row['gap'], $row['columns'][0]['background'], $row['columns'][0]['align']]);

        $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]));
        $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
        $component->set("mountedActions.0.data.rows.{$rowKey}.layout", 'stacked');
        $left = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.columns"));

        $component
            ->assertFormFieldHidden("rows.{$rowKey}.columns.{$left}.background", 'mountedActionSchema0')
            ->assertFormFieldHidden("rows.{$rowKey}.gap", 'mountedActionSchema0');
    }
}
