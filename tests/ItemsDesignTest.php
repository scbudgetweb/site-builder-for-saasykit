<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\BlockPresets;
use ScBudgetWeb\SiteBuilder\Blocks\Core\FeaturesBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\StepsBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\StyleClipboard;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Settings\Settings;

/**
 * Design › Items (Design\Items): the look of a block's repeated items, from the parts it declares (ItemParts).
 */
class ItemsDesignTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: string}
     */
    private static function resolved(array $items): array
    {
        $output = BlockDesign::resolve(['items' => $items]);

        return [explode(' ', $output->classes('section')), $output->style('section')];
    }

    public function test_the_items_box_becomes_classes_and_properties(): void
    {
        [$classes, $style] = self::resolved(['box' => [
            'background' => '#0b1f44', 'background_to' => '#1d41d5', 'gradient' => 'diagonal', 'background_hover' => '#112233',
            'padding' => ['desktop' => '32px', 'phone' => '1rem'], 'gap' => ['desktop' => '0px'], 'radius' => ['desktop' => '12px'],
            'border_width' => 2, 'border_style' => 'dashed', 'border_colour' => '#e5e7eb', 'border_colour_hover' => '#1d41d5',
            'accent' => 'left', 'accent_width' => 6, 'accent_colour' => '#f59e0b',
            'shadow' => 'medium', 'shadow_hover' => 'strong', 'lift' => 'medium', 'dividers' => true, 'divider_colour' => '#cccccc',
        ]]);

        foreach (['sb-d-ibg', 'sb-d-ibg-light', 'sb-d-ibg-h', 'sb-d-ip', 'sb-d-ip-p', 'sb-d-igap', 'sb-d-ir', 'sb-d-ibw', 'sb-d-ibs', 'sb-d-ibc', 'sb-d-ibc-h', 'sb-d-iacc-left', 'sb-d-ish', 'sb-d-ish-h', 'sb-d-ilift', 'sb-d-idiv'] as $class) {
            $this->assertContains($class, $classes);
        }

        foreach (['--sb-ibg: linear-gradient(135deg, #0b1f44, #1d41d5);', '--sb-ip: 32px;', '--sb-ip-p: 1rem;', '--sb-igap: 0px;', '--sb-ir: 12px;', '--sb-ibw: 2px;', '--sb-ibs: dashed;', '--sb-iaw: 6px;', '--sb-iac: #f59e0b;', '--sb-ilift: -4px;', '--sb-idc: #cccccc;'] as $property) {
            $this->assertStringContainsString($property, $style);
        }

        $this->assertStringContainsString('--sb-ish: 0 4px 12px', $style);
        $this->assertStringContainsString('--sb-ish-h: 0 10px 25px', $style);
    }

    public function test_light_items_get_dark_text_and_a_shadow_can_be_none_or_custom(): void
    {
        [$classes, $style] = self::resolved(['box' => ['background' => '#fef3c7', 'shadow' => 'none']]);
        $this->assertContains('sb-d-ibg-dark', $classes);
        $this->assertStringContainsString('--sb-ibg: #fef3c7;', $style);
        $this->assertStringContainsString('--sb-ish: none;', $style, 'None takes a card\'s standard shadow away.');

        [, $style] = self::resolved(['box' => ['shadow' => 'custom', 'shadow_x' => 2, 'shadow_y' => 4, 'shadow_blur' => 10, 'shadow_spread' => -1, 'shadow_color' => '#112233', 'shadow_opacity' => 50]]);
        $this->assertStringContainsString('--sb-ish: 2px 4px 10px -1px rgba(17, 34, 51, 0.5);', $style);

        [$classes] = self::resolved(['box' => ['shadow' => 'auto']]);
        $this->assertNotContains('sb-d-ish', $classes, 'Standard: the block\'s own look.');
    }

    public function test_the_icon_and_each_text_part(): void
    {
        [$classes, $style] = self::resolved([
            'icon' => ['fill' => 'outline', 'shape' => 'circle', 'size' => ['desktop' => '40px', 'tablet' => '32px'], 'colour' => '#1d41d5', 'colour_hover' => '#0a3d2e', 'background' => '#f59e0b'],
            'value' => ['weight' => '800', 'size' => ['desktop' => '64px'], 'colour' => '#f59e0b', 'colour_hover' => '#ffffff'],
            'label' => ['style' => ['upper'], 'letter_spacing' => ['desktop' => '2px'], 'align' => 'left'],
        ]);

        foreach (['sb-d-ic-outline', 'sb-d-ic-circle', 'sb-d-ics', 'sb-d-ics-t', 'sb-d-icc', 'sb-d-icc-h', 'sb-d-icb', 'sb-d-ivaw', 'sb-d-ivas', 'sb-d-ivac', 'sb-d-ivac-h', 'sb-d-ilb-upper', 'sb-d-ilbls', 'sb-d-ilb-align-left'] as $class) {
            $this->assertContains($class, $classes);
        }

        foreach (['--sb-ics: 40px;', '--sb-ics-t: 32px;', '--sb-icc: #1d41d5;', '--sb-icb: #f59e0b;', '--sb-ivaw: 800;', '--sb-ivas: 64px;', '--sb-ivac: #f59e0b;', '--sb-ilbls: 2px;'] as $property) {
            $this->assertStringContainsString($property, $style);
        }
    }

    public function test_hand_edited_values_never_reach_the_page(): void
    {
        [$classes, $style] = self::resolved([
            'box' => ['background' => 'red;} body {display:none', 'accent' => 'diagonal', 'border_width' => 999, 'lift' => 'huge', 'shadow' => 'glow', 'gradient' => 'spiral', 'padding' => ['desktop' => '10vw']],
            'icon' => ['fill' => 'sparkly', 'size' => ['desktop' => '2px']],
            'value' => ['colour' => 'url(evil)'],
            'nonsense' => ['colour' => '#ff0000'],
        ]);

        $this->assertSame([''], $classes);
        $this->assertSame('', $style);
    }

    public function test_items_render_with_their_part_classes(): void
    {
        $this->page(['blocks' => [self::block('stats', [
            'items' => [['source' => 'custom', 'value' => 120, 'label' => 'Halls', 'icon' => 'bx:regular/bx-home']],
            'design' => ['items' => ['box' => ['background' => '#0b1f44'], 'value' => ['colour' => '#f59e0b']]],
        ])]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]+class="[^"]*sb-d-ibg sb-d-ibg-light[^"]*sb-d-ivac/', $html);
        $this->assertStringContainsString('--sb-ivac: #f59e0b;', $html);

        foreach (['sb-items', 'sb-item', 'sb-item-icon', 'sb-item-value', 'sb-item-label'] as $class) {
            $this->assertMatchesRegularExpression('/class="[^"]*\b'.$class.'\b/', $html, $class);
        }
    }

    public function test_the_panel_saves_items_settings_under_design_items(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('stats', ['items' => [['source' => 'custom', 'value' => 5, 'label' => 'Rooms']]])]]);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertFormFieldExists('design.items.box.background', 'mountedActionSchema0')
            ->assertFormFieldExists('design.items.icon.fill', 'mountedActionSchema0')
            ->assertFormFieldExists('design.items.value.colour', 'mountedActionSchema0')
            ->assertFormFieldExists('design.items.label.size', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('design.items.title.colour', 'mountedActionSchema0')
            ->set('mountedActions.0.data.design.items.value.colour', '#f59e0b')
            ->callMountedAction()
            ->callAction('publish');

        $this->assertSame('#f59e0b', $page->fresh()->blocks[0]['data']['design']['items']['value']['colour']);
    }

    public function test_blocks_saved_before_1_7_read_their_card_look_from_design_items(): void
    {
        $page = $this->page(['slug' => 'about-us']);
        DB::table('site_builder_pages')->where('id', $page->id)->update(['blocks_version' => 2, 'blocks' => json_encode([
            self::block('features', ['items' => [['title' => 'Free parking']], 'card' => ['background' => true, 'border' => true, 'icon_position' => 'top', 'background_color' => '#0b1f44', 'icon_size' => 'xl']]),
            self::block('section', ['rows' => [['layout' => '1', 'columns' => [['blocks' => [
                self::block('image_cards', ['cards' => [['title' => 'Main hall']], 'card' => ['title_color' => '#333333']]),
            ]]]]]]),
        ])]);

        $blocks = Page::query()->find($page->id)->blocks;

        $this->assertSame(['background' => true, 'border' => true, 'icon_position' => 'top'], $blocks[0]['data']['card']);
        $this->assertSame(['box' => ['background' => '#0b1f44'], 'icon' => ['size' => ['desktop' => '40px']]], $blocks[0]['data']['design']['items']);
        $this->assertSame(['title' => ['colour' => '#333333']], $blocks[1]['data']['rows'][0]['columns'][0]['blocks'][0]['data']['design']['items'], 'Blocks in a Section\'s columns too.');

        $html = $this->get('/about-us')->assertSuccessful()->getContent();
        $this->assertStringContainsString('--sb-ibg: #0b1f44;', $html);
        $this->assertStringContainsString('data-text-scheme="light"', $html, 'Light text on the dark card, as before.');
    }

    public function test_a_card_preset_saved_before_1_7_moves_its_look_too(): void
    {
        app(Settings::class)->set(['presets.features' => ['default' => 'abcd1234', 'items' => [
            'abcd1234' => ['name' => 'Navy cards', 'values' => ['card' => ['background_color' => '#0b1f44', 'icon_position' => 'left']]],
        ]]]);

        $data = app(BlockPresets::class)->apply(FeaturesBlock::class, ['items' => [['title' => 'Free parking']]]);

        $this->assertSame('#0b1f44', $data['design']['items']['box']['background']);
        $this->assertSame('left', $data['card']['icon_position']);
        $this->assertArrayNotHasKey('background_color', $data['card']);
    }

    public function test_items_styles_copy_and_paste_as_a_group(): void
    {
        $this->assertSame(['design.items', 'style_presets.items'], StyleClipboard::paths('items'), 'With its saved style.');
        $this->assertContains('design.items', StyleClipboard::paths('all'));

        $copied = StyleClipboard::extract(['heading' => 'Why us', 'design' => ['items' => ['box' => ['background' => '#0b1f44']]]], StyleClipboard::paths('items'));
        $this->assertSame(['design' => ['items' => ['box' => ['background' => '#0b1f44']]]], $copied);
    }

    public function test_steps_connect_and_highlight(): void
    {
        $this->assertSame(['sb-steps-line', '--sb-steps-ls: dashed; --sb-steps-lc: #1d41d5;'], StepsBlock::connector(['connector' => 'dashed', 'connector_colour' => '#1d41d5']));
        $this->assertSame(['', ''], StepsBlock::connector(['connector' => 'zigzag;}']));
        $this->assertSame(['', ''], StepsBlock::connector([]));

        $this->page(['blocks' => [self::block('steps', [
            'connector' => 'solid',
            'items' => [['title' => 'Choose a room'], ['title' => 'Pick a date', 'highlight' => true], ['title' => 'Book']],
        ])]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();
        $this->assertMatchesRegularExpression('/<ol class="[^"]*sb-items[^"]*sb-steps-lg-3 sb-steps-line"/', $html);
        $this->assertSame(1, substr_count($html, 'sb-item-highlight'));
        $this->assertStringContainsString('sb-item-icon sb-item-marker', $html);
    }

    public function test_a_part_must_be_one_the_package_styles(): void
    {
        $this->assertSame(['sb-items', 'sb-item', 'sb-item-icon', 'sb-item-value'], ItemParts::make('Numbers')->icon('Icons')->text('value', 'Number')->classes());

        $this->expectException(InvalidArgumentException::class);
        ItemParts::make('Cards')->text('subtitle', 'Subtitle');
    }
}
