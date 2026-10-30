<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;

/**
 * The Toggle block: a title that opens and closes its content, one FAQ item on its own, open or closed at first.
 */
class ToggleBlockTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_it_opens_and_closes_its_content(): void
    {
        $this->page(['blocks' => [
            self::block('toggle', ['title' => 'Can we bring our own food?', 'content' => '<p>Yes, the kitchen is yours.</p>']),
            self::block('toggle', ['title' => 'Is there parking?', 'content' => '<p>Ten spaces.</p>', 'open' => true, 'marker' => 'chevron']),
            self::block('toggle', ['title' => 'Can we stay late?', 'content' => '<p>Until midnight.</p>', 'marker' => 'none']),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<details class="sb-item sb-toggle[^"]*"\s*>\s*<summary[^>]*>\s*<span class="sb-item-title">Can we bring our own food\?<\/span>\s*<svg[^>]*group-open:rotate-45/', $html, 'Closed, with a plus, unless chosen.');
        $this->assertMatchesRegularExpression('/<details class="sb-item sb-toggle[^"]*"\s*open\s*>\s*<summary[^>]*>\s*<span class="sb-item-title">Is there parking\?<\/span>\s*<svg[^>]*group-open:rotate-180/', $html);
        $this->assertMatchesRegularExpression('/Can we stay late\?<\/span>\s*<\/summary>/', $html, 'No marker.');
        $this->assertStringContainsString('<div class="sb-item-text block-text sb-prose mt-3 opacity-85"><p>Yes, the kitchen is yours.</p></div>', $html);
    }

    public function test_toggles_that_touch_share_one_line(): void
    {
        $css = (string) file_get_contents(__DIR__.'/../resources/css/site-builder.css');

        $this->assertStringContainsString('[data-block="toggle"]:not([class*="py-"]) + [data-block="toggle"]:not([class*="py-"]) .sb-toggle {', $css);
    }

    public function test_its_title_and_content_are_safe(): void
    {
        $this->page(['blocks' => [self::block('toggle', ['title' => '<b>Hall</b>', 'content' => '<p>Hi</p><script>alert(1)</script>', 'marker' => 'sparkles'])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('&lt;b&gt;Hall&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('group-open:rotate-45', $html, 'An unknown marker is the plus.');
    }

    public function test_its_panel_styles_the_title_and_content(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('toggle', ['title' => 'Is there parking?', 'content' => '<p>Ten spaces.</p>'])]]);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertFormFieldExists('design.items.title.colour', 'mountedActionSchema0')
            ->assertFormFieldExists('design.items.text.size', 'mountedActionSchema0')
            ->assertFormFieldExists('appearance.text_size', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('design.items.box.columns', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('design.items.icon.fill', 'mountedActionSchema0');
    }

    public function test_it_needs_a_title_and_content(): void
    {
        $result = app(PageImport::class)->import(['title' => 'Questions', 'slug' => 'questions', 'blocks' => [['type' => 'toggle', 'data' => ['open' => 'yes']]]]);

        $this->assertSame([
            'blocks.0.data.title: The title field is required.',
            'blocks.0.data.content: The content field is required.',
        ], $result->errors);
    }
}
