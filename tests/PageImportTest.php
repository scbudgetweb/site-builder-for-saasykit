<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;
use ScBudgetWeb\SiteBuilder\Pages\PageImportResult;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;

/**
 * site-builder:page builds a page from JSON, checked by the page form and each block's own fields as Save in the
 * editor does, and refuses what the form would quietly drop. Nothing is saved when there's a problem.
 */
class PageImportTest extends PageTestCase
{
    /** A 1 × 1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->folder = sys_get_temp_dir().'/sb-import-'.uniqid();
        File::ensureDirectoryExists($this->folder.'/images');
        File::put($this->folder.'/images/hall.png', (string) base64_decode(self::PNG));
        File::put($this->folder.'/images/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');
        File::put($this->folder.'/images/unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>');
        File::put($this->folder.'/images/notes.png', 'not really an image');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function import(array $page, bool $update = false, bool $dryRun = false): PageImportResult
    {
        return app(PageImport::class)->import($page, $this->folder, $update, $dryRun);
    }

    /**
     * @return array<string, mixed>
     */
    private static function villageHall(): array
    {
        return [
            'title' => 'Village Hall',
            'slug' => 'village-hall',
            'blocks' => [
                ['type' => 'hero', 'data' => ['heading' => 'Welcome to Sometown Village Hall', 'buttons' => [['label' => 'Book a room', 'url' => '/book']]]],
                ['type' => 'stats', 'data' => ['items' => [['source' => 'custom', 'value' => 1923, 'label' => 'Built', 'format' => 'plain']]]],
                ['type' => 'section', 'data' => ['rows' => [['layout' => '2', 'columns' => [
                    ['blocks' => [['type' => 'text', 'data' => ['content' => '<h2>Rooms</h2><p>Two halls and a kitchen.</p>']]]],
                    ['blocks' => [['type' => 'image', 'data' => ['image' => 'file:images/hall.png', 'alt' => 'The main hall']]]],
                ]]]]],
            ],
        ];
    }

    public function test_a_page_is_built_from_json(): void
    {
        $result = $this->import(self::villageHall());

        $this->assertSame([], $result->errors);
        $this->assertTrue($result->isNew);
        $page = Page::query()->where('slug', 'village-hall')->firstOrFail();
        $this->assertSame(PageStatus::Draft, $page->status, 'Draft unless it says published.');

        $blocks = $page->blockList();
        $this->assertSame(['hero', 'stats', 'section'], array_column($blocks, 'type'));
        $this->assertNotEmpty($blocks[0]['uid']);
        $this->assertCount(1, $blocks[0]['data']['buttons'], 'Only the rows given.');
        $this->assertSame([], $blocks[0]['data']['proof_avatars']);

        $image = $blocks[2]['data']['rows'][0]['columns'][1]['blocks'][0]['data']['image'];
        $this->assertStringStartsWith('site-builder/pages/', $image);
        $this->assertStringEndsWith('.png', $image);
        Storage::disk('public')->assertExists($image);

        $page->update(['status' => PageStatus::Published]);
        $this->get('/village-hall')->assertOk()
            ->assertSee('Welcome to Sometown Village Hall')
            ->assertSee('Two halls and a kitchen.')
            ->assertSee('1923')
            ->assertSee('/storage/'.$image, false);
    }

