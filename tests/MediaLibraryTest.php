<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Forms\MediaPicker;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageMedia;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Media\MediaLibrary;
use ScBudgetWeb\SiteBuilder\Models\MediaFile;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\PageDraft;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Settings\Settings;

/**
 * Website › Media library: every upload to pages is recorded as it's stored and stays until someone deletes it there,
 * which is refused while current content uses it.
 */
class MediaLibraryTest extends PageTestCase
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
     * A file in the pages upload folder, recorded in the library.
     */
    private function libraryFile(string $name, string $contents = 'image'): MediaFile
    {
        Storage::disk('public')->put("site-builder/pages/{$name}", $contents);

        return app(MediaLibrary::class)->record("site-builder/pages/{$name}", $name);
    }

    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 120, 90));
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    /** A JPEG carrying an Exif block (as a phone's photo does), right after its start marker. */
    private function jpegWithExif(int $width, int $height): string
    {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x00";
        $exif = "Exif\x00\x00".$tiff;
        $jpeg = $this->jpeg($width, $height);

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    }

    public function test_an_image_uploaded_in_a_block_joins_the_library(): void
    {
        $page = $this->page(['blocks' => [self::block('image', ['alt' => 'The hall'])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component->callAction(
            TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]),
            ['image' => UploadedFile::fake()->image('main-hall.jpg', 800, 600), 'alt' => 'The hall'],
        )->call('save')->assertHasNoFormErrors();

        $path = (string) Page::query()->find($page->id)->blockList()[0]['data']['image'];
        $file = MediaFile::query()->where('path', $path)->sole();

        $this->assertSame('main-hall.jpg', $file->name);
        $this->assertSame(MediaFile::IMAGE, $file->kind);
        $this->assertSame([800, 600], [$file->width, $file->height]);
        $this->assertGreaterThan(0, $file->size);
        $this->assertSame(crc32('admin@example.com'), $file->uploaded_by);
    }

    public function test_a_library_file_stays_when_a_page_stops_using_it_but_an_unrecorded_one_goes(): void
    {
        config(['site-builder.pages.revisions' => 1]);
        $this->libraryFile('kept.jpg');
        Storage::disk('public')->put('site-builder/pages/old.jpg', 'image');

        $page = $this->page(['blocks' => [self::block('image', ['image' => 'site-builder/pages/kept.jpg']), self::block('image', ['image' => 'site-builder/pages/old.jpg'])]]);
        $page->update(['blocks' => []]);
        $page->forceDelete();

        Storage::disk('public')->assertExists('site-builder/pages/kept.jpg');
        Storage::disk('public')->assertMissing('site-builder/pages/old.jpg');
    }

    public function test_the_nightly_clean_up_keeps_library_files(): void
    {
        $this->libraryFile('kept.jpg');
        Storage::disk('public')->put('site-builder/pages/abandoned.jpg', 'image');
        $this->travel(2)->days();

        $this->artisan('site-builder:clean-uploads')->assertSuccessful();

        Storage::disk('public')->assertExists('site-builder/pages/kept.jpg');
        Storage::disk('public')->assertMissing('site-builder/pages/abandoned.jpg');
    }

    public function test_files_already_uploaded_are_added_once(): void
    {
        Storage::disk('public')->put('site-builder/pages/hall.jpg', $this->jpeg(40, 30));
        Storage::disk('public')->put('site-builder/pages/talk.mp3', 'sound');
        Storage::disk('public')->put('site-builder/branding/logo.png', 'logo');
        $this->travel(3)->days();

        $this->artisan('site-builder:media-sync')->expectsOutput('2 files added to the media library.')->assertSuccessful();
        $this->artisan('site-builder:media-sync')->expectsOutput('0 files added to the media library.')->assertSuccessful();

        $this->assertSame(
            ['site-builder/pages/hall.jpg' => 'image', 'site-builder/pages/talk.mp3' => 'audio'],
            MediaFile::query()->orderBy('path')->pluck('kind', 'path')->all(),
        );
        $this->assertSame(40, MediaFile::query()->where('path', 'site-builder/pages/hall.jpg')->value('width'));
        $this->assertSame(
            Storage::disk('public')->lastModified('site-builder/pages/hall.jpg'),
            MediaFile::query()->where('path', 'site-builder/pages/hall.jpg')->sole()->created_at->getTimestamp(),
            'Dated when it was uploaded.',
        );
    }

    public function test_a_file_used_anywhere_current_cant_be_deleted(): void
    {
        $library = app(MediaLibrary::class);
        $places = [
            'page' => fn (string $path) => $this->page(['slug' => 'p-'.md5($path), 'blocks' => [self::block('image', ['image' => $path])]]),
            'page in the bin' => fn (string $path) => $this->page(['slug' => 'b-'.md5($path), 'blocks' => [self::block('image', ['image' => $path])]])->delete(),
            'share image' => fn (string $path) => $this->page(['slug' => 's-'.md5($path), 'share_image' => $path]),
            'library block' => fn (string $path) => SavedBlock::query()->create(['name' => 'Hall photo', 'blocks' => [self::block('image', ['image' => $path])]]),
            'template' => fn (string $path) => Template::query()->create(['name' => 'Main footer', 'type' => 'footer', 'blocks' => [self::block('image', ['image' => $path])]]),
            'draft' => fn (string $path) => PageDraft::query()->create(['page_id' => $this->page(['slug' => 'd-'.md5($path)])->id, 'user_id' => 1, 'blocks' => [], 'history' => [[self::block('image', ['image' => $path])]]]),
        ];

        foreach ($places as $place => $use) {
            $file = $this->libraryFile(str($place)->slug().'.jpg');
            $use($file->path);
            app()->forgetScopedInstances();

            $this->assertTrue(app(MediaLibrary::class)->isUsed($file->path), $place);
            $this->assertFalse(app(MediaLibrary::class)->delete($file), $place);
            Storage::disk('public')->assertExists($file->path);
        }

        $this->assertTrue(app(PageService::class)->isUploadInUse('site-builder/pages/template.jpg'), 'Templates count for the old clean-up too.');

        $unused = $this->libraryFile('unused.jpg');
        $this->assertTrue($library->delete($unused));
        Storage::disk('public')->assertMissing('site-builder/pages/unused.jpg');
        $this->assertModelMissing($unused);
    }

    public function test_used_on_lists_each_place(): void
    {
        $file = $this->libraryFile('hall.jpg');
        $this->page(['title' => 'Our rooms', 'blocks' => [self::block('image', ['image' => $file->path])]]);
        Template::query()->create(['name' => 'Main footer', 'type' => 'footer', 'blocks' => [self::block('image', ['image' => $file->path])]]);
        Page::query()->where('title', 'Our rooms')->sole()->revisions()->create(['snapshot' => ['blocks' => [self::block('image', ['image' => $file->path])]]]);

        $labels = array_column(app(MediaLibrary::class)->usesOf($file->path), 'label');

        $this->assertSame('Page: Our rooms', $labels[0]);
        $this->assertSame('Template: Main footer', $labels[1]);
        // Saving the page kept a revision of it too.
        $this->assertSame('2 kept revisions (restoring them after deleting this would show nothing in its place)', $labels[2]);
        $this->assertNotNull(app(MediaLibrary::class)->usesOf($file->path)[0]['url']);
        $this->assertSame([], app(MediaLibrary::class)->usesOf('site-builder/pages/nowhere.jpg'));
    }

    public function test_page_editors_open_website_media_on_its_library_tab(): void
    {
        Gate::define('update settings', fn (): bool => false);
        $this->assertTrue(ManageMedia::canAccess());
        $this->assertSame('Website', ManageMedia::getNavigationGroup());
        $this->assertSame('Media', ManageMedia::getNavigationLabel());

        // Library only: the settings tabs are for those who can update settings.
        Livewire::test(ManageMedia::class, ['activeTab' => 'images'])
            ->assertSet('activeTab', 'library')
            ->assertSee('Library')
            ->assertDontSee('Largest image');

        $this->actingAs($this->user('someone@example.com'));
        $this->assertFalse(ManageMedia::canAccess());

        Gate::define('update pages', fn (User $user): bool => true);
        $this->assertTrue(ManageMedia::canAccess());
    }

    public function test_the_library_lists_this_sites_files_with_filters_and_search(): void
    {
        $hall = $this->libraryFile('hall.jpg');
        $talk = $this->libraryFile('welcome-talk.mp3', 'sound');
        $logo = $this->libraryFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $elsewhere = MediaFile::query()->create(['disk' => 'public', 'path' => 'other-tenant/pages/x.jpg', 'name' => 'x.jpg', 'kind' => 'image', 'size' => 1]);
        $this->page(['blocks' => [self::block('image', ['image' => $hall->path])]]);

        Livewire::test(ManageMedia::class)
            ->assertCanSeeTableRecords([$hall, $talk, $logo])
            ->assertCanNotSeeTableRecords([$elsewhere])
            ->filterTable('kind', 'audio')
            ->assertCanSeeTableRecords([$talk])
            ->assertCanNotSeeTableRecords([$hall, $logo])
            ->resetTableFilters()
            ->filterTable('in_use', true)
            ->assertCanSeeTableRecords([$hall])
            ->assertCanNotSeeTableRecords([$talk, $logo])
            ->filterTable('in_use', false)
            ->assertCanSeeTableRecords([$talk, $logo])
            ->assertCanNotSeeTableRecords([$hall])
            ->resetTableFilters()
            ->searchTable('welcome')
            ->assertCanSeeTableRecords([$talk])
            ->assertCanNotSeeTableRecords([$hall, $logo]);
    }

    public function test_it_shows_as_a_grid_or_a_list(): void
    {
        $this->libraryFile('hall.jpg');

        Livewire::test(ManageMedia::class)
            ->assertSet('libraryLayout', 'grid')
            ->assertActionVisible('showList')
            ->callAction('showList')
            ->assertSet('libraryLayout', 'list')
            ->assertTableColumnExists('size')
            ->assertActionVisible('showGrid');
    }

    public function test_details_save_the_name_and_alt_text(): void
    {
        $file = $this->libraryFile('IMG_0042.jpg');
        $this->page(['title' => 'Our rooms', 'blocks' => [self::block('image', ['image' => $file->path])]]);

        Livewire::test(ManageMedia::class)
            ->mountAction(TestAction::make('details')->table($file))
            ->assertActionDataSet(['name' => 'IMG_0042.jpg', 'alt' => null])
            ->setActionData(['name' => 'Main hall', 'alt' => 'The main hall set out for a party'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(['Main hall', 'The main hall set out for a party'], [$file->fresh()->name, $file->fresh()->alt]);
    }

    public function test_a_file_can_be_downloaded(): void
    {
        $file = $this->libraryFile('hall.jpg');
        $file->update(['name' => 'Main hall']);

        Livewire::test(ManageMedia::class)
            ->callAction(TestAction::make('download')->table($file))
            ->assertFileDownloaded('Main hall.jpg');
    }

    public function test_delete_is_off_for_a_file_in_use_and_bulk_delete_keeps_those(): void
    {
        $used = $this->libraryFile('used.jpg');
        $unused = $this->libraryFile('unused.jpg');
        $this->page(['blocks' => [self::block('image', ['image' => $used->path])]]);

        Livewire::test(ManageMedia::class)
            ->assertActionDisabled(TestAction::make('delete')->table($used))
            ->assertActionEnabled(TestAction::make('delete')->table($unused))
            ->callTableBulkAction('deleteUnused', [$used, $unused])
            ->assertNotified('1 file deleted');

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
        Storage::disk('public')->assertExists($used->path);
        Storage::disk('public')->assertMissing($unused->path);
    }

    public function test_several_files_can_be_uploaded_at_once_with_each_kinds_checks(): void
    {
        Livewire::test(ManageMedia::class)
            ->callAction('upload', ['files' => [
                UploadedFile::fake()->image('hall.jpg', 100, 80),
                UploadedFile::fake()->create('talk.mp3', 50, 'audio/mpeg'),
            ]])
            ->assertHasNoActionErrors()
            ->assertNotified('2 files added to the library');

        $this->assertSame(['hall.jpg' => 'image', 'talk.mp3' => 'audio'], MediaFile::query()->orderBy('name')->pluck('kind', 'name')->all());

        // A sound file may be 100 MB, but an image only 2 MB (Media settings' standard sizes).
        Livewire::test(ManageMedia::class)
            ->callAction('upload', ['files' => [UploadedFile::fake()->create('huge.jpg', 3000, 'image/jpeg')]])
            ->assertHasActionErrors(['files']);

        Livewire::test(ManageMedia::class)
            ->callAction('upload', ['files' => [UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>')]])
            ->assertHasActionErrors(['files']);

        $this->assertSame(2, MediaFile::query()->count());
    }

    public function test_photos_are_shrunk_and_lose_their_location_data_when_media_settings_say_so(): void
    {
        $library = app(MediaLibrary::class);
        $upload = fn (): UploadedFile => UploadedFile::fake()->createWithContent('phone.jpg', $this->jpegWithExif(3000, 2000));

        // Off (as standard): stored as uploaded.
        $asUploaded = (string) Storage::disk('public')->get((string) $library->store($upload()));
        $this->assertStringContainsString('Exif', $asUploaded);
        $this->assertSame(3000, getimagesizefromstring($asUploaded)[0]);

        app(Settings::class)->set(['media.image_max_width' => 1600, 'media.image_strip_location' => true]);
        app()->forgetScopedInstances();

        $path = (string) app(MediaLibrary::class)->store($upload());
        $processed = (string) Storage::disk('public')->get($path);

        $this->assertStringNotContainsString('Exif', $processed);
        $this->assertSame([1600, 1067], array_slice(getimagesizefromstring($processed), 0, 2));
        $this->assertSame([1600, 1067], [MediaFile::query()->where('path', $path)->value('width'), MediaFile::query()->where('path', $path)->value('height')]);
        $this->assertSame('phone.jpg', MediaFile::query()->where('path', $path)->value('name'));
    }

    public function test_gifs_and_files_that_cant_be_read_are_kept_as_uploaded(): void
    {
        app(Settings::class)->set(['media.image_max_width' => 1600, 'media.image_strip_location' => true]);
        app()->forgetScopedInstances();

        $gif = UploadedFile::fake()->image('moving.gif', 2000, 100);
        $path = (string) app(MediaLibrary::class)->store($gif);

        $this->assertSame(2000, getimagesizefromstring((string) Storage::disk('public')->get($path))[0]);

        $notReally = UploadedFile::fake()->createWithContent('broken.jpg', 'not an image');
        $this->assertNotNull(app(MediaLibrary::class)->store($notReally));
    }

    public function test_the_picker_lists_this_sites_files_of_its_kinds_newest_first(): void
    {
        $old = $this->libraryFile('old-hall.jpg');
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        $this->libraryFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->libraryFile('talk.mp3', 'sound');
        $this->libraryFile('new-hall.jpg');
        MediaFile::query()->create(['disk' => 'public', 'path' => 'other-tenant/pages/x.jpg', 'name' => 'x.jpg', 'kind' => 'image', 'size' => 1]);

        $images = MediaPicker::make('path')->kinds([MediaFile::IMAGE, MediaFile::SVG]);
        $names = fn (array $result): array => array_column($result['items'], 'name');

        $this->assertSame(['new-hall.jpg', 'logo.svg', 'old-hall.jpg'], $names($images->getMediaForJs()));
        $this->assertSame(['logo.svg'], $names($images->getMediaForJs(kind: 'svg')));
        $this->assertSame(['old-hall.jpg'], $names($images->getMediaForJs(search: 'old')));
        $this->assertSame(['new-hall.jpg', 'logo.svg', 'old-hall.jpg'], $names($images->getMediaForJs(kind: 'audio')), 'A kind it doesn\'t offer is ignored.');
        $this->assertSame(['talk.mp3'], $names(MediaPicker::make('path')->kinds([MediaFile::AUDIO])->getMediaForJs()));
        $this->assertSame(['images' => 'Images', 'svg' => 'SVG drawings'], array_combine(['images', 'svg'], array_values($images->getKindFilters())));
        $this->assertSame([], MediaPicker::make('path')->kinds([MediaFile::AUDIO])->getKindFilters());

        foreach (range(1, MediaPicker::PER_PAGE) as $number) {
            $this->libraryFile("photo-{$number}.jpg");
        }

        $first = $images->getMediaForJs();
        $this->assertCount(MediaPicker::PER_PAGE, $first['items']);
        $this->assertTrue($first['hasMore']);
        $this->assertFalse($images->getMediaForJs(page: 2)['hasMore']);
    }

    public function test_choosing_from_the_library_fills_blank_alt_text(): void
    {
        $file = $this->libraryFile('hall.jpg');
        $file->update(['alt' => 'The main hall set out for a party']);
        $page = $this->page(['blocks' => [self::block('image', []), self::block('image', ['alt' => 'Our own words'])]]);

        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->callAction(TestAction::make('chooseFromLibrary')->schemaComponent('image', 'mountedActionSchema0'), ['path' => $file->path])
            ->assertHasNoActionErrors();

        $this->assertSame([$file->path], array_values((array) $editor->get('mountedActions.0.data.image')));
        $this->assertSame('The main hall set out for a party', $editor->get('mountedActions.0.data.alt'));

        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[1]['uid']]))
            ->callAction(TestAction::make('chooseFromLibrary')->schemaComponent('image', 'mountedActionSchema0'), ['path' => $file->path]);

        $this->assertSame('Our own words', $editor->get('mountedActions.0.data.alt'), 'Alt text already written is kept.');
    }

    public function test_a_new_file_can_be_uploaded_in_the_picker_and_one_must_be_given(): void
    {
        $page = $this->page(['blocks' => [self::block('image', ['alt' => 'The hall'])]]);

        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->callAction(TestAction::make('chooseFromLibrary')->schemaComponent('image', 'mountedActionSchema0'), [])
            ->assertHasActionErrors(['path'])
            ->callAction(TestAction::make('chooseFromLibrary')->schemaComponent('image', 'mountedActionSchema0'), ['upload' => UploadedFile::fake()->image('garden.jpg', 300, 200)])
            ->assertHasNoActionErrors();

        $path = (string) array_values((array) $editor->get('mountedActions.0.data.image'))[0];
        $this->assertSame('garden.jpg', MediaFile::query()->where('path', $path)->value('name'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_sound_file_can_be_chosen_from_the_library(): void
    {
        $talk = $this->libraryFile('talk.mp3', 'sound');
        $hall = $this->libraryFile('hall.jpg');
        $page = $this->page(['blocks' => [self::block('audio', ['title' => 'Welcome talk'])]]);

        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->callAction(TestAction::make('chooseFromLibrary')->schemaComponent('audio', 'mountedActionSchema0'), ['path' => $hall->path])
            ->assertHasActionErrors(['path'])
            ->callAction(TestAction::make('chooseFromLibrary')->schemaComponent('audio', 'mountedActionSchema0'), ['path' => $talk->path])
            ->assertHasNoActionErrors();

        $this->assertSame([$talk->path], array_values((array) $editor->get('mountedActions.0.data.audio')));
    }
}
