<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\ListSavedBlocks;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\EditTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\ListTemplates;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\PageExport;

/**
 * Import outside the visual builder: Edit as a form (the whole page, or after a block card) and the Theme builder's
 * and Library's lists (a new template or library block).
 */
class ImportFormsAndListsTest extends PageTestCase
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
     * @param  array<string, mixed>  $file
     */
    private static function file(array $file): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('export.json', (string) json_encode($file));
    }

    /**
     * @return array<string, mixed>
     */
    private static function textExport(string ...$texts): array
    {
        return app(PageExport::class)->page(array_map(fn (string $text): array => self::block('text', ['content' => "<p>{$text}</p>"]), $texts));
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     */
    private static function texts(array $blocks): string
    {
        // In a form, rich text is the editor's document (an array): its words are its "text" values.
        $words = fn (mixed $content): string => is_array($content)
            ? (preg_match_all('/"text":"([^"]*)"/', (string) json_encode($content), $matches) > 0 ? implode('', $matches[1]) : '')
            : strip_tags((string) $content);

        return implode(' ', array_map(fn (array $block): string => isset($block['data']['content']) ? $words($block['data']['content']) : $block['type'], array_values($blocks)));
    }

    public function test_the_page_form_imports_into_the_form_until_it_is_saved(): void
    {
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>One</p>']), self::block('text', ['content' => '<p>Two</p>'])]]);
        $editor = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $editor->callAction('importPage', ['file' => self::file(self::textExport('X')), 'replace' => false])->assertNotified('Imported');
        $this->assertSame('One Two X', self::texts($editor->get('data.blocks')), 'Off: added at the end.');
        $this->assertSame('One Two', self::texts($page->fresh()->blocks), 'Nothing kept until Save.');

        $editor->callAction('importPage', ['file' => self::file(self::textExport('New'))])->assertNotified('Imported');
        $this->assertSame('New', self::texts($editor->get('data.blocks')));

        $editor->call('save')->assertHasNoFormErrors();
        $this->assertSame('New', self::texts($page->fresh()->blocks));
        $this->assertSame('About us', $page->fresh()->title);
    }

    public function test_a_block_card_imports_after_itself(): void
    {
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>One</p>']), self::block('text', ['content' => '<p>Two</p>'])]]);
        $editor = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $first = array_key_first($editor->get('data.blocks'));

        $editor->callAction(TestAction::make('importBlocks')->schemaComponent('blocks')->arguments(['item' => $first]), ['file' => self::file(self::textExport('X', 'Y'))])
            ->assertHasNoActionErrors()
            ->assertNotified('Imported');
        $this->assertSame('One X Y Two', self::texts($editor->get('data.blocks')));

        $editor = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $editor->callAction(TestAction::make('importBlocks')->schemaComponent('blocks')->arguments(['item' => array_key_first($editor->get('data.blocks'))]), ['file' => self::file(['site_builder' => 1, 'kind' => 'row', 'row' => ['layout' => 'stacked', 'columns' => [['blocks' => []]]]])])
            ->assertNotified('Nothing was imported');
        $this->assertSame('One Two', self::texts($editor->get('data.blocks')), 'Rows go in from the visual builder.');
    }

    public function test_a_header_template_form_takes_a_headers_file_and_its_settings(): void
    {
        $header = Template::query()->create(['name' => 'Main header', 'type' => 'header', 'blocks' => [self::block('text', ['content' => '<p>Old</p>'])]]);
        $other = Template::query()->create(['name' => 'Other', 'type' => 'header', 'settings' => ['sticky' => 'always'], 'blocks' => [self::block('text', ['content' => '<p>Top</p>'])]]);
        $footer = Template::query()->create(['name' => 'Footer', 'type' => 'footer', 'blocks' => [self::block('text', ['content' => '<p>Bottom</p>'])]]);
        Livewire::test(EditTemplate::class, ['record' => $header->getRouteKey()])
            ->callAction('importPage', ['file' => self::file(app(PageExport::class)->record($footer))])
            ->assertNotified('Nothing was imported');

        $editor = Livewire::test(EditTemplate::class, ['record' => $header->getRouteKey()]);
        $editor->callAction('importPage', ['file' => self::file(app(PageExport::class)->record($other))])->assertNotified('Imported');
        $this->assertSame('Top', self::texts($editor->get('data.blocks')));
        $this->assertSame('always', $editor->get('data.settings.sticky'));
    }

    public function test_the_theme_builder_list_imports_a_new_template_turned_off(): void
    {
        $original = Template::query()->create(['name' => 'Main header', 'type' => 'header', 'settings' => ['sticky' => 'always'], 'blocks' => [self::block('text', ['content' => '<p>Top</p>'])]]);

        Livewire::test(ListTemplates::class)
            ->callAction('import', ['name' => 'Imported header', 'file' => self::file(app(PageExport::class)->record($original))])
            ->assertHasNoActionErrors()
            ->assertNotified('Imported');

        $template = Template::query()->where('name', 'Imported header')->sole();
        $this->assertSame(['header', false, 'always', 'Top'], [$template->type, $template->is_active, $template->settings['sticky'], self::texts($template->blocks)]);

        Livewire::test(ListTemplates::class)
            ->callAction('import', ['name' => 'Not a template', 'file' => self::file(self::textExport('X'))])
            ->assertNotified('Nothing was imported');
        $this->assertSame(2, Template::query()->count());
    }

    public function test_the_library_list_imports_one_block_as_a_new_library_block(): void
    {
        $section = app(PageExport::class)->page([self::block('section', ['rows' => [['layout' => 'stacked', 'columns' => [['blocks' => [self::block('text', ['content' => '<p>Inside</p>'])]]]]]])]);

        Livewire::test(ListSavedBlocks::class)
            ->callAction('import', ['name' => 'Opening hours', 'file' => self::file($section)])
            ->assertHasNoActionErrors()
            ->assertNotified('Imported');

        $item = SavedBlock::query()->sole();
        $this->assertSame(['Opening hours', 'section'], [$item->name, $item->type]);

        Livewire::test(ListSavedBlocks::class)
            ->callAction('import', ['name' => 'Two blocks', 'file' => self::file(self::textExport('X', 'Y'))])
            ->assertNotified('Nothing was imported');
        $this->assertSame(1, SavedBlock::query()->count(), 'A library block is one block.');
    }
}
