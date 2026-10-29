<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Closure;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\Core\DividerBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Help for someone new in a block's panel: tooltips on the harder settings and on item buttons, tab icons, a note on
 * the Advanced tab, and rarely needed settings under More options (still saved under the same keys).
 */
class BlockEditorHelpTest extends PageTestCase
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
     * The page's first block, open in its panel in a fresh editor.
     */
    private function openFirstBlock(Page $page): Testable
    {
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        return $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]));
    }

    private static function tooltip(string $text): Closure
    {
        return fn (Field $field): bool => str_starts_with((string) $field->getHintIconTooltip(), $text);
    }

    public function test_a_card_blocks_harder_settings_and_item_buttons_have_tooltips(): void
    {
        $page = $this->page(['blocks' => [self::block('features', ['items' => [['title' => 'Free parking']]])]]);
        $component = $this->openFirstBlock($page);
        $itemKey = array_key_first($component->get('mountedActions.0.data.items'));

        $component
            ->assertFormFieldExists("items.{$itemKey}.link_url", 'mountedActionSchema0', self::tooltip('A page on this site'))
            ->assertFormFieldExists('columns', 'mountedActionSchema0', self::tooltip('How many sit side by side'))
            ->assertFormFieldExists('card.background', 'mountedActionSchema0', self::tooltip('A background and border make each card a box'))
            ->assertFormFieldExists('card.icon_background', 'mountedActionSchema0', self::tooltip('A tile is a small square'))
            ->assertFormFieldExists('items', 'mountedActionSchema0', fn (Repeater $field): bool => $field->getCloneAction()->getTooltip() === 'Duplicate this feature'
                && $field->getDeleteAction()->getTooltip() === 'Delete this feature'
                && $field->getReorderAction()->getTooltip() === 'Drag to move this feature');
    }

    public function test_settings_under_more_options_are_still_saved_under_their_keys(): void
    {
        $page = $this->page(['blocks' => [self::block('text', [
            'content' => '<p>Book the main hall</p>',
            'buttons' => [['label' => 'Book now', 'url' => '/book']],
        ])]]);
        $component = $this->openFirstBlock($page);
        $buttonKey = array_key_first($component->get('mountedActions.0.data.buttons'));

        $component
            ->assertFormFieldExists("buttons.{$buttonKey}.style", 'mountedActionSchema0', self::tooltip('Automatic picks the button style'))
            ->assertFormFieldExists("buttons.{$buttonKey}.new_tab", 'mountedActionSchema0', self::tooltip('Best for links to other websites'))
            ->assertFormFieldExists('buttons', 'mountedActionSchema0', fn (Repeater $field): bool => $field->getDeleteAction()->getTooltip() === 'Delete this button')
            ->set("mountedActions.0.data.buttons.{$buttonKey}.own_style", true)
            ->set("mountedActions.0.data.buttons.{$buttonKey}.shadow", 'medium')
            ->set("mountedActions.0.data.buttons.{$buttonKey}.new_tab", true)
            ->callMountedAction()
            ->call('save')
            ->assertHasNoFormErrors();

        $button = array_values(Page::query()->find($page->id)->blockList()[0]['data']['buttons'])[0];
        $this->assertSame(['medium', true], [$button['shadow'], $button['new_tab']]);
    }

    public function test_an_images_border_and_shadow_sit_under_more_options_with_the_corners_explained(): void
    {
        $page = $this->page(['blocks' => [self::block('gallery', ['images' => []])]]);

        $this->openFirstBlock($page)
            ->assertFormFieldExists('image_corners', 'mountedActionSchema0', self::tooltip('How round the image'))
            ->assertFormFieldExists('image_border_style', 'mountedActionSchema0')
            ->assertFormFieldExists('image_shadow', 'mountedActionSchema0')
            ->assertFormFieldExists('images', 'mountedActionSchema0', fn (Repeater $field): bool => $field->getCloneAction()->getTooltip() === 'Duplicate this image');
    }

    public function test_tabs_have_icons_and_the_advanced_tab_says_most_blocks_dont_need_it(): void
    {
        $tabs = collect(TextBlock::make()->getDefaultChildComponents())->first(fn (mixed $component): bool => $component instanceof Tabs);
        $byLabel = collect($tabs->getDefaultChildComponents())->mapWithKeys(fn (Tab $tab): array => [$tab->getLabel() => $tab]);

        $this->assertSame(['Content', 'Design', 'Advanced'], $byLabel->keys()->all(), 'The three tabs.');
        $this->assertSame(Heroicon::OutlinedPencilSquare, $byLabel['Content']->getIcon());
        $this->assertSame(Heroicon::OutlinedCog6Tooth, $byLabel['Advanced']->getIcon());
        $this->assertInstanceOf(Text::class, $byLabel['Advanced']->getDefaultChildComponents()[0], 'The Advanced tab starts with a note.');

        // Every block has an Advanced tab now, for its display conditions at least.
        $dividerTabs = collect(DividerBlock::make()->getDefaultChildComponents())->first(fn (mixed $component): bool => $component instanceof Tabs);
        $this->assertContains('Advanced', collect($dividerTabs?->getDefaultChildComponents() ?? [])->map(fn (Tab $tab): string => (string) $tab->getLabel())->all());
    }
}
