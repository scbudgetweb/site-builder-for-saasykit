<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Branding\BrandingStyle;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;

class ManageBrandingPageTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
            Fixtures\DashboardPanelProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Gate::define('update settings', fn (User $user): bool => $user->getAttribute('email') === 'admin@example.com');
    }

    public function test_only_users_with_the_permission_can_open_it(): void
    {
        $this->actingAs($this->user('someone@example.com'));
        $this->assertFalse(ManageBranding::canAccess());

        $this->actingAs($this->user('admin@example.com'));
        $this->assertTrue(ManageBranding::canAccess());
        $this->assertSame('Website', ManageBranding::getNavigationGroup());
    }

    public function test_it_opens_on_the_standard_look(): void
    {
        $this->actingAs($this->user('admin@example.com'));

        Livewire::test(ManageBranding::class)
            ->assertSchemaStateSet([
                'text_size' => '16',
                'heading_scale' => 'standard',
                'style_button_corners' => 'rounded',
                'button_1_name' => 'Primary',
                'logo_header' => 'light',
                'logo_footer' => 'dark',
                'hero_background_type' => 'gradient',
            ]);
    }

    public function test_it_saves_settings_and_blank_fields_keep_the_standard_look(): void
    {
        $this->actingAs($this->user('admin@example.com'));

        Livewire::test(ManageBranding::class)
            ->fillForm([
                'site_color' => '#2e6a4c',
                'text_size' => '18',
                'header_bg_color' => '#ffffff',
                'panel_color_dashboard' => '#123456',
                'button_1_uppercase' => true,
                'custom_css' => '.promo { color: #123456; }',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Branding saved');

        $branding = $this->app->make(Branding::class);

        $this->assertSame('#2e6a4c', $branding->get(Branding::SITE_COLOR));
        // Lesson 2: the Select hands back numeric keys as integers, so read it as a string.
        $this->assertSame('18', $branding->string(Branding::TEXT_SIZE));
        $this->assertSame('#123456', $branding->get(Branding::panelColorKey('dashboard')));
        $this->assertTrue($branding->isOn(BrandingStyle::buttonKey(1, 'uppercase')));
        $this->assertFalse($branding->isOn(BrandingStyle::buttonKey(2, 'uppercase')));
        $this->assertNull($branding->get(Branding::FOOTER_BG_COLOR));
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->actingAs($this->user('admin@example.com'));

        Livewire::test(ManageBranding::class)
            ->fillForm([
                'site_color' => 'green',
                'text_size' => '99',
                'custom_css' => '@import url(https://evil.example/x.css);',
            ])
            ->call('save')
            ->assertHasFormErrors(['site_color', 'text_size', 'custom_css']);

        $this->assertNull($this->app->make(Branding::class)->get(Branding::SITE_COLOR));
    }

    public function test_a_pale_site_colour_warns_but_still_saves(): void
    {
        $this->actingAs($this->user('admin@example.com'));

        Livewire::test(ManageBranding::class)
            ->fillForm(['site_color' => '#ffff99'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Low contrast site colour');

        $this->assertSame('#ffff99', $this->app->make(Branding::class)->get(Branding::SITE_COLOR));
    }

    public function test_uploads_are_stored_and_a_replaced_file_is_deleted(): void
    {
        $this->actingAs($this->user('admin@example.com'));

        Livewire::test(ManageBranding::class)
            ->fillForm(['apple_touch_icon' => UploadedFile::fake()->image('icon.png', 180, 180)])
            ->call('save')
            ->assertHasNoFormErrors();

        $first = $this->app->make(Branding::class)->get(Branding::APPLE_TOUCH_ICON);
        Storage::disk('public')->assertExists($first);
        $this->assertStringStartsWith('site-builder/branding/', $first);

        // As in the browser: remove the old file, then upload the new one.
        Livewire::test(ManageBranding::class)
            ->set('data.apple_touch_icon', [])
            ->fillForm(['apple_touch_icon' => UploadedFile::fake()->image('new.png', 180, 180)])
            ->call('save')
            ->assertHasNoFormErrors();

        $second = $this->app->make(Branding::class)->get(Branding::APPLE_TOUCH_ICON);
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_there_is_a_colour_field_for_each_panel(): void
    {
        $this->actingAs($this->user('admin@example.com'));

        Livewire::test(ManageBranding::class)
            ->assertFormFieldExists('panel_color_admin')
            ->assertFormFieldExists('panel_color_dashboard');
    }

    private function user(string $email): User
    {
        $user = new User;
        $user->forceFill(['id' => crc32($email), 'name' => 'Test', 'email' => $email]);

        return $user;
    }
}
