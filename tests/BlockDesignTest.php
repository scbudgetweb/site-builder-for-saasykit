<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\Design\DesignOutput;
use ScBudgetWeb\SiteBuilder\Blocks\Design\DesignValue;
use ScBudgetWeb\SiteBuilder\Filament\Forms\BoxSides;
use ScBudgetWeb\SiteBuilder\Filament\Forms\DeviceValue;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;

/**
 * Blocks' design options (BlockDesign): the shared parts, and Layout › Spacing & size.
 */
class BlockDesignTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_lengths_are_checked_and_tidied(): void
    {
        $this->assertSame('24px', DesignValue::length('24px', ['px', 'rem'], 0, 100));
        $this->assertSame('1.5rem', DesignValue::length(' 1.50 rem ', ['px', 'rem'], 0, 100));
        $this->assertSame('-8px', DesignValue::length('-8px', ['px'], -10, 10));
        $this->assertSame('1.4', DesignValue::length('1.4', [''], 0, 5));

        foreach (['24em', '200px', '-1px', '24px; color: red', 'calc(1px)', '', null, ['24px']] as $bad) {
            $this->assertNull(DesignValue::length($bad, ['px', 'rem'], 0, 100), json_encode($bad));
        }
    }

    public function test_only_the_packages_own_classes_and_safe_values_are_output(): void
    {
        $output = (new DesignOutput)
            ->addClass('section', 'sb-d-pt')
            ->addClass('section', 'evil" onclick="x')
            ->addClass('nowhere', 'sb-d-pt')
            ->setProperty('section', '--sb-pt', '24px')
            ->setProperty('section', '--sb-x', 'red; background: url(https://evil.example/x.png)')
            ->setProperty('section', '--sb-y', '1px} body {display:none')
            ->setProperty('section', 'color', 'red');

        $this->assertSame('sb-d-pt', $output->classes('section'));
        $this->assertSame('--sb-pt: 24px;', $output->style('section'));
        $this->assertTrue((new DesignOutput)->isEmpty());
    }

    public function test_spacing_differs_by_screen_size_and_goes_on_the_right_elements(): void
    {
        $output = BlockDesign::resolve(['spacing' => [
            'padding' => ['desktop' => ['top' => '64px', 'bottom' => '64px'], 'phone' => ['top' => '24px']],
            'margin' => ['desktop' => ['top' => '-40px']],
            'max_width' => ['desktop' => '640px', 'tablet' => '90%'],
            'align' => 'right',
            'min_height' => ['desktop' => '100vh'],
            'vertical_align' => 'center',
        ]]);

        $this->assertSame('sb-d-pt sb-d-pt-p sb-d-pb', $output->classes('box'));
        $this->assertSame('--sb-pt: 64px; --sb-pt-p: 24px; --sb-pb: 64px;', $output->style('box'));
        $this->assertStringContainsString('sb-d-mt', $output->classes('section'));
        $this->assertStringContainsString('--sb-mt: -40px;', $output->style('section'));
        $this->assertSame('sb-d-maxw sb-d-maxw-t sb-d-align-right', $output->classes('container'));
        $this->assertStringContainsString('sb-d-minh sb-d-valign-center', $output->classes('section'));

        $ignored = BlockDesign::resolve(['spacing' => ['padding' => ['desktop' => ['top' => '9999px', 'middle' => '1px']], 'align' => 'diagonal']]);
        $this->assertTrue($ignored->isEmpty(), 'Values out of range or not on a list are left out.');
    }

    public function test_design_reaches_the_page_and_a_panel_gets_the_box_settings(): void
    {
        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Plain</p>']),
            self::block('text', ['content' => '<p>Spaced</p>', 'design' => ['spacing' => ['padding' => ['desktop' => ['top' => '80px']], 'margin' => ['desktop' => ['bottom' => '16px']]]]]),
            self::block('call_to_action', ['heading' => 'Panelled', 'appearance' => ['background' => 'dark', 'background_area' => 'panel'], 'design' => ['spacing' => ['padding' => ['desktop' => ['left' => '2rem']]]]]),
        ]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]*class="[^"]*sb-d-mb sb-d-pt [^"]*"\s*style="--sb-mb: 16px; --sb-pt: 80px;"[^>]*>.*Spaced/s', $html);
        $this->assertMatchesRegularExpression('/class="sb-panel [^"]*sb-d-pl"[^>]*style="[^"]*--sb-pl: 2rem;/', $html);
        $this->assertSame(3, substr_count($html, 'sb-d-'), 'Only the classes for values that are set; the plain block has none.');
    }

    public function test_the_fields_save_screen_sizes_and_refuse_bad_values(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Hi</p>'])]]);
        $uid = $page->blocks[0]['uid'];

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]))
            ->assertFormFieldExists('design.spacing.padding', 'mountedActionSchema0', fn (BoxSides $field): bool => $field->isResponsive())
            ->assertFormFieldExists('design.spacing.max_width', 'mountedActionSchema0', fn (DeviceValue $field): bool => $field->getUnits() === ['px', 'rem', '%'])
            ->setActionData(['design.spacing.padding' => ['desktop' => ['top' => '9999px']]])
            ->callMountedAction()
            ->assertHasActionErrors(['design.spacing.padding']);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editBlock')->arguments(['uid' => $uid]), [
                'design.spacing.padding' => ['desktop' => ['top' => '48px'], 'phone' => ['top' => '16px']],
                'design.spacing.min_height' => ['desktop' => '50vh'],
            ])
            ->assertHasNoActionErrors()
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => str_contains($params['html'], '--sb-pt-p: 16px;')
                && str_contains($params['html'], '--sb-minh: 50vh;'));
    }

    public function test_the_admin_panel_loads_the_design_fields_script(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());

        $this->get(PageResource::getUrl('index', panel: 'admin'))
            ->assertSee("Alpine.data('sbDeviceValue'", false)
            ->assertSee("Alpine.store('sbDevice'", false);
    }
}
