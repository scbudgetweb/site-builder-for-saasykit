<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\Core\HeadingBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;

/**
 * The Heading block: a heading on its own at the level chosen (H1–H6), with its small heading, styled by Design ›
 * Heading text (each level too), Heading size and decoration.
 */
class HeadingBlockTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_it_draws_its_level_with_that_levels_size(): void
    {
        $this->page(['blocks' => [
            self::block('heading', ['heading' => 'Our rooms', 'eyebrow' => 'Sometown Village Hall']),
            self::block('heading', ['heading' => 'The main hall', 'level' => 'h4', 'align' => 'center']),
            self::block('heading', ['heading' => 'Kitchen', 'level' => 'h6', 'align' => 'right']),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('<h2 class="sb-h2 section-heading">Our rooms</h2>', $html, 'H2 unless another level is chosen.');
        $this->assertStringContainsString('Sometown Village Hall</p>', $html);
        $this->assertStringContainsString('<h4 class="sb-h4 section-heading text-center">The main hall</h4>', $html);
        $this->assertStringContainsString('<h6 class="sb-h6 section-heading text-right">Kitchen</h6>', $html);
    }

    public function test_it_has_less_space_around_it_than_a_block_with_content(): void
    {
        $this->page(['blocks' => [
            self::block('heading', ['heading' => 'Our rooms']),
            self::block('heading', ['heading' => 'The main hall', 'appearance' => ['spacing' => 'large']]),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]+class="[^"]*py-8 sm:py-10[^"]*"[^>]*>(?:(?!<section).)*Our rooms/s', $html, 'Small, unless chosen.');
        $this->assertSame('small', HeadingBlock::fieldDefaults()['appearance.spacing'], 'The panel shows it as the default.');
        $this->assertSame('medium', TextBlock::fieldDefaults()['appearance.spacing']);
    }

    public function test_only_a_heading_level_becomes_a_tag(): void
    {
        $this->page(['blocks' => [self::block('heading', ['heading' => '<b>Hall</b>', 'level' => 'script'])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('<h2 class="sb-h2 section-heading">&lt;b&gt;Hall&lt;/b&gt;</h2>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_its_panel_has_heading_design_but_not_body_text(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('heading', ['heading' => 'Our rooms', 'level' => 'h3'])]]);

        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertFormFieldExists('design.typography.h3.colour', 'mountedActionSchema0')
            ->assertFormFieldExists('appearance.heading_decoration', 'mountedActionSchema0')
            ->assertFormFieldExists('appearance.heading_size', 'mountedActionSchema0')
            ->assertFormFieldExists('design.eyebrow.decoration', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('appearance.heading_align', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('appearance.text_size', 'mountedActionSchema0');

        $editor->set('mountedActions.0.data.design.typography.h3.colour', '#0b1f44')
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->callAction('publish');

        $this->assertMatchesRegularExpression('/<section[^>]+class="[^"]*sb-d-hl3c[^"]*"[^>]*--sb-hl3c: #0b1f44;/', $this->get('/about-us')->getContent());
    }

    public function test_it_can_be_imported(): void
    {
        $result = app(PageImport::class)->import(['title' => 'Rooms', 'slug' => 'rooms', 'status' => 'published', 'blocks' => [
            ['type' => 'heading', 'data' => ['heading' => 'Our rooms', 'level' => 'h1']],
            ['type' => 'heading', 'data' => ['level' => 'h7']],
        ]]);

        $this->assertSame(['blocks.1.data.heading: The heading field is required.', 'blocks.1.data.level: The selected level is invalid.'], $result->errors);
    }
}
