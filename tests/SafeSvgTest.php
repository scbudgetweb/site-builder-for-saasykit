<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;
use ScBudgetWeb\SiteBuilder\Support\SafeSvg;

/**
 * SVG logos and favicons are served from the site's own address, so only plain drawings are accepted.
 */
class SafeSvgTest extends TestCase
{
    private const PLAIN = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" viewBox="0 0 200 50">'
        .'<title>Village Hall</title><inkscape:namedview pagecolor="#ffffff"/>'
        .'<defs><linearGradient id="g"><stop offset="0" stop-color="#0a3d2e"/><stop offset="1" stop-color="#1d41d5"/></linearGradient><style>.t{font-family:sans-serif}</style></defs>'
        .'<rect width="200" height="50" fill="url(#g)" style="opacity:.9"/><use xlink:href="#g"/><text class="t" x="10" y="30">Village Hall</text></svg>';

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_a_plain_drawing_is_accepted(): void
    {
        $this->assertNull(SafeSvg::problem(self::PLAIN));
    }

    public function test_svgs_with_active_content_or_links_to_other_files_are_refused(): void
    {
        $svg = fn (string $inner, string $attributes = ''): string => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '.$attributes.'>'.$inner.'</svg>';

        foreach ([
            $svg('<script>alert(1)</script>'),
            $svg('', 'onload="alert(1)"'),
            $svg('<rect width="1" height="1" onclick="alert(1)"/>'),
            $svg('<foreignObject><div xmlns="http://www.w3.org/1999/xhtml">Hi</div></foreignObject>'),
            $svg('<a href="javascript:alert(1)"><text>x</text></a>'),
            $svg('<use xlink:href="https://evil.example/sprite.svg#icon"/>'),
            $svg('<image href="https://evil.example/track.png"/>'),
            $svg('<rect fill="url(https://evil.example/x.svg#p)"/>'),
            $svg('<style>@import url(https://evil.example/x.css);</style>'),
            $svg('<rect style="background:url(//evil.example/x.png)"/>'),
            $svg('<set attributeName="href" to="javascript:alert(1)"/>'),
            $svg('<html:script xmlns:html="http://www.w3.org/1999/xhtml">alert(1)</html:script>'),
            '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>',
            '<html><body>not an svg</body></html>',
            'not xml at all',
        ] as $input) {
            $this->assertNotNull(SafeSvg::problem($input), $input);
        }
    }

    public function test_branding_refuses_an_unsafe_svg_logo_and_keeps_a_plain_one(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::define('update settings', fn (User $user): bool => true);
        $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Test', 'email' => 'admin@example.com']));

        Livewire::test(ManageBranding::class)
            ->fillForm(['logo_light' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->call('save')
            ->assertHasFormErrors(['logo_light']);

        $this->assertNull(app(Branding::class)->get('branding.logo_light'));

        Livewire::test(ManageBranding::class)
            ->fillForm(['logo_light' => UploadedFile::fake()->createWithContent('logo.svg', self::PLAIN)])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStringEndsWith('.svg', (string) app(Branding::class)->get('branding.logo_light'));
    }

    public function test_branding_access_fails_closed_when_its_permission_isnt_a_name(): void
    {
        Gate::before(fn (): bool => true);
        $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Test', 'email' => 'admin@example.com']));

        config(['site-builder.permissions.branding' => ['update settings']]);
        $this->assertFalse(ManageBranding::canAccess());

        config(['site-builder.permissions.branding' => 'update settings']);
        $this->assertTrue(ManageBranding::canAccess());
    }
}
