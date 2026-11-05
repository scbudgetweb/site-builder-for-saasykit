<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockPresets;
use ScBudgetWeb\SiteBuilder\Blocks\BlockSchema;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\ListPages;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\ListSavedBlocks;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\ListTemplates;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\PageExport;
use ScBudgetWeb\SiteBuilder\Templates\HeaderBehaviour;

/**
 * Export (PageExport): a page, template, library block, or one Section, row, column or block, as a JSON file. What
 * only exists on this site (library links, presets, saved styles) is written out in full, and no uids are kept.
 */
class PageExportTest extends PageTestCase
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

        Carbon::setTestNow('2026-11-03 12:00:00');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
    }

    /**
     * Text "Intro", then a Section with a two-column row (A | B) and a one-column row (C).
     */
    private function exportPage(): Page
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
     * Every key anywhere in the file.
     *
     * @param  array<int|string, mixed>  $value
     * @return array<int, int|string>
     */
    private static function keys(array $value): array
    {
        $keys = array_keys($value);

        foreach ($value as $item) {
            if (is_array($item)) {
                $keys = [...$keys, ...self::keys($item)];
            }
        }

        return $keys;
    }

    public function test_a_page_exports_its_blocks_only_without_uids(): void
    {
        $page = $this->exportPage();
        $file = app(PageExport::class)->page($page->blocks);

        $this->assertSame([1, 'page', BlockSchema::VERSION, '2026-11-03T12:00:00+00:00'], [$file['site_builder'], $file['kind'], $file['schema'], $file['exported_at']]);
        $this->assertSame(['site_builder', 'kind', 'schema', 'exported_at', 'blocks'], array_keys($file), 'No title, address or SEO.');
        $this->assertSame(['text', 'section'], array_column($file['blocks'], 'type'));
        $this->assertNotContains('uid', self::keys($file), 'An import gives new ones.');

        $rows = $file['blocks'][1]['data']['rows'];
        $this->assertSame([0, 1], array_keys($rows), 'Lists, not keyed by the form\'s ids.');
        $this->assertSame('<p>B</p>', $rows[0]['columns'][1]['blocks'][0]['data']['content']);
        $this->assertSame('<p>C</p>', $rows[1]['columns'][0]['blocks'][0]['data']['content']);
    }

    public function test_one_section_block_row_or_column_exports_on_its_own(): void
    {
        $blocks = $this->exportPage()->blocks;
        $section = $blocks[1];
        $rows = array_values($section['data']['rows']);
        $export = app(PageExport::class);

        $this->assertSame(['section', 'section'], [$export->part($blocks, $section['uid'])['kind'], $export->part($blocks, $section['uid'])['blocks'][0]['type']]);
        $this->assertSame('block', $export->part($blocks, $blocks[0]['uid'])['kind']);

        $row = $export->part($blocks, $rows[0]['uid']);
        $this->assertSame(['row', '2'], [$row['kind'], (string) $row['row']['layout']]);
        $this->assertSame(['<p>A</p>', '<p>B</p>'], array_map(fn (array $column): string => $column['blocks'][0]['data']['content'], $row['row']['columns']));

        $columns = array_values($rows[0]['columns']);
        $column = $export->part($blocks, $columns[1]['uid']);
        $this->assertSame(['column', '<p>B</p>'], [$column['kind'], $column['column']['blocks'][0]['data']['content']]);

        $nested = $export->part($blocks, array_values($columns[0]['blocks'])[0]['uid']);
        $this->assertSame(['block', '<p>A</p>'], [$nested['kind'], $nested['blocks'][0]['data']['content']]);

        foreach ([$row, $column, $nested] as $file) {
            $this->assertNotContains('uid', self::keys($file));
        }

        $this->assertNull($export->part($blocks, 'nothing-has-this-uid'));
    }

    public function test_library_links_presets_and_saved_styles_are_written_out_in_full(): void
    {
        $item = app(BlockLibrary::class)->save('Join us', 'call_to_action', ['heading' => 'Join us today']);
        $presets = app(BlockPresets::class);
        $presets->create('text', 'Navy panel', ['appearance' => ['background' => 'custom', 'background_color' => '#0b1f44']], isDefault: true);
        $style = $presets->createStyle('heading', 'Gold headings', ['design' => ['typography' => ['heading' => ['colour' => '#f59e0b']]]]);

        $page = $this->page(['blocks' => [
            self::block('saved', ['saved_block_id' => $item->getKey()]),
            self::block('text', ['content' => '<p>Styled</p>', 'style_presets' => ['heading' => $style]]),
        ]]);
        $file = app(PageExport::class)->page($page->blocks);

        $this->assertSame(['call_to_action', 'Join us today'], [$file['blocks'][0]['type'], $file['blocks'][0]['data']['heading']], 'The library block itself, not a link to it.');
        $this->assertNotContains('saved_block_id', self::keys($file));

        $text = $file['blocks'][1]['data'];
        $this->assertSame('#0b1f44', $text['appearance']['background_color'], 'The default preset\'s settings.');
        $this->assertSame('#f59e0b', $text['design']['typography']['heading']['colour'], 'The saved style\'s.');
        $this->assertArrayNotHasKey('style_presets', $text);
        $this->assertArrayNotHasKey('preset', $text);
    }

    public function test_a_template_exports_its_type_and_settings_but_not_where_it_shows(): void
    {
        $template = Template::query()->create([
            'name' => 'Main header',
            'type' => 'header',
            'is_active' => true,
            'applies_to' => 'pages',
            'page_ids' => [1, 2],
            'settings' => [...HeaderBehaviour::defaults(), 'sticky' => 'always'],
            'blocks' => [self::block('text', ['content' => '<p>Top</p>'])],
        ]);
        $file = app(PageExport::class)->record($template);

        $this->assertSame(['template', 'header', 'always'], [$file['kind'], $file['template']['type'], $file['template']['settings']['sticky']]);
        $this->assertSame(['type', 'settings'], array_keys($file['template']));
        $this->assertSame('<p>Top</p>', $file['blocks'][0]['data']['content']);
        $this->assertNotContains('page_ids', self::keys($file));
        $this->assertNotContains('applies_to', self::keys($file));
    }

    public function test_file_names_say_what_and_when(): void
    {
        $this->assertSame('about-us-page-2026-11-03.json', PageExport::fileName('About us', 'page'));
        $this->assertSame('home-pricing-section-2026-11-03.json', PageExport::fileName('Home Pricing section', 'section'));
        $this->assertSame('row-2026-11-03.json', PageExport::fileName('', 'row'));
    }

    public function test_the_visual_builder_exports_the_draft_and_each_part(): void
    {
        $page = $this->exportPage();
        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()]);

        $editor->callAction('export')->assertFileDownloaded('about-us-page-2026-11-03.json');

        $rowUid = array_values($page->blocks[1]['data']['rows'])[0]['uid'];
        $editor->call('exportPart', $rowUid)->assertFileDownloaded('about-us-row-2026-11-03.json');
        $editor->call('exportPart', $page->blocks[1]['uid'])->assertFileDownloaded('about-us-section-2026-11-03.json');

        $editor->call('renameLayer', $rowUid, 'Opening hours');
        $editor->call('exportPart', $rowUid)->assertFileDownloaded('about-us-opening-hours-row-2026-11-03.json', null, 'application/json');

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->call('exportPart', 'nothing-has-this-uid')
            ->assertNotified('That can\'t be done there.')
            ->assertNoFileDownloaded();
    }

    public function test_lists_and_the_form_editor_export_too(): void
    {
        $page = $this->exportPage();
        $template = Template::query()->create(['name' => 'Main footer', 'type' => 'footer', 'blocks' => []]);
        $item = app(BlockLibrary::class)->save('Join us', 'call_to_action', ['heading' => 'Join us today']);

        Livewire::test(ListPages::class)->callAction(TestAction::make('export')->table($page))->assertFileDownloaded('about-us-page-2026-11-03.json');
        Livewire::test(ListTemplates::class)->callAction(TestAction::make('export')->table($template))->assertFileDownloaded('main-footer-template-2026-11-03.json');
        Livewire::test(ListSavedBlocks::class)->callAction(TestAction::make('export')->table($item))->assertFileDownloaded('join-us-library-2026-11-03.json');

        $editor = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $editor->callAction('export')->assertFileDownloaded('about-us-page-2026-11-03.json');

        $key = array_key_first($editor->get('data.blocks'));
        $editor->callAction(TestAction::make('exportBlock')->schemaComponent('blocks')->arguments(['item' => $key]))
            ->assertHasNoActionErrors()
            ->assertFileDownloaded();
    }
}
