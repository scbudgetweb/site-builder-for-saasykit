<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\PageRevision;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;

/**
 * Page revisions (HB-96, D0a-5, D0a-11).
 */
class PageRevisionsTest extends PageTestCase
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

    public function test_each_save_that_changes_the_page_is_kept_with_who_saved_it(): void
    {
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>First</p>'])]]);
        $this->assertSame(1, $page->revisions()->count());

        $page->update(['blocks' => [self::block('text', ['content' => '<p>Second</p>'])], 'seo_title' => 'About']);
        // Nothing a revision keeps has changed.
        $page->update(['status' => PageStatus::Draft, 'slug' => 'about']);
        $page->touch();

        $revisions = $page->revisions()->latest('id')->get();
        $this->assertCount(2, $revisions);
        $this->assertSame('Test', $revisions[0]->user_name);
        $this->assertSame('<p>Second</p>', $revisions[0]->snapshot['blocks'][0]['data']['content']);
        $this->assertSame('About', $revisions[0]->snapshot['seo_title']);
        $this->assertArrayNotHasKey('slug', $revisions[0]->snapshot);
    }

    public function test_only_the_newest_revisions_are_kept(): void
    {
        config(['site-builder.pages.revisions' => 3]);
        $page = $this->page(['title' => 'Version 1']);

        foreach (range(2, 5) as $version) {
            $page->update(['title' => "Version {$version}"]);
        }

        $this->assertSame(['Version 5', 'Version 4', 'Version 3'], $page->revisions()->latest('id')->get()->map(fn (PageRevision $revision) => $revision->snapshot['title'])->all());
    }

    public function test_images_stay_while_a_revision_uses_them_and_go_once_it_is_pruned(): void
    {
        config(['site-builder.pages.revisions' => 2]);
        Storage::disk('public')->put('site-builder/pages/old.jpg', 'image');

        $page = $this->page(['blocks' => [self::block('image_text', ['image' => 'site-builder/pages/old.jpg'])]]);
        $page->update(['blocks' => [self::block('text', ['content' => '<p>No image</p>'])]]);

        // The first revision can still be restored, so its image is kept.
        Storage::disk('public')->assertExists('site-builder/pages/old.jpg');

        $page->update(['title' => 'Renamed']);
        Storage::disk('public')->assertMissing('site-builder/pages/old.jpg');
    }

    public function test_deleting_a_page_for_good_removes_its_revisions_and_their_images(): void
    {
        Storage::disk('public')->put('site-builder/pages/old.jpg', 'image');
        $page = $this->page(['blocks' => [self::block('image_text', ['image' => 'site-builder/pages/old.jpg'])]]);
        $page->update(['blocks' => []]);

        $page->delete();
        $this->assertSame(2, PageRevision::query()->count(), 'A page in the bin keeps its revisions.');
        Storage::disk('public')->assertExists('site-builder/pages/old.jpg');

        $page->forceDelete();
        $this->assertSame(0, PageRevision::query()->count());
        Storage::disk('public')->assertMissing('site-builder/pages/old.jpg');
    }

    public function test_the_editor_lists_revisions_and_restore_loads_one_as_unsaved_changes(): void
    {
        $page = $this->page(['title' => 'Old title', 'slug' => 'about-us', 'blocks' => [self::block('text', ['content' => '<p>Old words</p>'])]]);
        $first = $page->revisions()->sole();
        $page->update(['title' => 'New title', 'blocks' => [self::block('text', ['content' => '<p>New words</p>'])]]);

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('revisions'))
            ->assertActionMounted(TestAction::make('revisions'));

        // The list (modal content isn't in the test HTML): newest first, the current one can't be restored.
        $list = view('site-builder::filament.pages.revisions', ['revisions' => $page->revisions()->latest('id')->get()])->render();
        $this->assertSame(1, substr_count($list, 'Current'));
        $this->assertSame(1, substr_count($list, 'wire:click="restoreRevision('));
        $this->assertStringContainsString('Test', $list);
        $this->assertStringContainsString('revisions/'.$first->getKey().'?expires=', $list);

        $component
            ->call('restoreRevision', $first->getKey())
            ->assertNotified('Revision loaded')
            ->assertSchemaStateSet(['title' => 'Old title', 'slug' => 'about-us']);

        $this->assertSame('New title', $page->fresh()->title, 'Nothing is saved until the page is.');
        $this->assertSame([], $component->get('mountedActions'));

        $component->call('save')->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame('Old title', $page->title);
        $this->assertStringContainsString('Old words', $page->blockList()[0]['data']['content']);
        $this->assertSame(3, $page->revisions()->count(), 'Saving the restored version makes a new revision.');
    }

    public function test_another_pages_revision_cant_be_restored(): void
    {
        $other = $this->page(['title' => 'Other', 'slug' => 'other']);
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->call('restoreRevision', $other->revisions()->sole()->getKey())
            ->assertNotified('That revision no longer exists.')
            ->assertSchemaStateSet(['title' => 'About us']);
    }

    public function test_a_revision_preview_shows_the_old_version_through_a_signed_link_only(): void
    {
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Old words</p>'])]]);
        $revision = $page->revisions()->sole();
        $page->update(['blocks' => [self::block('text', ['content' => '<p>New words</p>'])]]);

        $this->get($revision->previewUrl())
            ->assertSuccessful()
            ->assertSee('Old words')
            ->assertDontSee('New words')
            ->assertSee('earlier version of this page')
            ->assertSee('noindex', false);

        $this->assertSame('<p>New words</p>', $page->fresh()->blocks[0]['data']['content']);

        $this->withExceptionHandling();
        $this->get(route('site-builder.pages.revision-preview', ['page' => $page->id, 'revision' => $revision->id]))->assertForbidden();

        $other = $this->page(['slug' => 'other']);
        $this->get(URL::temporarySignedRoute('site-builder.pages.revision-preview', now()->addHour(), ['page' => $other->id, 'revision' => $revision->id]))->assertNotFound();
    }
}
