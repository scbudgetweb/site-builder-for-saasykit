<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\StyleClipboard;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;

/**
 * Design › Heading text: All headings, then H1 to H6 one by one, reaching headings typed in rich text.
 */
class HeadingLevelsTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_each_level_has_its_own_classes_and_properties(): void
    {
        $output = BlockDesign::resolve(['typography' => [
            'heading' => ['colour' => '#111827'],
            'h2' => ['colour' => '#0b1f44', 'size' => ['desktop' => '40px', 'phone' => '28px'], 'style' => ['upper']],
            'h3' => ['colour' => '#c9a227', 'weight' => '700'],
            'h7' => ['colour' => '#ff0000'],
        ]]);

        $classes = explode(' ', $output->classes('section'));
        $style = $output->style('section');

        foreach (['sb-d-hc', 'sb-d-hl2c', 'sb-d-hl2s', 'sb-d-hl2s-p', 'sb-d-hl2-upper', 'sb-d-hl3c', 'sb-d-hl3w'] as $class) {
            $this->assertContains($class, $classes);
        }

        foreach (['--sb-hl2c: #0b1f44;', '--sb-hl2s: 40px;', '--sb-hl2s-p: 28px;', '--sb-hl3c: #c9a227;', '--sb-hl3w: 700;'] as $property) {
            $this->assertStringContainsString($property, $style);
        }

        $this->assertStringNotContainsString('#ff0000', $style, 'Only H1 to H6.');
    }

    public function test_a_levels_rule_beats_all_headings(): void
    {
        $css = (string) file_get_contents(__DIR__.'/../resources/css/design/headings.css');

        // Three classes and the element beat All headings' two classes and :is(…, .section-heading).
        $this->assertStringContainsString('.sb-d-hl2c.sb-d-hl2c.sb-d-hl2c h2 { color: var(--sb-hl2c); }', $css);
        $this->assertStringContainsString('.sb-d-hl6s-p.sb-d-hl6s-p.sb-d-hl6s-p h6 { font-size: var(--sb-hl6s-p); }', $css);
        $this->assertStringContainsString("@import './design/headings.css';", (string) file_get_contents(__DIR__.'/../resources/css/site-builder.css'));
    }

    public function test_the_panel_has_all_headings_and_each_level(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<h2>Our hall</h2><p>Village Hall, Sometown.</p>'])]]);

        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]));

        foreach (['heading', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $level) {
            $editor->assertFormFieldExists("design.typography.{$level}.colour", 'mountedActionSchema0');
        }

        $editor->set('mountedActions.0.data.design.typography.h2.colour', '#0b1f44')
            ->callMountedAction()
            ->callAction('publish');

        $this->assertSame('#0b1f44', $page->fresh()->blocks[0]['data']['design']['typography']['h2']['colour']);
        $this->assertMatchesRegularExpression('/<section[^>]+class="[^"]*sb-d-hl2c[^"]*"[^>]*--sb-hl2c: #0b1f44;/', $this->get('/about-us')->getContent());
    }

    public function test_heading_styles_copy_every_level(): void
    {
        foreach (['design.typography.heading', 'design.typography.h1', 'design.typography.h6'] as $path) {
            $this->assertContains($path, StyleClipboard::paths('heading'));
        }
    }
}
