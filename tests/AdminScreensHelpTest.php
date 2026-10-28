<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageNavigation;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\CreatePage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\ListPages;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\CreateSavedBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\ListSavedBlocks;

/**
 * The admin screens explain themselves to someone new: tooltips on settings that need explaining, tooltips on icon
 * buttons, a line under each list's title, and empty lists that say what to do next.
 */
class AdminScreensHelpTest extends PageTestCase
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
     * @return \Closure(Field): bool
     */
    private static function tooltip(string $text): \Closure
    {
        return fn (Field $field): bool => str_starts_with((string) $field->getHintIconTooltip(), $text);
    }

    public function test_page_settings_explain_themselves(): void
    {
        Livewire::test(CreatePage::class)
            ->assertFormFieldExists('title', self::tooltip('The page\'s name'))
            ->assertFormFieldExists('slug', fn (Field $field): bool => $field->getLabel() === 'Web address' && str_starts_with((string) $field->getHintIconTooltip(), 'The end of the page\'s web address'))
            ->assertFormFieldExists('status', self::tooltip('Draft: hidden from visitors'))
            ->assertFormFieldExists('show_page_banner', self::tooltip('A strip across the top of the page'))
            ->assertFormFieldExists('seo_title', self::tooltip('The blue link people click in search results'))
            ->assertFormFieldExists('noindex', self::tooltip('For pages you only share by link'))
            ->assertSee('Search &amp; sharing', false);
    }

    public function test_empty_lists_say_what_to_do_and_lists_say_what_they_are(): void
    {
        Livewire::test(ListPages::class)
            ->assertSee('No pages yet')
            ->assertSee('each built from blocks');

        Livewire::test(ListSavedBlocks::class)
            ->assertSee('No library blocks yet')
            ->assertSee('Blocks you can reuse on many pages');
    }

    public function test_the_block_library_explains_linking(): void
    {
        Livewire::test(CreateSavedBlock::class)
            ->assertFormFieldExists('blocks', fn (Builder $field): bool => str_starts_with((string) $field->getHintIconTooltip(), 'The block to reuse.'));
    }

    public function test_the_menu_builder_explains_links_and_its_buttons(): void
    {
        Livewire::test(ManageNavigation::class)
            ->assertFormFieldExists('header', fn (Repeater $field): bool => $field->getMoveUpAction()->getTooltip() === 'Move this link up'
                && $field->getDeleteAction()->getTooltip() === 'Delete this link')
            ->assertFormFieldExists('footer.columns', fn (Repeater $field): bool => $field->getDeleteAction()->getTooltip() === 'Delete this column')
            ->assertFormFieldExists('footer.copyright', self::tooltip('The small print'));
    }
}
