<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;

/**
 * Design colours warn, under the field, when text would be hard to read on the colour behind it (SiteColour::readableOn()).
 */
class ContrastWarningsTest extends PageTestCase
{
    private const WARNING = 'Hard to read on its background';

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function panel(string $type, array $data): Testable
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['slug' => $type, 'blocks' => [self::block($type, $data)]]);

        return Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]));
    }

    /**
     * Whether the colour field at $path warns, and shows it under the field.
     */
    private function assertWarns(Testable $panel, string $path, bool $warns = true, string $message = ''): void
    {
        $panel->assertFormFieldExists($path, 'mountedActionSchema0', function (SiteColour $field) use ($warns, $message): bool {
            $below = (string) $field->getChildSchema($field::BELOW_CONTENT_SCHEMA_KEY)?->toHtmlString();

            $this->assertSame($warns, str_starts_with((string) $field->contrastWarning(), self::WARNING), $message);
            $this->assertSame($warns, str_contains($below, self::WARNING), 'Shown under the field. '.$message);

            return true;
        });
    }

    public function test_an_item_text_colour_warns_on_its_item_background(): void
    {
        $panel = $this->panel('stats', ['items' => [['source' => 'custom', 'value' => 5, 'label' => 'Rooms']]])
            ->set('mountedActions.0.data.design.items.box.background', '#ffffff')
            ->set('mountedActions.0.data.design.items.value.colour', '#fde047');
        $this->assertWarns($panel, 'design.items.value.colour');

        $panel->set('mountedActions.0.data.design.items.value.colour', '#0b1f44');
        $this->assertWarns($panel, 'design.items.value.colour', false);

        $panel->set('mountedActions.0.data.design.items.box.background', '#000814');
        $this->assertWarns($panel, 'design.items.value.colour', message: 'The background changing counts too.');
    }

    public function test_heading_text_warns_on_the_blocks_own_colour(): void
    {
        $panel = $this->panel('text', ['content' => '<h2>Our hall</h2>', 'appearance' => ['background' => 'custom', 'background_color' => '#0b1f44']])
            ->set('mountedActions.0.data.design.typography.h2.colour', '#1e293b');

        $this->assertWarns($panel, 'design.typography.h2.colour');
        $this->assertWarns($panel, 'design.typography.body.colour', false, 'Blank: nothing to check.');
    }

    public function test_only_a_single_colour_behind_it_is_checked(): void
    {
        $panel = $this->panel('text', ['content' => '<h2>Our hall</h2>', 'appearance' => ['background' => 'dark']])
            ->set('mountedActions.0.data.design.typography.heading.colour', '#1e293b');

        $this->assertWarns($panel, 'design.typography.heading.colour', false);
    }

    public function test_the_small_heading_warns_on_its_pill_and_an_icon_on_its_solid_tile(): void
    {
        $eyebrow = $this->panel('text', ['content' => '<p>Hi</p>', 'eyebrow' => 'New', 'eyebrow_style' => 'pill'])
            ->set('mountedActions.0.data.design.eyebrow.pill_colour', '#ffffff')
            ->set('mountedActions.0.data.design.eyebrow.colour', '#fef9c3');
        $this->assertWarns($eyebrow, 'design.eyebrow.colour');

        $icon = $this->panel('features', ['items' => [['title' => 'Parking', 'icon' => 'bx:regular/bx-car']]])
            ->set('mountedActions.0.data.design.items.icon.fill', 'solid')
            ->set('mountedActions.0.data.design.items.icon.background', '#fde047')
            ->set('mountedActions.0.data.design.items.icon.colour', '#ffffff');
        $this->assertWarns($icon, 'design.items.icon.colour');

        $icon->set('mountedActions.0.data.design.items.icon.fill', 'outline');
        $this->assertWarns($icon, 'design.items.icon.colour', false, 'Only a solid tile is behind it.');
    }
}
