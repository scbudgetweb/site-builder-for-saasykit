<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\Core\IconBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\Design\Items;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;

/**
 * The Icon block: one icon, styled by Design › Icon as Features' icons are (without the items' box settings), read
 * out when it has a label, and a link through Content › Link.
 */
class IconBlockTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_it_draws_the_icon_read_out_only_with_a_label(): void
    {
        $this->page(['blocks' => [
            self::block('icon', ['icon' => 'bx:regular/bx-accessibility', 'label' => 'Wheelchair access']),
            self::block('icon', ['icon' => 'bx:regular/bx-wifi', 'align' => 'right']),
            self::block('icon', ['icon' => 'bx:regular/not-an-icon']),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="sb-icon-block flex justify-center">\s*<span class="sb-item inline-flex">\s*<span class="sb-item-icon[^"]*"\s*role="img" aria-label="Wheelchair access"\s*><svg/', $html, 'Centred unless chosen.');
        $this->assertMatchesRegularExpression('/<div class="sb-icon-block flex justify-end">\s*<span class="sb-item inline-flex">\s*<span class="sb-item-icon[^"]*"\s*><svg[^>]*aria-hidden="true"/', $html, 'Decoration: hidden from screen readers.');
        $this->assertSame(2, substr_count($html, 'data-block="icon"'), 'A block with an unknown icon is hidden, not an empty space.');
    }

    public function test_design_icon_styles_it_and_link_makes_it_a_link(): void
    {
        $this->page(['blocks' => [self::block('icon', [
            'icon' => 'bx:regular/bx-wifi',
            'appearance' => ['link_url' => '/facilities'],
            'design' => ['items' => ['icon' => ['fill' => 'solid', 'shape' => 'circle', 'colour' => '#ffffff', 'background' => '#0b1f44', 'size' => ['desktop' => '40px']]]],
        ])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]+class="[^"]*sb-d-ic-solid[^"]*sb-d-ic-circle[^"]*"/', $html);
        foreach (['--sb-icc: #ffffff;', '--sb-icb: #0b1f44;', '--sb-ics: 40px;', 'href="/facilities"'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }

        $css = (string) file_get_contents(__DIR__.'/../resources/css/design/items.css');
        $this->assertStringContainsString(':where(.sb-icon-block) .sb-item-icon { --sb-icsz: 3rem; }', $css, 'Larger as standard, and a chosen size still wins.');
    }

    public function test_its_panel_has_the_icon_style_without_the_items_box(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('icon', ['icon' => 'bx:regular/bx-wifi'])]]);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertFormFieldExists('design.items.icon.fill', 'mountedActionSchema0')
            ->assertFormFieldExists('design.items.icon.colour_hover', 'mountedActionSchema0')
            ->assertFormFieldExists('appearance.link_url', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('design.items.box.columns', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('appearance.heading_size', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('appearance.text_size', 'mountedActionSchema0');
    }

    public function test_items_without_a_box_have_no_box_toggle(): void
    {
        $parts = ItemParts::make('Icon')->icon('Icon')->withoutBox();

        $this->assertSame(['sb-item', 'sb-item-icon'], $parts->classes());
        $this->assertSame(['items_icon'], array_map(fn ($toggle): string => $toggle->key, Items::togglesFor($parts)));
        $this->assertSame(['sb-item', 'sb-item-icon', 'sb-item-title'], ItemParts::make('Icon')->withoutBox()->icon('Icon')->text('title', 'Title')->classes(), 'Kept as parts are added.');
        $this->assertSame('Icon: Wheelchair access', IconBlock::make()->getLabel(['icon' => 'bx:regular/bx-wifi', 'label' => 'Wheelchair access']));
    }

    public function test_an_icon_must_be_from_the_library(): void
    {
        $result = app(PageImport::class)->import(['title' => 'Facilities', 'slug' => 'facilities', 'blocks' => [
            ['type' => 'icon', 'data' => ['icon' => 'heroicon-o-home']],
            ['type' => 'icon', 'data' => []],
        ]]);

        $this->assertSame(['blocks.0.data.icon: Choose an icon from the list.', 'blocks.1.data.icon: The icon field is required.'], $result->errors);
    }
}
