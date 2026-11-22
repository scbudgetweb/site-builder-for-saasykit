<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\ListPages;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\EditSavedBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\ListSavedBlocks;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Pages\EditLocks;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

/**
 * One editor at a time: whoever opens a page, library block or template first holds it while their editor is open;
 * anyone else sees who, gets it read-only, and is refused on the server. The lock lapses once the editor is closed.
 */
class EditLockTest extends PageTestCase
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
        // A second admin, Sam, alongside Alex (admin@example.com).
        Gate::before(fn (User $user): ?bool => $user->getAttribute('email') === 'sam@example.com' ? true : null);
    }

    private function alex(): User
    {
        return tap($this->user(), fn (User $user) => $user->forceFill(['name' => 'Alex']));
    }

    private function sam(): User
    {
        return tap($this->user('sam@example.com'), fn (User $user) => $user->forceFill(['name' => 'Sam']));
    }

    /**
     * A new request's lookups (EditLocks remembers them for one request).
     */
    private function locks(): EditLocks
    {
        app()->forgetScopedInstances();

        return app(EditLocks::class);
    }

    private function textPage(): Page
    {
        return $this->page(['blocks' => [self::block('text', ['content' => '<p>Welcome to the Village Hall</p>'])]]);
    }

    public function test_one_person_holds_the_lock_while_they_renew_it_and_it_lapses_once_they_stop(): void
    {
        $page = $this->textPage();

        $this->assertTrue($this->locks()->claim($page, $this->alex()));
        $this->assertFalse($this->locks()->claim($page, $this->sam()));
        $this->assertSame('Alex', $this->locks()->heldByOther($page, $this->sam())['name']);
        $this->assertNull($this->locks()->heldByOther($page, $this->alex()));

        // Renewed for as long as the editor is open, however idle.
        foreach (range(1, 10) as $beat) {
            $this->travel(EditLocks::HEARTBEAT)->seconds();
            $this->assertTrue($this->locks()->claim($page, $this->alex()));
        }
        $this->assertFalse($this->locks()->claim($page, $this->sam()));

        // Only its holder can free it.
        $this->locks()->release($page, $this->sam()->getAuthIdentifier());
        $this->assertNotNull($this->locks()->heldByOther($page, $this->sam()));
        $this->locks()->release($page, $this->alex()->getAuthIdentifier());
        $this->assertTrue($this->locks()->claim($page, $this->sam()));

        // A closed tab: no more renewals, so it lapses.
        $this->travel(EditLocks::EXPIRES + 1)->seconds();
        $this->assertNull($this->locks()->heldByOther($page, $this->alex()));
        $this->assertTrue($this->locks()->claim($page, $this->alex()));
    }

    public function test_the_lists_show_who_is_editing_and_keep_the_editor_closed(): void
    {
        $page = $this->textPage();
        $item = SavedBlock::query()->create(['name' => 'Opening hours', 'blocks' => [self::block('text', ['content' => '<p>Open daily</p>'])]]);

        $this->actingAs($this->alex());
        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->assertSet('editLockHolder', null);
        Livewire::test(EditSavedBlock::class, ['record' => $item->getRouteKey()])->assertSet('editLockHolder', null);

        // Alex's own list is as usual.
        $this->locks();
        Livewire::test(ListPages::class)
            ->assertActionHasIcon(TestAction::make('visual')->table($page), Heroicon::OutlinedPaintBrush)
            ->assertActionHasUrl(TestAction::make('visual')->table($page), PageResource::getUrl('visual', ['record' => $page]));

        $this->actingAs($this->sam());
        $this->locks();

        foreach (['visual', 'edit'] as $name) {
            Livewire::test(ListPages::class)
                ->assertActionHasIcon(TestAction::make($name)->table($page), Heroicon::OutlinedInformationCircle)
                ->assertActionHasColor(TestAction::make($name)->table($page), 'gray')
                ->assertActionDoesNotHaveUrl(TestAction::make($name)->table($page), PageResource::getUrl($name, ['record' => $page]))
                ->assertSee('Alex is editing this page')
                ->callAction(TestAction::make($name)->table($page))
                ->assertNotified('Alex is editing this page');
        }

        Livewire::test(ListPages::class)->assertActionVisible(TestAction::make('preview')->table($page))->assertActionVisible(TestAction::make('view')->table($page));
        Livewire::test(ListSavedBlocks::class)
            ->assertActionHasIcon(TestAction::make('visual')->table($item), Heroicon::OutlinedInformationCircle)
            ->assertSee('Alex is editing this block');
    }

    public function test_the_second_admin_gets_the_visual_builder_read_only_and_cant_change_or_publish(): void
    {
        $page = $this->textPage();
        $uid = $page->blocks[0]['uid'];

        $this->actingAs($this->alex());
        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()]);

        $this->actingAs($this->sam());
        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->assertSet('editLockHolder.name', 'Alex')
            ->assertSee('Alex is editing this page')
            ->assertSee('You can look, but not change it until they close it.')
            ->assertActionDisabled('publish');

        $component->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]))
            ->assertActionNotMounted('editBlock')
            ->assertNotified('Alex is editing this page');
        $component->call('deleteNode', $uid);
        $component->call('toggleLayerHidden', $uid);
        $this->assertSame([$uid], array_column(app(VisualDrafts::class)->open($page, $this->sam())->blocks ?? [], 'uid'));
        $this->assertFalse(app(VisualDrafts::class)->open($page, $this->sam())->blocks[0]['hidden'] ?? false);

        // Looking stays open: Preview and Revisions.
        $component->mountAction('revisions')->assertActionMounted('revisions');

        // The heartbeat says when Alex has gone, without taking the lock: Start editing reloads with fresh content.
        $this->assertSame('locked', $component->instance()->keepEditLock());
        $this->travel(EditLocks::EXPIRES + 1)->seconds();
        $this->locks();
        $this->assertSame('free', $component->instance()->keepEditLock());
        $this->assertNull($this->locks()->holder($page));

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->assertSet('editLockHolder', null);
    }

    public function test_the_second_admin_gets_the_form_read_only_and_a_save_is_refused(): void
    {
        $page = $this->textPage();

        $this->actingAs($this->alex());
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $this->actingAs($this->sam());
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSee('Alex is editing this page')
            ->assertFormFieldIsDisabled('title')
            ->fillForm(['title' => 'Changed by Sam'])
            ->call('save')
            ->assertNotified('Alex is editing this page');

        $this->assertSame('About us', $page->fresh()->title);
    }

    public function test_an_editor_that_lost_its_lock_cant_save_over_the_new_one(): void
    {
        $page = $this->textPage();

        $this->actingAs($this->alex());
        $alexEditor = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        // Alex's laptop sleeps, the lock lapses, and Sam opens the page.
        $this->travel(EditLocks::EXPIRES + 1)->seconds();
        $this->actingAs($this->sam());
        $this->locks();
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->assertSet('editLockHolder', null);

        $this->actingAs($this->alex());
        $this->locks();
        $alexEditor->fillForm(['title' => 'Changed by Alex'])->call('save')->assertNotified('Sam is editing this page');
        $this->assertSame('About us', $page->fresh()->title);
        $alexEditor->assertSet('editLockHolder.name', 'Sam');
    }

    public function test_closing_the_editor_frees_the_lock_through_its_signed_link(): void
    {
        $page = $this->textPage();

        $this->actingAs($this->alex());
        $url = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])->instance()->editLockReleaseUrl();

        $this->post($url.'x')->assertForbidden();
        $this->assertNotNull($this->locks()->holder($page));

        $this->post($url)->assertNoContent();
        $this->assertNull($this->locks()->holder($page));
    }
}
