<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;
use ScBudgetWeb\SiteBuilder\Settings\Settings;

/**
 * Custom CSS lives in one place (Branding › Custom CSS), with names it can rely on across updates: the brand colours as
 * variables (Branding::colourVariables()) and a reference of them, the item part classes and the block types.
 */
class CustomCssReferenceTest extends PageTestCase
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

        Gate::define('update settings', fn (User $user): bool => $user->getAttribute('email') === 'admin@example.com');
    }

    private function saveColours(): void
    {
        app(Settings::class)->set([Branding::GLOBAL_COLOURS => [
            ['id' => 'sunrise1', 'name' => 'Sunrise orange', 'colour' => '#f97316'],
            ['id' => 'sunrise2', 'name' => 'Sunrise  Orange!', 'colour' => '#ea580c'],
            ['id' => 'stars001', 'name' => '★★', 'colour' => '#fde047'],
            ['id' => 'site0001', 'name' => 'Site', 'colour' => '#0b1f44'],
        ]]);
    }

    public function test_brand_colours_have_variables_named_after_them(): void
    {
        $this->saveColours();
        $branding = app(Branding::class);

        $this->assertSame([
            '--sb-colour-site' => ['name' => 'Site colour', 'value' => 'var(--color-primary-500)'],
            '--sb-colour-secondary' => ['name' => 'Site secondary colour', 'value' => 'var(--color-secondary-500)'],
            '--sb-colour-sunrise-orange' => ['name' => 'Sunrise orange', 'value' => '#f97316'],
            '--sb-colour-sunrise-orange-2' => ['name' => 'Sunrise  Orange!', 'value' => '#ea580c'],
            '--sb-colour-stars001' => ['name' => '★★', 'value' => '#fde047'],
            '--sb-colour-site-2' => ['name' => 'Site', 'value' => '#0b1f44'],
        ], $branding->colourVariables());

        $css = $branding->siteCssVariables();
        $this->assertStringContainsString('--sb-colour-sunrise-orange: #f97316', $css);
        $this->assertStringContainsString('--sb-gc-sunrise1: #f97316', $css, 'What blocks use stays as it was.');
        $this->assertStringNotContainsString('--sb-colour-site:', $css, 'The site colours\' are in the stylesheet, following the palette.');

        $stylesheet = (string) file_get_contents(__DIR__.'/../resources/css/site-builder.css');
        $this->assertStringContainsString('--sb-colour-site: var(--color-primary-500);', $stylesheet);
        $this->assertStringContainsString('--sb-colour-secondary: var(--color-secondary-500);', $stylesheet);
    }

    public function test_swatches_show_each_colour_as_the_website_has_it(): void
    {
        $this->saveColours();

        $swatches = ManageBranding::cssReference()['swatches'];

        $this->assertSame(app(Branding::class)->primaryShade(500), $swatches['--sb-colour-site'], 'Not set: the standard site colour.');
        $this->assertNull($swatches['--sb-colour-secondary'], 'Not set: the theme\'s own, shown as an empty swatch.');
        $this->assertSame('#ea580c', $swatches['--sb-colour-sunrise-orange-2']);
    }

    public function test_nothing_saved_still_outputs_no_css(): void
    {
        $this->assertSame('', app(Branding::class)->siteCssVariables());
    }

    public function test_the_custom_css_tab_lists_what_css_can_rely_on(): void
    {
        $this->saveColours();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());

        Livewire::test(ManageBranding::class)
            ->assertSee('What your CSS can use')
            ->assertSee('var(--sb-colour-sunrise-orange)')
            ->assertSee('Sunrise orange')
            ->assertSee('var(--sb-colour-site)')
            ->assertSee('.sb-item-value')
            ->assertSee('.sb-item-highlight')
            ->assertSee('[data-block="stats"]', false)
            ->assertDontSee('[data-block="banner"]', false);
    }
}
