<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Settings\Settings;

class PageUploadsTest extends PageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        foreach (['hall.jpg', 'kitchen.jpg', 'share.jpg', 'shared.jpg'] as $file) {
            Storage::disk('public')->put("site-builder/pages/{$file}", 'image');
        }
    }

    public function test_images_removed_on_save_are_deleted(): void
    {
        // Only the current version is kept, so an image removed on save has no older revision still using it.
        config(['site-builder.pages.revisions' => 1]);

        $page = $this->page([
            'blocks' => [self::block('image_text', ['image' => 'site-builder/pages/hall.jpg']), self::block('image_text', ['image' => 'site-builder/pages/kitchen.jpg'])],
            'share_image' => 'site-builder/pages/share.jpg',
        ]);

        $page->update(['blocks' => [self::block('image_text', ['image' => 'site-builder/pages/kitchen.jpg'])], 'share_image' => null]);

        Storage::disk('public')->assertMissing('site-builder/pages/hall.jpg');
        Storage::disk('public')->assertMissing('site-builder/pages/share.jpg');
        Storage::disk('public')->assertExists('site-builder/pages/kitchen.jpg');
    }

    public function test_an_image_another_page_uses_is_kept_even_when_that_page_is_in_the_bin(): void
    {
        $page = $this->page(['blocks' => [self::block('image_text', ['image' => 'site-builder/pages/shared.jpg'])]]);
        $copy = $this->page(['slug' => 'copy', 'blocks' => [self::block('image_text', ['image' => 'site-builder/pages/shared.jpg'])]]);
        $copy->delete();

        $page->update(['blocks' => []]);

        Storage::disk('public')->assertExists('site-builder/pages/shared.jpg');
    }

    public function test_deleting_for_good_removes_the_pages_images(): void
    {
        $page = $this->page(['blocks' => [self::block('image_text', ['image' => 'site-builder/pages/hall.jpg'])]]);

        $page->delete();
        // A page in the bin can be restored, so its images stay.
        Storage::disk('public')->assertExists('site-builder/pages/hall.jpg');

        $page->forceDelete();
        Storage::disk('public')->assertMissing('site-builder/pages/hall.jpg');
    }

    public function test_only_files_in_the_pages_folder_count_as_page_uploads(): void
    {
        $pages = app(PageService::class);

        $this->assertTrue($pages->isPageUpload('site-builder/pages/hall.jpg'));
        $this->assertFalse($pages->isPageUpload('site-builder/branding/logo.png'));
        $this->assertFalse($pages->isPageUpload('site-builder/pages/../branding/logo.png'));
        $this->assertSame(['site-builder/pages/hall.jpg'], $pages->uploadedPaths([['data' => ['image' => 'site-builder/pages/hall.jpg', 'logo' => 'site-builder/branding/logo.png']]]));
    }

    public function test_the_clean_up_command_deletes_old_images_that_nothing_uses(): void
    {
        foreach (['orphan.jpg', 'in-library.jpg', 'in-revision.jpg'] as $file) {
            Storage::disk('public')->put("site-builder/pages/{$file}", 'image');
        }
        foreach (['logo.png', 'hero.jpg', 'app-image.png', 'unused-logo.png'] as $file) {
            Storage::disk('public')->put("site-builder/branding/{$file}", 'image');
        }
        app(Branding::class)->save([
            Branding::LOGO_LIGHT => 'site-builder/branding/logo.png',
            // Kept while saved, even when the banner currently shows a gradient.
            Branding::bannerKey('hero', 'image') => 'site-builder/branding/hero.jpg',
        ]);
        app(Settings::class)->set(['branding.app_signup_image' => 'site-builder/branding/app-image.png']);

        $page = $this->page(['blocks' => [self::block('image_text', ['image' => 'site-builder/pages/kitchen.jpg'])], 'share_image' => 'site-builder/pages/share.jpg']);
        app(BlockLibrary::class)->save('Image', 'image_text', ['image' => 'site-builder/pages/in-library.jpg']);
        $page->revisions()->create(['snapshot' => ['blocks' => [self::block('image_text', ['image' => 'site-builder/pages/in-revision.jpg'])]]]);

        // Uploaded just now: an editor may still be open, so nothing goes yet.
        $this->artisan('site-builder:clean-uploads')->expectsOutputToContain('0 unused files deleted.')->assertSuccessful();
        Storage::disk('public')->assertExists('site-builder/pages/orphan.jpg');

        Carbon::setTestNow(now()->addDays(2));

        $this->artisan('site-builder:clean-uploads', ['--dry-run' => true])
            ->expectsOutputToContain('Unused: site-builder/pages/orphan.jpg')
            ->expectsOutputToContain('Unused: site-builder/branding/unused-logo.png')
            ->assertSuccessful();
        Storage::disk('public')->assertExists('site-builder/pages/orphan.jpg');
        Storage::disk('public')->assertExists('site-builder/branding/unused-logo.png');

        $this->artisan('site-builder:clean-uploads')
            ->expectsOutputToContain('Deleted: site-builder/pages/orphan.jpg')
            ->expectsOutputToContain('Deleted: site-builder/branding/unused-logo.png')
            ->assertSuccessful();
        Storage::disk('public')->assertMissing('site-builder/branding/unused-logo.png');

        // hall.jpg and shared.jpg (from setUp) aren't used by anything either.
        foreach (['orphan.jpg', 'hall.jpg', 'shared.jpg'] as $file) {
            Storage::disk('public')->assertMissing("site-builder/pages/{$file}");
        }
        foreach (['site-builder/pages/kitchen.jpg', 'site-builder/pages/share.jpg', 'site-builder/pages/in-library.jpg', 'site-builder/pages/in-revision.jpg', 'site-builder/branding/logo.png', 'site-builder/branding/hero.jpg', 'site-builder/branding/app-image.png'] as $path) {
            Storage::disk('public')->assertExists($path);
        }

        Carbon::setTestNow();
    }
}
