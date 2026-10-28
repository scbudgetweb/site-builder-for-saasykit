<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Block images can be SVGs, which stay sharp at any size, as long as they're plain drawings (SafeSvg). A share image
 * can't: social sites don't show SVGs.
 */
class BlockSvgUploadTest extends PageTestCase
{
    private const PLAIN = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40"><title>Village Hall</title><rect width="120" height="40" fill="#0a3d2e"/></svg>';

    private const UNSAFE = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>';

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
     * Opens the page's first block in its panel and uploads $svg as its image.
     */
    private function uploadToImageBlock(Page $page, string $svg): Testable
    {
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        return $component->callAction(
            TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]),
            ['image' => UploadedFile::fake()->createWithContent('logo.svg', $svg), 'alt' => 'Village Hall'],
        );
    }

    public function test_a_plain_svg_can_be_a_block_image(): void
    {
        $page = $this->page(['blocks' => [self::block('image', ['alt' => 'Village Hall'])]]);

        $this->uploadToImageBlock($page, self::PLAIN)
            ->assertHasNoFormErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $path = (string) Page::query()->find($page->id)->blockList()[0]['data']['image'];
        $this->assertStringEndsWith('.svg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_an_svg_with_active_content_is_refused_as_a_block_image(): void
    {
        $page = $this->page(['blocks' => [self::block('image', ['alt' => 'Village Hall'])]]);

        $this->uploadToImageBlock($page, self::UNSAFE)->assertHasActionErrors(['image']);

        $this->assertNull(Page::query()->find($page->id)->blockList()[0]['data']['image'] ?? null);
        $this->assertSame([], Storage::disk('public')->allFiles('site-builder/pages'));
    }

    public function test_a_share_image_cant_be_an_svg(): void
    {
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['share_image' => UploadedFile::fake()->createWithContent('share.svg', self::PLAIN)])
            ->call('save')
            ->assertHasFormErrors(['share_image']);
    }
}
