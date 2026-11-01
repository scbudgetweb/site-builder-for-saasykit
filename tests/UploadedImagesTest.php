<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Media\MediaLibrary;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageService;

/**
 * Every image field can reuse an image already in the media library ("Choose from library"), newest first, instead of
 * uploading it again. Only library files in this site's upload folder, of the field's kinds, are offered or accepted.
 */
class UploadedImagesTest extends PageTestCase
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
        $disk = Storage::disk('public');

        foreach (['hall.jpg' => 100, 'kitchen.webp' => 300, 'logo.svg' => 200, 'notes.pdf' => 400] as $file => $age) {
            $disk->put("site-builder/pages/{$file}", 'image');
            touch($disk->path("site-builder/pages/{$file}"), time() - 1000 + $age);
        }
        $disk->put('site-builder/branding/logo.png', 'image');
        $disk->put('site-builder/pages/old/garden.jpg', 'image');
        app(MediaLibrary::class)->recordExisting();
    }

    public function test_uploaded_images_are_listed_newest_first(): void
    {
        $pages = app(PageService::class);

        $this->assertSame(['site-builder/pages/kitchen.webp', 'site-builder/pages/logo.svg', 'site-builder/pages/hall.jpg'], $pages->uploadedImages());
        $this->assertSame(['site-builder/pages/kitchen.webp', 'site-builder/pages/hall.jpg'], $pages->uploadedImages(withSvg: false), 'Where an SVG isn\'t allowed (a share image), none are offered.');
    }

    public function test_nothing_uploaded_lists_nothing(): void
    {
        Storage::fake('public');

        $this->assertSame([], app(PageService::class)->uploadedImages());
    }

    public function test_a_block_image_can_be_chosen_from_uploads(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('image', ['alt' => 'Village Hall'])]]);

        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->callAction(TestAction::make('chooseFromLibrary')->schemaComponent('image', 'mountedActionSchema0'), ['path' => 'site-builder/pages/kitchen.webp'])
            ->assertHasNoFormErrors();

        $this->assertSame(['site-builder/pages/kitchen.webp'], array_values((array) $editor->get('mountedActions.0.data.image')));

        $editor->callMountedAction()->assertHasNoFormErrors()->callAction('publish');

        $this->assertSame('site-builder/pages/kitchen.webp', Page::query()->find($page->id)->blockList()[0]['data']['image']);
        $this->assertStringContainsString('/storage/site-builder/pages/kitchen.webp', $this->get('/'.$page->slug)->getContent());
    }

    public function test_only_page_uploads_can_be_chosen(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('image', ['alt' => 'Village Hall'])]]);

        foreach (['site-builder/branding/logo.png', 'site-builder/pages/notes.pdf', 'site-builder/pages/../branding/logo.png'] as $path) {
            Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
                ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
                ->callAction(TestAction::make('chooseFromLibrary')->schemaComponent('image', 'mountedActionSchema0'), ['path' => $path])
                ->assertHasActionErrors(['path']);
        }
    }

    public function test_a_share_image_offers_no_svgs(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('chooseFromLibrary')->schemaComponent('share_image', 'form'), ['path' => 'site-builder/pages/logo.svg'])
            ->assertHasActionErrors(['path'])
            ->callAction(TestAction::make('chooseFromLibrary')->schemaComponent('share_image', 'form'), ['path' => 'site-builder/pages/hall.jpg'])
            ->assertHasNoActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('site-builder/pages/hall.jpg', Page::query()->find($page->id)->share_image);
    }
}
