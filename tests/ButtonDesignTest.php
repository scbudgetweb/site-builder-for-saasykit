<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;

/**
 * Buttons: their text and link in Content, their look for the whole block in Style › Buttons, and a button that looks
 * different with its own.
 */
class ButtonDesignTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    private const BLOCK_LOOK = ['style' => 'custom', 'bg_color' => '#0a3d2e', 'text_color' => '#ffffff', 'width' => 'wide'];

    public function test_buttons_take_the_blocks_look_unless_they_have_their_own(): void
    {
        $this->page(['blocks' => [self::block('call_to_action', [
            'heading' => 'Book the village hall',
            'button_design' => self::BLOCK_LOOK,
            'buttons' => [
                ['label' => 'Book now', 'url' => '/book'],
                ['label' => 'See prices', 'url' => '/prices', 'own_style' => true, 'style' => 'custom', 'bg_color' => '#ffffff', 'text_color' => '#0a3d2e'],
            ],
        ])]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/class="site-button min-w-56"\s+style="--site-button-bg: #0a3d2e; --site-button-text: #ffffff;[^"]*"\s*>\s*Book now/', $html);
        $this->assertMatchesRegularExpression('/class="site-button"\s+style="--site-button-bg: #ffffff; --site-button-text: #0a3d2e;[^"]*"\s*>\s*See prices/', $html);
    }

    public function test_buttons_saved_with_their_own_style_keep_it(): void
    {
        $legacy = ['label' => 'Book now', 'url' => '/book', 'style' => 'custom', 'bg_color' => '#123456', 'text_color' => '#ffffff'];

        $this->assertTrue(PageBlock::hasOwnLook($legacy));
        $this->assertSame($legacy, PageBlock::withLook($legacy, self::BLOCK_LOOK));
        $this->assertFalse(PageBlock::hasOwnLook(['label' => 'Plain', 'url' => '/', 'style' => 'auto', 'width' => 'auto']));
        $this->assertFalse(PageBlock::hasOwnLook([...$legacy, 'own_style' => false]), 'Turned off, the block\'s look wins.');

        $this->assertSame(
            ['label' => 'Plain', 'url' => '/', 'new_tab' => true, ...self::BLOCK_LOOK],
            PageBlock::withLook(['label' => 'Plain', 'url' => '/', 'new_tab' => true, 'style' => 'auto', 'shadow' => 'none'], [...self::BLOCK_LOOK, 'evil' => 'x']),
            'Only look keys come from the block.',
        );
    }

    public function test_the_style_tab_has_the_button_look_and_a_button_can_have_its_own(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Hi</p>', 'buttons' => [
                ['label' => 'Plain', 'url' => '/'],
                ['label' => 'Styled before', 'url' => '/', 'style' => 'custom', 'bg_color' => '#123456', 'text_color' => '#ffffff'],
            ]]),
            self::block('faq', ['items' => []]),
        ]]);
        $open = fn (int $index) => Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[$index]['uid']]));

        $component = $open(0);
        [$plain, $styled] = array_keys($component->get('mountedActions.0.data.buttons'));

        $component
            ->assertFormFieldExists('button_design.style', 'mountedActionSchema0')
            ->assertFormFieldExists("buttons.{$plain}.own_style", 'mountedActionSchema0', fn (Toggle $field): bool => $field->getState() === false)
            ->assertFormFieldExists("buttons.{$styled}.own_style", 'mountedActionSchema0', fn (Toggle $field): bool => $field->getState() === true)
            ->assertFormFieldVisible("buttons.{$styled}.style", 'mountedActionSchema0')
            ->assertFormFieldHidden("buttons.{$plain}.style", 'mountedActionSchema0')
            ->setActionData(['button_design.style' => 'custom', 'button_design.bg_color' => '#0a3d2e', 'button_design.text_color' => '#ffffff'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => substr_count($params['html'], '--site-button-bg: #0a3d2e;') === 1
                && substr_count($params['html'], '--site-button-bg: #123456;') === 1);

        $open(1)->assertFormFieldDoesNotExist('button_design.style', 'mountedActionSchema0');
    }
}
