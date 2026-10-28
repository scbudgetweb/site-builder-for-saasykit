<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Branding\BrandingField;
use ScBudgetWeb\SiteBuilder\Facades\SiteBuilder as SiteBuilderFacade;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;

/**
 * Branding for someone new: a ? beside the settings people hesitate over explains them, and an app's own settings
 * can have one too.
 */
class BrandingHelpTest extends TestCase
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

        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::define('update settings', fn (User $user): bool => $user->getAttribute('email') === 'admin@example.com');

        $user = new User;
        $user->forceFill(['id' => 1, 'name' => 'Test', 'email' => 'admin@example.com']);
        $this->actingAs($user);
    }

    private static function tooltip(string $text): Closure
    {
        return fn (Field $field): bool => str_starts_with((string) $field->getHintIconTooltip(), $text);
    }

    public function test_the_harder_settings_explain_themselves(): void
    {
        Livewire::test(ManageBranding::class)
            ->assertFormFieldExists('site_color', self::tooltip('Your main brand colour.'))
            ->assertFormFieldExists('site_secondary_color', self::tooltip('An optional second brand colour'))
            ->assertFormFieldExists('heading_scale', self::tooltip('Changes every heading on the website at once.'))
            ->assertFormFieldExists('style_card_corners', self::tooltip('How round the corners are on cards'))
            ->assertFormFieldExists('style_input_corners', self::tooltip('The boxes people type into'))
            ->assertFormFieldExists('style_shadows', self::tooltip('The soft shadow under cards'))
            ->assertFormFieldExists('button_1_bg_hover', self::tooltip('Hover: when someone points at it'))
            ->assertFormFieldExists('header_link_hover_color', self::tooltip('Hover: when someone points at it'))
            ->assertFormFieldExists('header_link_color', fn (Field $field): bool => $field->getHintIconTooltip() === null)
            ->assertFormFieldExists('hero_background_type', self::tooltip('Gradient: two or three colours'))
            ->assertFormFieldExists('custom_css', fn (Field $field): bool => $field->getLabel() === 'Your CSS'
                && str_starts_with((string) $field->getHintIconTooltip(), 'For safety, "<", @import'));
    }

    public function test_an_apps_own_setting_can_have_a_tooltip(): void
    {
        SiteBuilderFacade::brandingSection('signup', 'Sign-up box', [
            BrandingField::text('signup_title', 'Title')->tooltip('The heading at the top of the sign-up box.'),
            BrandingField::colour('signup_bg', 'Background colour', '--color-signup-bg'),
        ]);

        $this->assertSame('The heading at the top of the sign-up box.', BrandingField::text('signup_title', 'Title')->tooltip('The heading at the top of the sign-up box.')->getTooltip());

        Livewire::test(ManageBranding::class)
            ->assertFormFieldExists('app_signup_title', self::tooltip('The heading at the top of the sign-up box.'))
            ->assertFormFieldExists('app_signup_bg', fn (Field $field): bool => $field->getHintIconTooltip() === null);
    }
}
