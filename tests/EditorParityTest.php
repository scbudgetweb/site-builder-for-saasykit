<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\SettingsPanel;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\EditSavedBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\LibraryVisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\EditTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\TemplateVisualEditor;
use ScBudgetWeb\SiteBuilder\Models\Template;

/**
 * Sections, rows, columns and blocks have the same settings wherever they're edited: a page, a library item or a
 * Theme Builder template, in the visual builder or as a form. Every editor opens the same panels.
 */
class EditorParityTest extends PageTestCase
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

    /**
     * @return array<string, mixed>
     */
    private static function sectionData(): array
    {
        return ['rows' => [['layout' => '2', 'columns' => [
            ['blocks' => [self::block('text', ['content' => '<p>Village Hall</p>'])]],
            ['blocks' => []],
        ]]]];
    }

    /**
     * Each editor, with a record holding one Section: [visual builder class, form class, record key].
     *
     * @return array<string, array{0: class-string, 1: class-string, 2: int|string}>
     */
    private function editors(): array
    {
        $page = $this->page(['blocks' => [self::block('section', self::sectionData())]]);
        $item = app(BlockLibrary::class)->save('Opening times', 'section', self::sectionData());
        $template = Template::query()->create(['name' => 'Main footer', 'type' => 'footer', 'blocks' => [self::block('section', self::sectionData())]]);

        return [
            'page' => [VisualEditor::class, EditPage::class, $page->getRouteKey()],
            'library item' => [LibraryVisualEditor::class, EditSavedBlock::class, $item->getRouteKey()],
            'template' => [TemplateVisualEditor::class, EditTemplate::class, $template->getRouteKey()],
        ];
    }

    public function test_every_visual_builder_opens_the_same_section_row_column_and_block_panels(): void
    {
        foreach ($this->editors() as [$visual, , $record]) {
            // The first block listed is the Section (Dividers are its alone; no text settings).
            $open = function (string $action, string $kind) use ($visual, $record) {
                $component = Livewire::test($visual, ['record' => $record]);
                $uid = collect($component->instance()->canvasNodes())->filter(fn (array $node): bool => $node['kind'] === $kind)->keys()->first();

                return $component->mountAction(TestAction::make($action)->arguments(['uid' => $uid]));
            };

            $open('editBlock', 'block')
                ->assertFormFieldExists('design.dividers.top_shape', 'mountedActionSchema0')
                ->assertFormFieldDoesNotExist('design.typography.heading.font', 'mountedActionSchema0');

            $open('editRow', 'row')
                ->assertFormFieldExists('background', 'mountedActionSchema0')
                ->assertFormFieldExists('link_url', 'mountedActionSchema0')
                ->assertFormFieldExists('design.spacing.margin', 'mountedActionSchema0')
                ->assertFormFieldDoesNotExist('design.typography.body.size', 'mountedActionSchema0');

            $open('editColumn', 'column')
                ->assertFormFieldExists('design.spacing.padding', 'mountedActionSchema0')
                ->assertFormFieldDoesNotExist('design.spacing.margin', 'mountedActionSchema0');
        }
    }

    public function test_every_form_opens_the_same_row_and_column_panels(): void
    {
        foreach ($this->editors() as $name => [, $form, $record]) {
            $component = Livewire::test($form, ['record' => $record]);
            $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]));

            $component->assertFormFieldDoesNotExist('design.typography.heading.font', 'mountedActionSchema0');

            $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
            $columnKey = array_key_first($component->get("mountedActions.0.data.rows.{$rowKey}.columns"));

            $component->assertFormFieldExists('rows', 'mountedActionSchema0', fn (Repeater $field): bool => isset($field->getExtraItemActions()['rowSettings']), "{$name}: row ⚙");

            $component->mountAction(TestAction::make('rowSettings')->schemaComponent('rows', 'mountedActionSchema0')->arguments(['item' => $rowKey]))
                ->assertFormFieldExists('link_url', 'mountedActionSchema1')
                ->assertFormFieldExists('design.spacing.margin', 'mountedActionSchema1');
            $component->callMountedAction();

            $component->mountAction(TestAction::make('columnSettings')->schemaComponent("rows.{$rowKey}.columns", 'mountedActionSchema0')->arguments(['item' => $columnKey]))
                ->assertFormFieldExists('design.spacing.padding', 'mountedActionSchema1')
                ->assertFormFieldDoesNotExist('design.spacing.margin', 'mountedActionSchema1');
        }
    }

    public function test_each_level_is_marked_with_its_colour_in_every_editor(): void
    {
        $level = fn ($action): ?string => $action->getExtraModalWindowAttributes()['data-sb-level'] ?? null;

        // Cards in the forms.
        $this->assertSame('section', SectionBlock::make()->getExtraAttributes()['data-sb-level'] ?? null);
        $this->assertSame('block', TextBlock::make()->getExtraAttributes()['data-sb-level'] ?? null);

        foreach ($this->editors() as [$visual, $form, $record]) {
            // The visual builder's panels.
            $component = Livewire::test($visual, ['record' => $record]);
            $nodes = collect($component->instance()->canvasNodes());

            foreach (['editRow' => 'row', 'editColumn' => 'column'] as $action => $kind) {
                $component->mountAction(TestAction::make($action)->arguments(['uid' => $nodes->filter(fn (array $node): bool => $node['kind'] === $kind)->keys()->first()]));
                $this->assertSame($kind, $level($component->instance()->getMountedAction()));
                $component->call('unmountAction');
            }

            $component->mountAction(TestAction::make('editBlock')->arguments(['uid' => $nodes->keys()->first()]));
            $this->assertSame('section', $level($component->instance()->getMountedAction()));

            // The form's Section panel, its row and column cards, and their panels.
            $component = Livewire::test($form, ['record' => $record]);
            $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]));
            $this->assertSame('section', $level($component->instance()->getMountedAction()));

            $rowKey = array_key_first($component->get('mountedActions.0.data.rows'));
            $component
                ->assertFormFieldExists('rows', 'mountedActionSchema0', fn (Repeater $field): bool => ($field->getExtraAttributes()['data-sb-level'] ?? null) === 'row'
                    && $level($field->getExtraItemActions()['rowSettings']) === 'row')
                ->assertFormFieldExists("rows.{$rowKey}.columns", 'mountedActionSchema0', fn (Repeater $field): bool => ($field->getExtraAttributes()['data-sb-level'] ?? null) === 'column'
                    && $level($field->getExtraItemActions()['columnSettings']) === 'column');
        }

        $styles = view('site-builder::filament.design-fields-script')->render();
        foreach (SettingsPanel::LEVEL_COLOURS as $name => $colour) {
            $this->assertStringContainsString(".fi-modal-window[data-sb-level=\"{$name}\"]", $styles);
            $this->assertStringContainsString("border-top: 3px solid {$colour};", $styles);
        }
    }
}
