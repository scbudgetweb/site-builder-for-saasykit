<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockPresets;
use ScBudgetWeb\SiteBuilder\Blocks\Core\FeaturesBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\StatsBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;

/**
 * Saved styles (BlockPresets): one group of settings (Items, Heading text, Buttons…) saved once, picked on any kind of
 * block, and followed by every block using it.
 */
class SavedStylesTest extends PageTestCase
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

        Gate::define('update settings', fn (User $user): bool => $user->getAttribute('email') === 'admin@example.com');
    }

    private function presets(): BlockPresets
    {
        return $this->app->make(BlockPresets::class);
    }

    public function test_a_style_keeps_only_its_groups_settings_and_can_be_updated_and_deleted(): void
    {
        $id = $this->presets()->createStyle('items', 'Stat grid with dividers', [
            'heading' => 'Our numbers',
            'design' => ['items' => ['box' => ['dividers' => true]], 'spacing' => ['margin' => ['desktop' => '0px']]],
        ]);

        $this->assertSame([$id => 'Stat grid with dividers'], $this->presets()->styleNames('items'));
        $this->assertSame(['design' => ['items' => ['box' => ['dividers' => true]]]], $this->presets()->styleValues('items', $id), 'No content, no other groups.');
        $this->assertSame([], $this->presets()->styleNames('heading'), 'Each group has its own.');

        $this->presets()->updateStyle('items', $id, ['design' => ['items' => ['box' => ['background' => '#0b1f44']]]]);
        $this->assertSame(['design' => ['items' => ['box' => ['background' => '#0b1f44']]]], $this->presets()->styleValues('items', $id));

        $this->presets()->deleteStyle('items', $id);
        $this->assertSame([], $this->presets()->styleNames('items'));
        $this->assertSame([], $this->presets()->styleNames('nonsense'));
    }

    public function test_the_blocks_own_settings_win_then_its_saved_style_then_its_preset(): void
    {
        $style = $this->presets()->createStyle('items', 'Navy cards', ['design' => ['items' => ['box' => ['background' => '#0b1f44', 'radius' => ['desktop' => '4px']]]]]);
        $this->presets()->create('features', 'House style', ['design' => ['items' => ['box' => ['background' => '#ffffff', 'padding' => ['desktop' => '32px']]]]], isDefault: true);

        $data = $this->presets()->apply(FeaturesBlock::class, [
            'style_presets' => ['items' => $style],
            'design' => ['items' => ['box' => ['radius' => ['desktop' => '20px']]]],
        ]);

        $this->assertSame('#0b1f44', $data['design']['items']['box']['background'], 'The style beats the preset.');
        $this->assertSame(['desktop' => '20px'], $data['design']['items']['box']['radius'], 'The block\'s own beats the style.');
        $this->assertSame(['desktop' => '32px'], $data['design']['items']['box']['padding'], 'The preset fills in the rest.');

        $other = $this->presets()->apply(StatsBlock::class, ['style_presets' => ['items' => $style]]);
        $this->assertSame('#0b1f44', $other['design']['items']['box']['background'], 'Any kind of block can use it.');

        $missing = $this->presets()->apply(StatsBlock::class, ['style_presets' => ['items' => 'zzzzzzzz', 'nonsense' => $style]]);
        $this->assertArrayNotHasKey('design', $missing, 'A deleted style or a made-up group does nothing.');
    }

    public function test_a_preset_can_choose_saved_styles(): void
    {
        $style = $this->presets()->createStyle('heading', 'Gold headings', ['design' => ['typography' => ['heading' => ['colour' => '#f59e0b']]]]);
        $this->presets()->create('stats', 'Gold', ['style_presets' => ['heading' => $style]], isDefault: true);

        $data = $this->presets()->apply(StatsBlock::class, []);

        $this->assertSame('#f59e0b', $data['design']['typography']['heading']['colour']);
    }

    public function test_changing_a_style_changes_the_page(): void
    {
        $style = $this->presets()->createStyle('items', 'Navy cards', ['design' => ['items' => ['box' => ['background' => '#0b1f44']]]]);
        $this->page(['blocks' => [self::block('stats', ['style_presets' => ['items' => $style], 'items' => [['source' => 'custom', 'value' => 5, 'label' => 'Rooms']]])]]);

        $this->assertStringContainsString('--sb-ibg: #0b1f44;', $this->get('/about-us')->getContent());

        $this->presets()->updateStyle('items', $style, ['design' => ['items' => ['box' => ['background' => '#fef3c7']]]]);
        $this->assertStringContainsString('--sb-ibg: #fef3c7;', $this->get('/about-us')->getContent());
    }

    public function test_saving_a_style_from_the_panel_moves_the_settings_into_it(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('stats', [
            'items' => [['source' => 'custom', 'value' => 5, 'label' => 'Rooms']],
            'design' => ['items' => ['box' => ['background' => '#0b1f44']]],
        ])]]);

        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertFormFieldExists('style_presets.items', 'mountedActionSchema0')
            ->assertFormFieldExists('style_presets.heading', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('style_presets.buttons', 'mountedActionSchema0')
            ->callAction(TestAction::make('saveStyle_items')->schemaComponent('style_presets.sb-saved-style-items', 'mountedActionSchema0'), ['name' => 'Navy numbers']);

        [$id] = array_keys($this->presets()->styleNames('items'));
        $values = $this->presets()->styleValues('items', $id);
        $this->assertSame('#0b1f44', $values['design']['items']['box']['background']);
        $this->assertSame(['design'], array_keys($values), 'Only the Items settings.');
        $this->assertArrayNotHasKey('padding', $values['design']['items']['box'], 'Blank settings aren\'t kept.');
        $editor->assertSet('mountedActions.0.data.style_presets.items', $id)
            ->assertSet('mountedActions.0.data.design.items.box.background', null);
    }
}
