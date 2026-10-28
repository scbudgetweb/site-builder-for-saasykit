<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockSchema;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Stored blocks' shape (BlockSchema): its version, and the uids on every block, row and column that an editor can
 * point at from save to save.
 */
class BlockSchemaTest extends PageTestCase
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
     * @return array<int, array<string, mixed>>
     */
    private static function sectionPage(): array
    {
        return [
            self::block('text', ['content' => '<p>Intro</p>']),
            self::block('section', ['rows' => [['layout' => '2', 'columns' => [
                ['blocks' => [self::block('text', ['content' => '<p>Left</p>'])]],
                ['blocks' => [self::block('call_to_action', ['heading' => 'Right'])]],
            ]]]]),
        ];
    }

    /**
     * Every uid in stored blocks, in order: block, row, column, the column's blocks…
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, mixed>
     */
    private static function uids(array $blocks): array
    {
        $uids = [];

        foreach ($blocks as $block) {
            $uids[] = $block['uid'] ?? null;

            foreach ($block['data']['rows'] ?? [] as $row) {
                $uids[] = $row['uid'] ?? null;

                foreach ($row['columns'] ?? [] as $column) {
                    $uids = [...$uids, $column['uid'] ?? null, ...self::uids($column['blocks'] ?? [])];
                }
            }
        }

        return $uids;
    }

    /**
     * @return array<int, mixed>
     */
    private static function storedUids(Page $page): array
    {
        return self::uids(json_decode((string) DB::table('site_builder_pages')->where('id', $page->id)->value('blocks'), true));
    }

    public function test_every_block_row_and_column_is_saved_with_its_own_uid_and_the_version(): void
    {
        $page = $this->page(['blocks' => self::sectionPage()]);
        $uids = self::storedUids($page);

        $this->assertCount(7, $uids, 'Two blocks, a row, two columns and a block in each.');
        $this->assertSame($uids, array_values(array_unique(array_filter($uids, fn (mixed $uid): bool => is_string($uid) && preg_match(BlockSchema::UID_PATTERN, $uid) === 1))));
        $this->assertSame(BlockSchema::VERSION, (int) DB::table('site_builder_pages')->where('id', $page->id)->value('blocks_version'));

        $page->update(['blocks' => $page->blocks]);

        $this->assertSame($uids, self::storedUids($page), 'Saving again keeps them.');
    }

    public function test_the_editor_keeps_uids_and_gives_a_new_block_one(): void
    {
        $page = $this->page(['blocks' => self::sectionPage()]);
        $uids = self::storedUids($page);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component
            ->set('data.blocks.new-item', self::block('text', ['content' => '<p>Added</p>']))
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = self::storedUids($page);
        $this->assertSame($uids, array_slice($saved, 0, 7), 'Blocks, rows and columns keep their uids through the editor.');
        $this->assertCount(8, array_unique($saved), 'The new block gets a uid of its own.');
    }

    public function test_a_copied_uid_is_replaced_and_an_unsafe_one_too(): void
    {
        $row = ['uid' => 'row-1', 'layout' => 'stacked', 'columns' => [['uid' => 'column-1', 'blocks' => [['uid' => 'inner', 'type' => 'text', 'data' => []]]]]];
        $blocks = BlockSchema::write([
            ['uid' => 'section-1', 'type' => 'section', 'data' => ['rows' => [$row, $row]]],
            ['uid' => '"><script>', 'type' => 'text', 'data' => []],
        ]);

        $uids = self::uids($blocks);
        $this->assertSame(['section-1', 'row-1', 'column-1', 'inner'], array_slice($uids, 0, 4), 'The first keeps its uid.');
        $this->assertCount(8, array_unique($uids), 'The cloned row, its column and block, and the unsafe uid get new ones.');
        $this->assertNotContains('"><script>', $uids);
    }

    public function test_blocks_saved_without_uids_read_the_same_every_time_and_stay_unchanged(): void
    {
        $page = $this->page(['blocks' => self::sectionPage()]);
        DB::table('site_builder_pages')->where('id', $page->id)->update([
            'blocks' => json_encode([self::block('section', ['rows' => [['layout' => '2', 'blocks' => [self::block('text'), self::block('text')]]]])]),
            'blocks_version' => 1,
        ]);

        $first = Page::query()->find($page->id);
        $second = Page::query()->find($page->id);

        $this->assertSame(self::uids($first->blocks), self::uids($second->blocks));
        $this->assertCount(6, array_unique(self::uids($first->blocks)), 'A section, its row, two columns and a block in each.');
        $this->assertFalse($first->isDirty('blocks'), 'Reading never counts as a change, so the page can still be cached.');
    }

    public function test_a_library_copy_gets_new_uids_and_a_linked_block_keeps_its_links_uid(): void
    {
        $item = app(BlockLibrary::class)->save('Two columns', 'section', self::sectionPage()[1]['data']);
        $libraryUids = self::uids($item->fresh()->blocks);

        $copy = app(BlockLibrary::class)->copy($item->id);
        $this->assertSame([], array_intersect($libraryUids, self::uids([$copy])));

        $page = $this->page(['blocks' => [self::block('saved', ['saved_block_id' => $item->id])]]);
        $linkUid = $page->blocks[0]['uid'];

        $this->assertSame([$linkUid, 'section'], [$page->blockList()[0]['uid'], $page->blockList()[0]['type']]);
    }

    public function test_a_revision_records_the_version_and_restores_the_uids(): void
    {
        $page = $this->page(['blocks' => self::sectionPage()]);
        $revision = $page->revisions()->sole();

        $this->assertSame(BlockSchema::VERSION, $revision->snapshot['blocks_version']);
        $this->assertSame(self::storedUids($page), self::uids($revision->attributesToRestore()['blocks']));
    }
}
