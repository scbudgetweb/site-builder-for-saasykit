<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\LibraryVisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\TemplateVisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\PageExport;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

/**
 * Import on the visual builder's canvas menu: a file from Export, checked as Save checks blocks, placed by what was
 * right-clicked. Images that aren't on this site are left out, not refused.
 */
class ImportPartsTest extends PageTestCase
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
     * Text "Intro", then a Section with a two-column row (A | B) and a one-column row (C).
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
                ['layout' => 'stacked', 'columns' => [['blocks' => [self::block('text', ['content' => '<p>C</p>'])]]]],
            ]]),
        ]]);
    }

    /**
     * The uids by what they hold: "Intro", "A", "B", "C", "section", "row1", "row2", "col1", "col2", "col3".
     *
     * @return array<string, string>
     */
    private static function uids(Page $page): array
    {
        $blocks = $page->blocks;
        $uids = ['Intro' => $blocks[0]['uid'], 'section' => $blocks[1]['uid']];
        $count = 0;

        foreach (array_values($blocks[1]['data']['rows']) as $rowIndex => $row) {
            $uids['row'.($rowIndex + 1)] = $row['uid'];

            foreach (array_values($row['columns']) as $column) {
                $uids['col'.++$count] = $column['uid'];

                foreach ($column['blocks'] as $block) {
                    $uids[strip_tags($block['data']['content'])] = $block['uid'];
                }
            }
        }

        return $uids;
    }

    /**
     * Each block's text in page order, "|" between columns and "/" between rows, e.g. "Intro [A | B / C]".
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
                fn (array $row): string => implode(' | ', array_map(fn (array $column): string => $column['blocks'] === [] ? '-' : self::outline($column['blocks']), array_values($row['columns']))),
                array_values($block['data']['rows']),
            )).']';
        }, array_values($blocks)));
    }

    private function draftOutline(Page $page): string
    {
        return self::outline(app(VisualDrafts::class)->open($page, $this->user())->blocks ?? []);
    }

    /**
     * @param  array<string, mixed>  $file
     */
    private function importInto(Page $page, string $uid, array $file): Testable
    {
        return Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('importPart')->arguments(['uid' => $uid]), [
                'file' => UploadedFile::fake()->createWithContent('export.json', (string) json_encode($file)),
            ]);
    }

    /**
     * An export of these text blocks, as Export writes it.
     *
     * @return array<string, mixed>
     */
    private static function textExport(string ...$texts): array
    {
        return app(PageExport::class)->page(array_map(fn (string $text): array => self::block('text', ['content' => "<p>{$text}</p>"]), $texts));
    }

    public function test_only_an_export_from_this_or_an_older_site_builder_is_read(): void
    {
        $this->assertSame('This isn\'t a site builder export: it isn\'t a JSON file.', PageImport::readExport('not json'));
        $this->assertSame('This isn\'t a site builder export. Use a file made with Export.', PageImport::readExport('{"title": "About us"}'));
        $this->assertSame('This file was exported from a newer version of the site builder. Update the site builder here to import it.', PageImport::readExport('{"site_builder": 99, "kind": "page", "blocks": []}'));
        $this->assertSame('This file was exported from a newer version of the site builder. Update the site builder here to import it.', PageImport::readExport('{"site_builder": 1, "kind": "page", "schema": 99, "blocks": []}'));
        $this->assertSame('This export is damaged: it has no row.', PageImport::readExport('{"site_builder": 1, "kind": "row"}'));

        // A Section saved before columns (schema 1) is upgraded as a stored one is.
        $read = PageImport::readExport((string) json_encode(['site_builder' => 1, 'kind' => 'section', 'schema' => 1, 'blocks' => [
            self::block('section', ['rows' => [['layout' => '2', 'blocks' => [self::block('text', ['content' => '<p>A</p>']), self::block('text', ['content' => '<p>B</p>'])]]]]),
        ]]));
        $this->assertSame('[A | B]', self::outline($read['blocks']));
    }

    public function test_blocks_are_checked_and_images_from_another_site_are_left_out(): void
    {
        $result = app(PageImport::class)->blocks([
            self::block('image', ['image' => 'site-builder/pages/from-another-site.jpg', 'alt' => 'The main hall']),
            self::block('text', ['content' => '<p>Hello</p>']),
        ]);

        $this->assertFalse($result->failed());
        $this->assertSame(['blocks.0.data.image: An image that isn\'t uploaded on this site, so it was left out. Choose another in its settings.'], $result->warnings);
        $this->assertSame(['image', 'text'], array_column($result->blocks, 'type'));
        $this->assertNull($result->blocks[0]['data']['image'] ?? null, 'It comes in without its image, like a block just added.');
        $this->assertSame('The main hall', $result->blocks[0]['data']['alt']);
        $this->assertNotNull($result->blocks[0]['uid'] ?? null, 'New uids.');
        $this->assertSame([], Storage::disk('public')->allFiles(), 'The stand-in image is gone again.');

        $this->assertSame(['blocks.0.type: This site has no "nonsense" block. The file may be from a newer version of the site builder, or use a block from an add-on this site doesn\'t have.'], app(PageImport::class)->blocks([self::block('nonsense')])->errors, 'Worded for people, not for someone writing JSON.');
        $this->assertSame(['blocks.0.type: A Hero can\'t go in a Section\'s column.'], app(PageImport::class)->blocks([self::block('hero', ['heading' => 'Hi'])], inColumn: true)->errors);
        $this->assertSame(['blocks.0.data.content: Not a setting here. Check its spelling, and that it belongs to this block.'], app(PageImport::class)->blocks([self::block('hero', ['heading' => 'Hi', 'content' => 'x'])])->errors);
    }

    public function test_rows_and_columns_are_checked_in_a_section(): void
    {
        $row = app(PageImport::class)->row(['layout' => '2', 'columns' => [['blocks' => [self::block('text', ['content' => '<p>X</p>'])]], ['blocks' => []]]]);
        $this->assertFalse($row->failed());
        $this->assertSame(['2', 2], [(string) $row->blocks[0]['layout'], count($row->blocks[0]['columns'])]);

        $bad = app(PageImport::class)->row(['layout' => '2', 'columns' => [['blocks' => [self::block('hero')]], ['blocks' => []]]]);
        $this->assertSame(['row.columns.0.blocks.0.type: A Hero can\'t go in a Section\'s column.'], $bad->errors, 'Problems name places in the row.');

        // A row's and a column's own settings (carried hidden in the page form) are checked by their panels.
        $styled = app(PageImport::class)->row(['layout' => '2', 'gap' => 'none', 'admin_label' => 'Opening hours', 'design' => ['spacing' => ['padding' => null], 'effects' => ['hide_on' => []]], 'columns' => [
            ['design' => ['effects' => ['hide_on' => ['phone']]], 'blocks' => []],
            ['blocks' => []],
        ]]);
        $this->assertSame([], $styled->errors);
        $this->assertSame(['none', 'Opening hours', ['phone']], [$styled->blocks[0]['gap'], $styled->blocks[0]['admin_label'], $styled->blocks[0]['columns'][0]['design']['effects']['hide_on']]);

        $typo = app(PageImport::class)->row(['layout' => '2', 'design' => ['spacing' => ['paddding' => '4px']], 'gap' => 'enormous', 'columns' => [['blocks' => []], ['blocks' => []]]]);
        $this->assertContains('row.design.spacing.paddding: Not a setting here. Check its spelling, and that it belongs to this block.', $typo->errors);
        $this->assertContains('row.gap: The selected space between columns is invalid.', $typo->errors);

        $column = app(PageImport::class)->column(['blocks' => [self::block('text', ['content' => '<p>Y</p>'])]]);
        $this->assertSame('<p>Y</p>', $column->blocks[0]['blocks'][0]['data']['content']);
    }

    public function test_blocks_go_after_a_block_or_at_the_end_of_a_column(): void
    {
        $page = $this->structurePage();
        $u = self::uids($page);

        $this->importInto($page, $u['Intro'], self::textExport('X', 'Y'))->assertHasNoActionErrors()->assertNotified('Imported');
        $this->assertSame('Intro X Y [A | B / C]', $this->draftOutline($page), 'After it, in order.');

        $this->importInto($page, $u['col1'], self::textExport('Z'));
        $this->assertSame('Intro X Y [A Z | B / C]', $this->draftOutline($page), 'At the end of the column.');

        $this->importInto($page, $u['B'], self::textExport('W'));
        $this->assertSame('Intro X Y [A Z | B W / C]', $this->draftOutline($page), 'After a block in a column.');

        $this->importInto($page, $u['section'], app(PageExport::class)->part($page->fresh()->blocks, $u['section']));
        $this->assertSame('Intro X Y [A Z | B W / C] [A | B / C]', $this->draftOutline($page), 'A Section after a Section.');

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->call('undo');
        $this->assertSame('Intro X Y [A Z | B W / C]', $this->draftOutline($page), 'Each import is one change: undo takes it back.');
    }

    public function test_rows_go_after_a_row_or_at_the_end_of_a_section(): void
    {
        $page = $this->structurePage();
        $u = self::uids($page);
        $row = app(PageExport::class)->part($page->blocks, $u['row2']);

        $this->importInto($page, $u['row1'], $row);
        $this->assertSame('Intro [A | B / C / C]', $this->draftOutline($page));

        $this->importInto($page, $u['section'], $row);
        $this->assertSame('Intro [A | B / C / C / C]', $this->draftOutline($page));

        $this->importInto($page, $u['A'], $row);
        $this->assertSame('Intro [A | B / C / C / C / C]', $this->draftOutline($page), 'After the row of the block right-clicked.');
    }

    public function test_a_columns_blocks_join_a_column(): void
    {
        $page = $this->structurePage();
        $u = self::uids($page);
        $column = app(PageExport::class)->part($page->blocks, $u['col1']);

        $this->importInto($page, $u['col2'], $column);
        $this->assertSame('Intro [A | B A / C]', $this->draftOutline($page));

        $this->importInto($page, $u['C'], $column);
        $this->assertSame('Intro [A | B A / C A]', $this->draftOutline($page));
    }

    public function test_what_cant_go_there_says_where_it_can(): void
    {
        $page = $this->structurePage();
        $u = self::uids($page);
        $row = app(PageExport::class)->part($page->blocks, $u['row1']);
        $column = app(PageExport::class)->part($page->blocks, $u['col1']);
        $section = app(PageExport::class)->part($page->blocks, $u['section']);

        $this->importInto($page, $u['Intro'], $row)->assertNotified('Nothing was imported');
        $this->importInto($page, $u['Intro'], $column)->assertNotified('Nothing was imported');
        $this->importInto($page, $u['row1'], self::textExport('X'))->assertNotified('Nothing was imported');
        $this->importInto($page, $u['A'], $section)->assertNotified('Nothing was imported');

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('importPart')->arguments(['uid' => $u['Intro']]), [
                'file' => UploadedFile::fake()->createWithContent('notes.json', '{"title": "Not an export"}'),
            ])
            ->assertNotified('Nothing was imported');

        $this->assertSame('Intro [A | B / C]', $this->draftOutline($page), 'Nothing changed.');
    }

    /**
     * @param  array<string, mixed>  $file
     * @param  class-string  $editor
     */
    private function importPage(Model $record, array $file, bool $replace = true, string $editor = VisualEditor::class): Testable
    {
        return Livewire::test($editor, ['record' => $record->getRouteKey()])
            ->callAction('importPage', [
                'file' => UploadedFile::fake()->createWithContent('export.json', (string) json_encode($file)),
                'replace' => $replace,
            ]);
    }

    public function test_import_page_replaces_the_content_or_adds_to_the_end(): void
    {
        $page = $this->structurePage();

        $this->importPage($page, self::textExport('X', 'Y'), replace: false)->assertHasNoActionErrors()->assertNotified('Imported');
        $this->assertSame('Intro [A | B / C] X Y', $this->draftOutline($page), 'Off: added at the end.');

        $this->importPage($page, self::textExport('New'))->assertNotified('Imported');
        $this->assertSame('New', $this->draftOutline($page), 'On (the default): everything replaced.');
        $this->assertSame('About us', $page->fresh()->title, 'Never the title.');
        $this->assertSame('Intro [A | B / C]', self::outline($page->fresh()->blocks), 'Visitors see nothing until it\'s published.');

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->call('undo');
        $this->assertSame('Intro [A | B / C] X Y', $this->draftOutline($page), 'Undo brings it back.');

        $u = self::uids($page);
        $this->importPage($page, app(PageExport::class)->part($page->blocks, $u['row1']))->assertNotified('Nothing was imported');
        $this->assertSame('Intro [A | B / C] X Y', $this->draftOutline($page), 'A row goes in with right-click, not over the page.');
    }

    public function test_a_templates_import_brings_its_header_settings_and_only_into_its_own_type(): void
    {
        $header = Template::query()->create(['name' => 'Main header', 'type' => 'header', 'blocks' => [self::block('text', ['content' => '<p>Old</p>'])]]);
        $other = Template::query()->create(['name' => 'Other header', 'type' => 'header', 'settings' => ['sticky' => 'always', 'shrink' => true], 'blocks' => [self::block('text', ['content' => '<p>Top</p>'])]]);
        $footer = Template::query()->create(['name' => 'Main footer', 'type' => 'footer', 'blocks' => [self::block('text', ['content' => '<p>Bottom</p>'])]]);

        $this->importPage($header, app(PageExport::class)->record($other), editor: TemplateVisualEditor::class)->assertNotified('Imported');
        $this->assertSame('Top', self::outline(app(VisualDrafts::class)->open($header, $this->user())->blocks ?? []));
        $this->assertSame(['always', true], [$header->fresh()->settings['sticky'], $header->fresh()->settings['shrink']], 'A header\'s settings come with it.');

        $this->importPage($header, app(PageExport::class)->record($footer), editor: TemplateVisualEditor::class)->assertNotified('Nothing was imported');
        $this->assertSame('Top', self::outline(app(VisualDrafts::class)->open($header, $this->user())->blocks ?? []), 'A footer doesn\'t go into a header.');
    }

    public function test_a_block_made_for_another_kind_of_template_says_so(): void
    {
        $footerSection = [self::block('section', ['rows' => [['layout' => 'stacked', 'columns' => [['blocks' => [self::block('site_footer_links')]]]]]])];

        app(BlockRegistry::class)->editingTemplate('header');
        $this->assertSame(
            ['blocks.0.data.rows.0.columns.0.blocks.0.type: The "Footer links" block is only for footer templates, so it can\'t go in a header template.'],
            app(PageImport::class)->blocks($footerSection)->errors,
        );

        app(BlockRegistry::class)->editingTemplate(null);
        $this->assertSame(
            ['blocks.0.type: The "Footer links" block is only for footer templates, so it can\'t go on a page or in the block library.'],
            app(PageImport::class)->blocks([self::block('site_footer_links')])->errors,
        );

        app(BlockRegistry::class)->editingTemplate('footer');
        $this->assertFalse(app(PageImport::class)->blocks($footerSection)->failed(), 'It goes in a footer.');
        app(BlockRegistry::class)->editingTemplate(null);

        $header = Template::query()->create(['name' => 'Main header', 'type' => 'header', 'blocks' => [self::block('text', ['content' => '<p>Top</p>'])]]);

        Livewire::test(TemplateVisualEditor::class, ['record' => $header->getRouteKey()])
            ->callAction(TestAction::make('importPart')->arguments(['uid' => $header->blocks[0]['uid']]), [
                'file' => UploadedFile::fake()->createWithContent('export.json', (string) json_encode(app(PageExport::class)->page($footerSection))),
            ])
            ->assertNotified('Nothing was imported');
        $this->assertSame('Top', self::outline(app(VisualDrafts::class)->open($header, $this->user())->blocks ?? $header->blocks));
    }

    public function test_problems_name_their_place_in_words(): void
    {
        $this->assertSame('Block 1 › Row 1 › Column 2 › Block 1: Not allowed.', PageImport::forPeople('blocks.0.data.rows.0.columns.1.blocks.0.type: Not allowed.'));
        $this->assertSame('Block 3 › Item 2 › Button Text: Too long.', PageImport::forPeople('blocks.2.data.items.1.button_text: Too long.'));
        $this->assertSame('The row › Column 1 › Block 2 › Image: Left out.', PageImport::forPeople('row.columns.0.blocks.1.data.image: Left out.'));
        $this->assertSame('It replaces the home page.', PageImport::forPeople('replaces: It replaces the home page.'));
        $this->assertSame('This isn\'t a site builder export.', PageImport::forPeople('This isn\'t a site builder export.'));
        $this->assertSame('A note: with a colon', PageImport::forPeople('A note: with a colon'), 'Only a path is turned into words.');
    }

    public function test_the_library_editor_has_no_import_page(): void
    {
        $item = app(BlockLibrary::class)->save('Join us', 'call_to_action', ['heading' => 'Join us today']);

        Livewire::test(LibraryVisualEditor::class, ['record' => $item->getRouteKey()])->assertActionHidden('importPage');
    }
}
