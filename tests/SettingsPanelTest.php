<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\SettingsPanel;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

/**
 * Block panels: Content, Design and Advanced tabs of collapsible toggles in a fixed order, a mark on
 * toggles with changed settings, and a Reset that puts a toggle back to its defaults.
 */
class SettingsPanelTest extends PageTestCase
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

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
    }

    public function test_a_toggle_thats_on_as_standard_shows_and_saves_on_for_a_block_without_a_value(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        // Just added, or saved before these toggles existed: no value for "Count up" or "Email messages".
        $page = $this->page(['blocks' => [
            self::block('stats', ['items' => [['source' => 'custom', 'value' => 120, 'label' => 'Seats']]]),
            self::block('stats', ['items' => [['source' => 'custom', 'value' => 120, 'label' => 'Seats']], 'animate' => false]),
            self::block('contact_form', []),
        ]]);
        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()]);

        $editor->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertSet('mountedActions.0.data.animate', true)
            ->callMountedAction()
            ->assertHasNoFormErrors();
        $editor->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[1]['uid']]))
            ->assertSet('mountedActions.0.data.animate', false, 'A choice made stays.')
            ->unmountAction();
        $editor->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[2]['uid']]))
            ->assertSet('mountedActions.0.data.send_email', true)
            ->assertSet('mountedActions.0.data.require_consent', false, 'Off as standard stays off.')
            ->unmountAction();

        $this->assertTrue(app(VisualDrafts::class)->find($page, $this->user())->blocks[0]['data']['animate'], 'Saved on, as it showed.');

        // The form editor's block panel too.
        $form = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $form->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($form->get('data.blocks'))]))
            ->assertSet('mountedActions.0.data.animate', true);
    }

    public function test_toggles_merge_by_key_and_follow_their_order(): void
    {
        $ordered = SettingsPanel::ordered([
            PanelToggle::design('animation', 'Animation', ['a'], 110),
            PanelToggle::design('layout', 'Layout', ['b']),
            PanelToggle::design('text', 'Text', ['c'], 49),
            PanelToggle::design('first', 'First', ['d'], 1),
            PanelToggle::design('style', 'Image', ['e']),
            PanelToggle::design('text', 'Text', ['f'], 49),
        ]);

        $this->assertSame(['first', 'layout', 'style', 'text', 'animation'], array_map(fn (array $parts): string => $parts[0]->key, $ordered));
        $this->assertCount(2, $ordered[3], 'Two sources add to the Text toggle.');
    }

    public function test_a_changed_toggle_is_marked_and_reset_puts_its_settings_back(): void
    {
        $page = $this->page(['blocks' => [self::block('text', [
            'content' => '<p>Opening times</p>',
            'appearance' => ['background' => 'light', 'width' => 'wide'],
        ])]]);
        $uid = $page->blocks[0]['uid'];
        $isModified = fn ($component, string $toggle): bool => SettingsPanel::isModified(
            $component->instance()->getSchema('mountedActionSchema0')->getComponent($toggle, withHidden: true),
        );

        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]));

        $this->assertTrue($isModified($component, 'sb-toggle-content-background'), 'Background has changed settings, so it is marked.');
        $this->assertFalse($isModified($component, 'sb-toggle-design-spacing'), 'Spacing is as it was.');

        $component
            ->callAction(TestAction::make('reset')->schemaComponent('sb-toggle-content-background', schema: 'mountedActionSchema0'))
            ->assertSchemaStateSet(['appearance.background' => 'none', 'appearance.width' => 'wide'], 'mountedActionSchema0')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $saved = app(VisualDrafts::class)->open($page, $this->user())->blocks[0]['data']['appearance'];
        $this->assertSame(['none', 'wide'], [$saved['background'], $saved['width']], 'Only that toggle went back; Sizing kept its width.');
    }

    public function test_every_block_can_have_an_admin_label_that_names_it_in_the_builder(): void
    {
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Hi</p>'])]]);
        $uid = $page->blocks[0]['uid'];

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editBlock')->arguments(['uid' => $uid]), ['admin_label' => 'Welcome note'])
            ->assertHasNoActionErrors();

        $this->assertSame('Welcome note', app(VisualDrafts::class)->open($page, $this->user())->blocks[0]['data']['admin_label']);
    }

    public function test_row_and_column_settings_open_with_the_same_tabs(): void
    {
        $labels = fn (array $schema): array => array_map(fn ($tab): string => (string) $tab->getLabel(), $schema[0]->getDefaultChildComponents());

        $this->assertSame(['Content', 'Design', 'Advanced'], $labels(SectionBlock::rowSettingsPanel()), 'With the design options blocks have.');
        $this->assertSame(['Content', 'Design', 'Advanced'], $labels(SectionBlock::columnSettingsPanel()));
    }

    public function test_an_image_uploaded_in_a_panel_shows_in_the_live_preview_before_its_saved(): void
    {
        $page = $this->page(['blocks' => [self::block('image', ['image' => null, 'alt' => 'The hall'])]]);
        $uid = $page->blocks[0]['uid'];

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]))
            ->set('mountedActions.0.data.image', UploadedFile::fake()->image('hall.png', 40, 30))
            ->call('previewBlock')
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => ($params['isPreview'] ?? false) === true
                && str_contains($params['html'], 'preview-file'));

        $this->assertNull(PageBlock::imageUrl('site-builder-preview:hall.png'), 'The preview URLs are only for that render.');
    }
}