    public function test_problems_are_listed_by_their_place_in_the_json_and_nothing_is_saved(): void
    {
        $page = self::villageHall();
        $page['title'] = '';
        $page['colour'] = 'green';
        $page['blocks'][0]['data']['headng'] = 'Typo';
        $page['blocks'][1]['data']['items'][0]['value'] = 'lots';
        $page['blocks'][2]['data']['rows'][0]['columns'][0]['blocks'][] = ['type' => 'hero', 'data' => []];
        $page['blocks'][] = ['type' => 'carousel', 'data' => []];

        $result = $this->import($page);

        $this->assertSame([
            'colour: Not a page setting. A page has: title, slug, status, replaces, show_page_banner, seo_title, seo_description, share_image, noindex, blocks.',
            'blocks.2.data.rows.0.columns.0.blocks.1.type: A Hero can\'t go in a Section\'s column.',
            'blocks.3.type: Unknown block type "carousel". The types are: '.implode(', ', array_keys(app(BlockRegistry::class)->offered())).'.',
        ], $result->errors, 'Shape problems come first: the form would drop those blocks without a word.');
        $this->assertSame(0, Page::query()->count());

        array_pop($page['blocks']);
        array_pop($page['blocks'][2]['data']['rows'][0]['columns'][0]['blocks']);
        unset($page['colour']);

        $result = $this->import($page);

        $this->assertContains('title: The title field is required.', $result->errors);
        $this->assertContains('blocks.0.data.headng: Not a setting here. Check its spelling, and that it belongs to this block.', $result->errors);
        $this->assertSame(0, Page::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles('site-builder/pages'), 'An image it uploaded goes again.');

        $page['title'] = 'Village Hall';
        unset($page['blocks'][0]['data']['headng']);

        $this->assertSame(['blocks.1.data.items.0.value: Must be a number.'], $this->import($page)->errors, 'The form turns "lots" into 0 without a word.');
    }

    public function test_each_block_is_checked_by_its_own_fields(): void
    {
        $result = $this->import(['title' => 'Village Hall', 'slug' => 'village-hall', 'blocks' => [
            ['type' => 'features', 'data' => ['heading' => 'Why book with us', 'items' => [['title' => 'Big hall'], ['text' => 'No title']]]],
            ['type' => 'image', 'data' => ['image' => 'site-builder/pages/missing.jpg']],
        ]]);

        $this->assertContains('blocks.0.data.items.1.title: The title field is required.', $result->errors);
        $this->assertContains('blocks.1.data.image: The image field is required.', $result->errors);
        $this->assertContains('blocks.1.data.alt: The alternative text field is required.', $result->errors);
    }

    public function test_a_list_holds_only_the_rows_given(): void
    {
        $result = $this->import(['title' => 'Village Hall', 'slug' => 'village-hall', 'blocks' => [['type' => 'features', 'data' => ['heading' => 'Why book with us']]]]);

        $this->assertSame(['blocks.0.data.items: The features field must have at least 1 items.'], $result->errors, 'No empty rows from its default of three.');
    }

    public function test_an_optional_image_that_isnt_an_upload_is_refused(): void
    {
        $result = $this->import(['title' => 'Village Hall', 'slug' => 'village-hall', 'blocks' => [
            ['type' => 'hero', 'data' => ['heading' => 'Welcome', 'feature_image' => 'https://example.com/hall.jpg']],
        ]]);

        $this->assertSame(['blocks.0.data.feature_image: Not accepted. An image must be a file already uploaded to site-builder/pages, or "file:…" to upload one.'], $result->errors);
    }

    public function test_image_files_get_an_uploads_checks(): void
    {
        $result = $this->import(['title' => 'Village Hall', 'slug' => 'village-hall', 'share_image' => 'file:images/logo.svg', 'blocks' => [
            ['type' => 'image', 'data' => ['image' => 'file:images/unsafe.svg', 'alt' => 'Logo']],
            ['type' => 'image', 'data' => ['image' => 'file:images/notes.png', 'alt' => 'Notes']],
            ['type' => 'image', 'data' => ['image' => 'file:images/missing.png', 'alt' => 'Missing']],
            ['type' => 'image', 'data' => ['image' => 'file:images/hall.png', 'alt' => 'The main hall']],
        ]]);

        $this->assertCount(4, $result->errors);
        $this->assertStringStartsWith('blocks.0.data.image: ', $result->errors[0]);
        $this->assertSame('blocks.1.data.image: Not an image the site takes: JPG, PNG, WebP, AVIF or SVG.', $result->errors[1]);
        $this->assertSame('blocks.2.data.image: No file at '.$this->folder.'/images/missing.png.', $result->errors[2]);
        $this->assertSame('share_image: Not an image the site takes here: JPG, PNG, WebP or AVIF.', $result->errors[3]);
        $this->assertSame([], Storage::disk('public')->allFiles('site-builder/pages'), 'The good one goes again too.');
    }

