<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\Design\Typography;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Filament\Forms\DeviceValue;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;

/**
 * Style › Heading text, Body text and Links (Design\Typography).
 */
class DesignTypographyTest extends PageTestCase
{
    /** A font folder for the tests, where Branding looks for an app's own fonts. */
    private string $fontFolder;

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

        $this->fontFolder = public_path(Branding::APP_FONTS_DIRECTORY.'/village-serif');
        File::ensureDirectoryExists($this->fontFolder);
        File::put($this->fontFolder.'/font.css', '/* test font */');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fontFolder);

        parent::tearDown();
    }

    public function test_headings_body_text_and_links_become_classes_and_properties(): void
    {
        $output = BlockDesign::resolve(['typography' => [
            'heading' => [
                'font' => 'village-serif',
                'weight' => '700',
                'style' => ['italic', 'upper'],
                'size' => ['desktop' => '48px', 'phone' => '2rem'],
                'line_height' => ['desktop' => '1.1'],
                'letter_spacing' => ['desktop' => '-0.5px'],
                'align' => 'center',
                'colour' => '#0a3d2e',
                'shadow' => 'soft',
            ],
            'body' => ['size' => ['tablet' => '18px'], 'colour' => '#333333'],
            'links' => ['colour' => '#1d41d5', 'hover_colour' => '#0a3d2e', 'weight' => 600, 'underline' => 'hover'],
        ]]);

        $classes = explode(' ', $output->classes('section'));
        $style = $output->style('section');

        foreach (['sb-d-hf', 'sb-d-hw', 'sb-d-h-italic', 'sb-d-h-upper', 'sb-d-hs', 'sb-d-hs-p', 'sb-d-hlh', 'sb-d-hls', 'sb-d-h-align-center', 'sb-d-hc', 'sb-d-h-shadow-soft', 'sb-d-ts-t', 'sb-d-tc', 'sb-d-lc', 'sb-d-lch', 'sb-d-lw', 'sb-d-l-underline-hover'] as $class) {
            $this->assertContains($class, $classes);
        }

        $this->assertNotContains('sb-d-ts', $classes, 'Only the screen sizes that are set.');

        foreach (["--sb-hf: 'Village Serif', ui-sans-serif, system-ui, sans-serif;", '--sb-hw: 700;', '--sb-hs: 48px;', '--sb-hs-p: 2rem;', '--sb-hlh: 1.1;', '--sb-hls: -0.5px;', '--sb-hc: #0a3d2e;', '--sb-ts-t: 18px;', '--sb-lw: 600;'] as $property) {
            $this->assertStringContainsString($property, $style);
        }

        $this->assertSame(['village-serif'], $output->fonts());
        $this->assertSame('', $output->classes('box').$output->classes('container'), 'Everything goes on the section.');
    }

    public function test_values_that_arent_allowed_are_left_out(): void
    {
        $output = BlockDesign::resolve(['typography' => [
            'heading' => [
                'font' => 'not-a-font',
                'weight' => '450',
                'style' => ['blink', 'italic'],
                'size' => ['desktop' => '3em'],
                'line_height' => ['desktop' => '1.5; color: red'],
                'letter_spacing' => ['desktop' => '-99px'],
                'align' => 'diagonal',
                'colour' => 'red',
                'shadow' => 'huge',
            ],
            'links' => ['underline' => 'sometimes', 'colour' => '#12345'],
        ]]);

        $this->assertSame('sb-d-h-italic', $output->classes('section'));
        $this->assertSame('', $output->style('section'));
        $this->assertSame([], $output->fonts());
    }

    public function test_typography_and_its_font_reach_the_page(): void
    {
        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Plain</p>']),
            self::block('call_to_action', ['heading' => 'Book the hall', 'design' => ['typography' => [
                'heading' => ['font' => 'village-serif', 'size' => ['desktop' => '56px', 'phone' => '32px'], 'colour' => '#0a3d2e'],
                'body' => ['style' => ['upper']],
            ]]]),
        ]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/<section[^>]*class="[^"]*sb-d-hf[^"]*sb-d-hs sb-d-hs-p[^"]*sb-d-hc[^"]*sb-d-t-upper[^"]*"[^>]*style="[^"]*--sb-hs: 56px; --sb-hs-p: 32px;/s', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="'.app(Branding::class)->fontStylesheetUrl('village-serif').'">', $html);
        $this->assertSame(1, substr_count($html, 'fonts/site-builder/village-serif/font.css'), 'The font is loaded once.');
    }

    public function test_a_page_without_a_chosen_font_loads_none(): void
    {
        $this->page(['blocks' => [self::block('text', ['content' => '<p>Plain</p>', 'design' => ['typography' => ['body' => ['font' => 'not-a-font']]]])]]);

        $this->get('/about-us')->assertSuccessful()->assertDontSee('village-serif/font.css', false);
    }

    public function test_the_editor_offers_the_text_settings_and_saves_them(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('call_to_action', ['heading' => 'Book the hall'])]]);
        $uid = $page->blocks[0]['uid'];

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]))
            ->assertFormFieldExists('design.typography.heading.font', 'mountedActionSchema0')
            ->assertFormFieldExists('design.typography.heading.size', 'mountedActionSchema0', fn (DeviceValue $field): bool => $field->isResponsive() && $field->getUnits() === Typography::SIZE_UNITS)
            ->assertFormFieldExists('design.typography.body.line_height', 'mountedActionSchema0', fn (DeviceValue $field): bool => $field->getUnits() === ['', 'px', 'em'])
            ->assertFormFieldExists('design.typography.links.hover_colour', 'mountedActionSchema0')
            ->setActionData(['design.typography.heading.size' => ['desktop' => '999px']])
            ->callMountedAction()
            ->assertHasActionErrors(['design.typography.heading.size']);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editBlock')->arguments(['uid' => $uid]), [
                'design.typography.heading.font' => 'village-serif',
                'design.typography.heading.size' => ['desktop' => '40px', 'phone' => '28px'],
                'design.typography.links.underline' => 'never',
            ])
            ->assertHasNoActionErrors()
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => str_contains($params['html'], '--sb-hs-p: 28px;')
                && str_contains($params['html'], 'sb-d-l-underline-never')
                && in_array(app(Branding::class)->fontStylesheetUrl('village-serif'), $params['fonts'], true));
    }
}
