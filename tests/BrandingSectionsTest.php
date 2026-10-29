<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Branding\BrandingField;
use ScBudgetWeb\SiteBuilder\Branding\BrandingStyle;
use ScBudgetWeb\SiteBuilder\Facades\SiteBuilder as SiteBuilderFacade;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;
use ScBudgetWeb\SiteBuilder\Settings\Settings;

/**
 * An app's own Branding section (SiteBuilder::brandingSection()), with an example sign-up box: its text, a toggle, a
 * choice, colours, a button style, an image and a banner.
 */
class BrandingSectionsTest extends TestCase
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

        SiteBuilderFacade::brandingSection('signup', 'Sign-up box', [
            BrandingField::text('signup_title', 'Title', maxLength: 80)->default('Stay in touch'),
            BrandingField::textarea('signup_text', 'Text under the title'),
            BrandingField::toggle('signup_show_privacy', 'Show the privacy note')->default(true),
            BrandingField::select('signup_layout', 'Layout', ['stacked' => 'Stacked', 'inline' => 'Side by side'])->default('stacked'),
            BrandingField::colour('signup_bg', 'Background colour', '--color-signup-bg'),
            BrandingField::colour('signup_text_color', 'Text colour', '--color-signup-text')->contrastWith('signup_bg'),
            BrandingField::buttonStyle('signup_button', 'Button style'),
            BrandingField::image('signup_image', 'Image'),
            BrandingField::banner('signup_banner', 'Sign-up banner')->helperText('Behind the sign-up box.'),
        ], 'The sign-up box at the foot of each page.');
    }

    public function test_keys_follow_the_branding_colour_rules_and_are_never_shared(): void
    {
        $this->assertThrows(fn () => BrandingField::text('Bad-Key', 'Bad'), InvalidArgumentException::class);
        $this->assertThrows(fn () => SiteBuilderFacade::brandingSection('bad key', 'Bad', []), InvalidArgumentException::class);
        $this->assertThrows(fn () => BrandingField::colour('x', 'X', 'not-a-variable'), InvalidArgumentException::class);
        // A text field that would save one of the banner's own settings.
        $this->assertThrows(fn () => SiteBuilderFacade::brandingSection('other', 'Other', [BrandingField::text('signup_banner_image', 'Clash')]), InvalidArgumentException::class);
        $this->assertThrows(fn () => SiteBuilderFacade::brandingColours('Clash', ['signup_bg' => ['Background', '--color-clash']]), InvalidArgumentException::class);

        // Registering the same section again replaces it.
        SiteBuilderFacade::brandingSection('signup', 'Sign-up box', [BrandingField::text('signup_title', 'Heading')]);
        $this->assertSame(['signup_title'], array_values(array_filter(array_keys(app(Branding::class)->appFields()), fn (string $key): bool => ! str_starts_with($key, 'newsletter'))), 'The package\'s own Mailing list section aside.');
    }

    public function test_the_section_is_a_tab_after_the_packages_and_opens_on_its_defaults(): void
    {
        Livewire::test(ManageBranding::class)
            ->assertSee(['Sign-up box', 'The sign-up box at the foot of each page.', 'Sign-up banner', 'Behind the sign-up box.'])
            ->assertFormFieldExists('app_signup_title', fn ($field): bool => $field->getPlaceholder() === 'Stay in touch')
            ->assertFormFieldExists('app_signup_image')
            ->assertFormFieldExists('app_signup_button', fn ($field): bool => array_map('strval', array_keys($field->getOptions())) === ['auto', '1', '2', '3', '4'])
            ->assertFormFieldExists('app_signup_banner_background_type')
            ->assertSchemaStateSet([
                'app_signup_show_privacy' => true,
                'app_signup_layout' => 'stacked',
                'app_signup_button' => 'auto',
                'app_signup_banner_background_type' => 'gradient',
            ]);

        $tabs = collect(Livewire::test(ManageBranding::class)->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($component): bool => $component instanceof Tabs);
        $labels = array_map(fn (Tab $tab): string => (string) $tab->getLabel(), $tabs->getChildSchema()->getComponents());

        $this->assertSame(['Custom CSS', 'Mailing list', 'Sign-up box'], array_slice($labels, -3), 'After the package\'s tabs, including its Mailing list section.');
    }

    public function test_it_saves_and_the_values_reach_the_site(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'app_signup_title' => '  Get our news  ',
                'app_signup_show_privacy' => false,
                'app_signup_layout' => 'inline',
                'app_signup_bg' => '#123456',
                'app_signup_text_color' => '#ffffff',
                'app_signup_button' => '3',
                'app_signup_image' => UploadedFile::fake()->image('signup.png', 400, 300),
                'app_signup_banner_background_type' => 'color',
                'app_signup_banner_background_color' => '#0a3d2e',
                'app_signup_banner_heading_color' => '#fff5cc',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Branding saved');

        $branding = app(Branding::class);

        $this->assertSame('Get our news', $branding->appValue('signup_title'));
        $this->assertNull($branding->appValue('signup_text'));
        // Off is kept, although the default is on.
        $this->assertFalse($branding->appValue('signup_show_privacy'));
        $this->assertSame('inline', $branding->appValue('signup_layout'));
        $this->assertSame('3', app(BrandingStyle::class)->appButtonSlot('signup_button'));
        $this->assertStringStartsWith('site-builder/branding/', $branding->get('branding.app_signup_image'));
        $this->assertStringContainsString('/storage/site-builder/branding/', (string) $branding->appImageUrl('signup_image'));
        $this->assertStringContainsString('--color-signup-bg: #123456', $branding->siteCssVariables());
        $this->assertStringContainsString('--color-sb-app_signup_banner-heading: #fff5cc', $branding->siteCssVariables());

        $html = Blade::render('<x-site-builder::banner variant="app_signup_banner"><h2>Join us</h2></x-site-builder::banner>');
        $this->assertStringContainsString('background-color: #0a3d2e', $html);
        $this->assertStringContainsString('var(--color-sb-app_signup_banner-heading, var(--color-sb-inner-heading))', $html);
    }

    public function test_it_validates_like_the_packages_own_settings_and_warns_about_contrast(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'app_signup_title' => str_repeat('x', 81),
                'app_signup_layout' => 'sideways',
                'app_signup_bg' => 'red',
                'app_signup_button' => '9',
            ])
            ->call('save')
            ->assertHasFormErrors(['app_signup_title' => 'max', 'app_signup_layout' => 'in', 'app_signup_bg' => 'regex', 'app_signup_button' => 'in']);

        Livewire::test(ManageBranding::class)
            ->fillForm(['app_signup_bg' => '#ffffff', 'app_signup_text_color' => '#fefefe'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Some colours may be hard to read');
    }

    public function test_hand_edited_values_that_fail_their_checks_are_dropped(): void
    {
        app(Settings::class)->set([
            'branding.app_signup_title' => ['an' => 'array'],
            'branding.app_signup_show_privacy' => 'yes',
            'branding.app_signup_layout' => 'evil',
            'branding.app_signup_bg' => 'red; } body { display: none',
            'branding.app_signup_button' => '9',
            'branding.app_signup_image' => 'site-builder/branding/../../.env',
            'branding.app_signup_banner_background_type' => 'image',
            'branding.app_signup_banner_image' => 'elsewhere/photo.jpg',
        ]);

        $branding = app(Branding::class);

        $this->assertSame('Stay in touch', $branding->appValue('signup_title'));
        $this->assertTrue($branding->appValue('signup_show_privacy'));
        $this->assertSame('stacked', $branding->appValue('signup_layout'));
        $this->assertNull($branding->appValue('signup_bg'));
        $this->assertSame('1', app(BrandingStyle::class)->appButtonSlot('signup_button'));
        $this->assertNull($branding->appImageUrl('signup_image'));
        $this->assertNull($branding->banner('app_signup_banner')['image']);
        $this->assertStringNotContainsString('display: none', $branding->siteCssVariables());
        $this->assertNull($branding->appValue('no_such_field'));
    }

    public function test_automatic_button_styles_suit_the_background(): void
    {
        app(Branding::class)->save([BrandingStyle::BUTTON_ON_DARK => '4']);

        $this->assertSame('1', app(BrandingStyle::class)->appButtonSlot('signup_button'));
        $this->assertSame('4', app(BrandingStyle::class)->appButtonSlot('signup_button', isOnDarkBackground: true));

        // The older host-owned setting reader understands Automatic too, and still defaults to style 1.
        app(Settings::class)->set(['branding.app_signup_button' => 'auto']);
        $this->assertSame('4', app(BrandingStyle::class)->siteButtonSlot('branding.app_signup_button', isOnDarkBackground: true));
        $this->assertSame('1', app(BrandingStyle::class)->siteButtonSlot('branding.not_set'));
    }

    public function test_a_replaced_image_is_deleted_and_clean_uploads_keeps_the_saved_one(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm(['app_signup_image' => UploadedFile::fake()->image('first.png', 400, 300)])
            ->call('save')
            ->assertHasNoFormErrors();

        $first = app(Branding::class)->get('branding.app_signup_image');

        Livewire::test(ManageBranding::class)
            ->set('data.app_signup_image', [])
            ->fillForm(['app_signup_image' => UploadedFile::fake()->image('second.png', 400, 300)])
            ->call('save')
            ->assertHasNoFormErrors();

        $second = app(Branding::class)->get('branding.app_signup_image');
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        touch(Storage::disk('public')->path($second), now()->subDays(3)->getTimestamp());
        $this->artisan('site-builder:clean-uploads')->assertSuccessful();
        Storage::disk('public')->assertExists($second);
    }
}