    public function test_a_setting_another_one_hides_is_a_warning(): void
    {
        $result = $this->import(['title' => 'Village Hall', 'slug' => 'village-hall', 'blocks' => [
            ['type' => 'text', 'data' => ['content' => '<p>Hello</p>', 'appearance' => ['background' => 'light', 'background_color' => '#0b1f44']]],
        ]]);

        $this->assertSame([], $result->errors);
        $this->assertSame(['blocks.0.data.appearance.background_color: Left out: it only applies when another setting turns it on.'], $result->warnings);
    }

    public function test_an_existing_page_changes_only_with_update(): void
    {
        $this->import(self::villageHall());
        $page = Page::query()->where('slug', 'village-hall')->firstOrFail();

        $change = ['slug' => 'village-hall', 'seo_title' => 'Sometown Village Hall', 'blocks' => [['type' => 'text', 'data' => ['content' => '<p>New</p>']]]];

        $this->assertSame(['slug: A page already uses this slug. Use --update to change it, or choose another slug.'], $this->import($change)->errors);

        $result = $this->import($change, update: true);

        $this->assertSame([], $result->errors);
        $this->assertFalse($result->isNew);
        $page->refresh();
        $this->assertSame('Village Hall', $page->title, 'Settings not given stay as they are.');
        $this->assertSame('Sometown Village Hall', $page->seo_title);
        $this->assertSame(['text'], array_column($page->blockList(), 'type'));
        $this->assertSame(2, $page->revisions()->count(), 'Saved as any save is, with a revision.');

        $page->delete();
        $this->assertSame(['slug: The page with this slug is in the bin. Restore it first, or use another slug.'], $this->import($change, update: true)->errors);
    }

    public function test_a_dry_run_saves_nothing(): void
    {
        $result = $this->import(self::villageHall(), dryRun: true);

        $this->assertSame([], $result->errors);
        $this->assertNull($result->page);
        $this->assertSame(0, Page::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles('site-builder/pages'));
    }

    public function test_the_command_imports_a_json_file(): void
    {
        File::put($this->folder.'/page.json', (string) json_encode([...self::villageHall(), 'status' => 'published']));
        File::put($this->folder.'/broken.json', '{"title": "Village Hall",');
        File::put($this->folder.'/typo.json', (string) json_encode(['title' => 'Village Hall', 'slug' => 'typo', 'blocks' => [['type' => 'txt']]]));

        $this->artisan('site-builder:page', ['file' => $this->folder.'/page.json', '--dry-run' => true])
            ->expectsOutputToContain('The page is fine (nothing saved: dry run).')
            ->assertSuccessful();
        $this->assertSame(0, Page::query()->count());

        $this->artisan('site-builder:page', ['file' => $this->folder.'/page.json'])
            ->expectsOutputToContain('Created: Village Hall (Published, ')
            ->assertSuccessful();
        $this->get('/village-hall')->assertOk()->assertSee('Welcome to Sometown Village Hall');

        $this->artisan('site-builder:page', ['file' => $this->folder.'/typo.json'])
            ->expectsOutputToContain('blocks.0.type: Unknown block type "txt".')
            ->expectsOutputToContain('1 problem: nothing was saved.')
            ->assertFailed();

        $this->artisan('site-builder:page', ['file' => $this->folder.'/broken.json'])->expectsOutputToContain('Not valid JSON')->assertFailed();
        $this->artisan('site-builder:page', ['file' => $this->folder.'/none.json'])->expectsOutputToContain('No file at')->assertFailed();
    }
}
