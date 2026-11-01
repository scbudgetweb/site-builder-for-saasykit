<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\CreateTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\EditTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\TemplateVisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;
use ScBudgetWeb\SiteBuilder\SiteBuilderServiceProvider;
use ScBudgetWeb\SiteBuilder\Templates\HeaderBehaviour;
use ScBudgetWeb\SiteBuilder\Templates\Templates;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\AnnouncementStandIn;

/**
 * How the header behaves as the page scrolls (Branding for the standard header, a header template's Header settings):
 * staying at the top, shrinking, a shadow, sitting over a Banner or Hero that opens the page; the sample page under a
 * header template's canvas; and Branding's Back to top button.
 */
class HeaderBehaviourTest extends PageTestCase
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
    }

    /**
     * @param  array<string, mixed>  $behaviour
     */
    private function brandingBehaviour(array $behaviour): void
    {
        app(Branding::class)->save([Branding::HEADER_BEHAVIOUR => $behaviour]);
    }

    /**
     * The theme's header (the standard one, or the header template that applies) on $page. The theme's views are only
     * used for this, as pages here are drawn in the stand-in layout.
     */
    private function headerHtml(Page $page): string
    {
        Livewire::component('announcement.view', AnnouncementStandIn::class);
        // As on a request of its own: no banner from an earlier page has said it opens this one.
        request()->attributes->remove(HeaderBehaviour::OPENING_BANNER_ATTRIBUTE);
        request()->attributes->set(Templates::PAGE_ATTRIBUTE, $page);

        $finder = $this->app['view']->getFinder();
        $paths = $finder->getPaths();
        $finder->prependLocation(SiteBuilderServiceProvider::themePath());

        try {
            return Blade::render('<x-layouts.app.header />');
        } finally {
            $finder->setPaths($paths);
            $finder->flush();
            request()->attributes->remove(Templates::PAGE_ATTRIBUTE);
        }
    }

    /**
     * The header's opening <header> tag on $page.
     */
    private function headerTag(Page $page): string
    {
        preg_match('/<header [^>]*>/', $this->headerHtml($page), $matches);

        return $matches[0] ?? '';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function headerTemplate(array $attributes = []): Template
    {
        return Template::query()->create([
            'name' => 'Main header',
            'type' => 'header',
            'blocks' => [self::block('site_header')],
            ...$attributes,
        ]);
    }

    public function test_saved_settings_are_checked(): void
    {
        $this->assertSame(HeaderBehaviour::defaults(), HeaderBehaviour::clean(null));
        $this->assertSame(
            ['sticky' => 'scroll_up', 'shrink' => true, 'shadow' => false, 'over' => true, 'over_text' => 'light', 'over_logo' => 'same'],
            HeaderBehaviour::clean(['sticky' => 'scroll_up', 'shrink' => '1', 'shadow' => 0, 'over' => true, 'over_text' => 'purple', 'over_logo' => ['dark']]),
        );
        $this->assertSame('none', HeaderBehaviour::clean(['sticky' => 'sideways'])['sticky']);
    }

    public function test_the_standard_header_scrolls_away_unless_branding_says_otherwise(): void
    {
        $page = $this->page(['show_page_banner' => false, 'blocks' => [self::block('text', ['content' => '<p>Welcome</p>'])]]);

        $html = $this->headerHtml($page);
        $this->assertStringContainsString('<header class="relative z-40 bg-sb-header-bg text-sb-header-text border-b border-current/10 sb-header-overlayable"', $html);
        $this->assertStringNotContainsString('data-sb-header', $html);
        $this->assertStringNotContainsString('window.siteBuilderHeader', $html, 'No script for a header that does nothing.');

        $this->brandingBehaviour(['sticky' => 'always', 'shrink' => true, 'shadow' => true]);
        $tag = $this->headerTag($page);
        $this->assertStringContainsString('sb-header-overlayable sb-header-sticky sb-header-shrinks sb-header-shadows', $tag);
        $this->assertStringContainsString('data-sb-header', $tag);
        $this->assertStringNotContainsString('sb-header-hides', $tag);
        $this->assertStringNotContainsString('sb-header-over', str_replace('sb-header-overlayable', '', $tag), 'Not over a page that opens with text.');

        $this->brandingBehaviour(['sticky' => 'scroll_up', 'shrink' => false, 'shadow' => false]);
        $html = $this->headerHtml($page);
        $this->assertMatchesRegularExpression('/<header [^>]*sb-header-sticky sb-header-hides"/', $html);
        $this->assertSame(1, substr_count($html, 'window.siteBuilderHeader = true'));
        $this->assertStringContainsString('class="sb-header-row sb-header-part mx-auto flex min-h-16', $html, 'Its row shrinks and takes the see-through colours.');

        $this->brandingBehaviour(['sticky' => 'none', 'shrink' => true, 'shadow' => true]);
        $this->assertStringNotContainsString('data-sb-header', $this->headerHtml($page), 'Shrinking and the shadow need it to stay at the top.');
    }

    public function test_it_sits_over_a_banner_or_hero_that_opens_the_page(): void
    {
        app(Branding::class)->save([Branding::LOGO_DARK => 'site-builder/logo-dark.png']);
        $this->brandingBehaviour(['over' => true, 'over_text' => 'dark', 'over_logo' => 'dark']);

        $hero = $this->page(['show_page_banner' => false, 'blocks' => [self::block('hero', ['heading' => 'Welcome to the Village Hall'])]]);
        $tag = $this->headerTag($hero);
        $this->assertStringContainsString('sb-header-overlayable sb-header-over', $tag);
        $this->assertStringContainsString('data-over-text="dark"', $tag);
        $this->assertMatchesRegularExpression('/data-over-logo="[^"]*site-builder\/logo-dark\.png"/', $tag, 'The other logo while see-through.');
        $this->assertSame(1, substr_count($this->get('/about-us')->assertSuccessful()->getContent(), 'class="sb-header-space"'), 'The hero leaves room for it.');

        $standard = $this->page(['slug' => 'standard-banner', 'blocks' => [self::block('text', ['content' => '<p>Opening hours</p>'])]]);
        $this->assertStringContainsString('sb-header-over', str_replace('sb-header-overlayable', '', $this->headerTag($standard)), 'The standard page banner counts.');
        $this->assertSame(1, substr_count($this->get('/standard-banner')->getContent(), 'class="sb-header-space"'));

        $textFirst = $this->page(['slug' => 'text-first', 'show_page_banner' => false, 'blocks' => [
            self::block('text', ['content' => '<p>Opening hours</p>']),
            self::block('banner', ['heading' => 'Book a room']),
        ]]);
        $this->assertStringNotContainsString('sb-header-over', str_replace('sb-header-overlayable', '', $this->headerTag($textFirst)), 'Elsewhere the header shows as normal.');
        $this->assertStringNotContainsString('sb-header-space', $this->get('/text-first')->getContent(), 'A banner further down leaves no room.');

        $this->brandingBehaviour(['over' => true, 'over_logo' => 'light']);
        $this->assertStringNotContainsString('data-over-logo', $this->headerTag($hero), 'Not when that logo isn\'t uploaded.');

        $this->assertSame(1, substr_count(Blade::render('<x-site-builder::banner :opening="true">Hello</x-site-builder::banner>'), 'class="sb-header-space"'));
        $this->assertTrue(HeaderBehaviour::pageOpensWithBanner(), 'A theme page\'s own opening banner (the home page, the blog) counts too.');
    }

    public function test_a_header_template_has_its_own_behaviour(): void
    {
        $this->brandingBehaviour(['sticky' => 'always']);
        $this->headerTemplate(['settings' => ['sticky' => 'scroll_up', 'over' => true]]);
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Welcome</p>'])]]);

        $html = $this->headerHtml($page);
        $tag = $this->headerTag($page);
        $this->assertStringContainsString('class="relative z-40 sb-header-template sb-header-sticky sb-header-hides sb-header-shadows sb-header-over"', $tag, 'Its own settings, not Branding\'s.');
        $this->assertStringContainsString('data-over-text="light"', $tag);
        $this->assertStringContainsString('sb-header-bar bg-sb-header-bg text-sb-header-text border-b border-current/10 sb-header-overlayable', $html);
        $this->assertStringContainsString('sb-header-row sb-header-part', $html);
        $this->assertSame(1, substr_count($html, 'window.siteBuilderHeader = true'));

        Carbon::setTestNow(now()->addMinute());
        $this->headerTemplate(['name' => 'Own background', 'settings' => ['over' => true], 'blocks' => [
            self::block('site_header', ['appearance' => ['background' => 'colour', 'background_color' => '#4a1d3f']]),
        ]]);
        $html = $this->headerHtml($page);
        $this->assertStringNotContainsString('sb-header-overlayable', $html, 'A Header bar with a Background of its own keeps it.');
        $this->assertStringNotContainsString('sb-header-part', $html);

        Carbon::setTestNow(now()->addMinutes(2));
        $this->headerTemplate(['name' => 'Plain', 'settings' => null]);
        $this->assertStringNotContainsString('data-sb-header', $this->headerTag($page), 'No settings: it scrolls away.');
    }

    public function test_header_settings_are_saved_from_the_visual_builder_and_shown_on_a_sample_page(): void
    {
        $this->actingAs($this->user());
        $template = $this->headerTemplate();

        $editor = Livewire::test(TemplateVisualEditor::class, ['record' => $template->getRouteKey()]);
        $editor->callAction(TestAction::make('addBlock'), ['type' => 'text']);
        $draft = app(VisualDrafts::class)->find($template, $this->user());
        $this->assertNotNull($draft);

        Carbon::setTestNow(now()->addMinute());
        Livewire::test(TemplateVisualEditor::class, ['record' => $template->getRouteKey()])
            ->assertActionVisible('headerSettings')
            ->mountAction('headerSettings')
            ->assertSchemaStateSet(HeaderBehaviour::defaults())
            ->callMountedAction()
            ->assertHasNoFormErrors();

        Livewire::test(TemplateVisualEditor::class, ['record' => $template->getRouteKey()])
            ->callAction('headerSettings', ['sticky' => 'always', 'shrink' => true, 'over' => true, 'over_text' => 'dark'])
            ->assertHasNoFormErrors()
            ->assertNotified('Header settings saved')
            ->assertDispatched('site-builder-canvas-reload');

        $template->refresh();
        $this->assertSame(['sticky' => 'always', 'shrink' => true, 'shadow' => true, 'over' => true, 'over_text' => 'dark', 'over_logo' => 'same'], $template->settings);
        $this->assertFalse(app(VisualDrafts::class)->isOutOfDate($draft->fresh(), $template), 'Saving the settings isn\'t someone else\'s change.');
        $this->assertCount(2, $draft->fresh()->blocks, 'The draft (the Header bar and the Text block added) is kept.');

        $html = $this->get($draft->fresh()->canvasUrl())->assertSuccessful()->getContent();
        $this->assertMatchesRegularExpression('/<div class="relative z-40 sb-header-template sb-header-sticky sb-header-shrinks sb-header-shadows sb-header-over"[^>]*data-over-text="dark"[^>]*data-sb-header/', $html);
        $this->assertMatchesRegularExpression('/data-sb-header[^>]*>\s*<div data-page/', $html, 'The template\'s blocks, which the canvas swaps, inside the header.');
        $this->assertStringContainsString('<div data-sb-sample>', $html);
        $this->assertStringContainsString('A sample page', $html);
        $this->assertSame(1, substr_count($html, 'class="sb-header-space"'), 'The sample\'s Hero leaves room for the header.');
        $this->assertSame(1, substr_count($html, 'window.siteBuilderHeader = true'));
    }

    public function test_only_a_header_has_header_settings(): void
    {
        $this->actingAs($this->user());
        $footer = Template::query()->create(['name' => 'Main footer', 'type' => 'footer', 'blocks' => []]);

        Livewire::test(TemplateVisualEditor::class, ['record' => $footer->getRouteKey()])->assertActionHidden('headerSettings');

        // Drawn in the toolbar: neither Header settings, nor Discard changes with nothing to discard.
        $toolbar = $this->get(TemplateResource::getUrl('visual', ['record' => $footer]))->assertSuccessful()->getContent();
        $this->assertStringNotContainsString('Header settings', $toolbar);
        $this->assertStringNotContainsString('Discard changes', $toolbar);
        $this->assertStringContainsString('Header settings', $this->get(TemplateResource::getUrl('visual', ['record' => $this->headerTemplate()]))->getContent());

        $html = $this->get(app(VisualDrafts::class)->open($footer, $this->user())->canvasUrl())->assertSuccessful()->getContent();
        $this->assertStringNotContainsString('data-sb-sample', $html);

        Livewire::test(CreateTemplate::class)
            ->fillForm(['name' => 'Shop footer', 'type' => 'footer', 'applies_to' => Template::EVERYWHERE])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertNull(Template::query()->where('name', 'Shop footer')->sole()->settings);
    }

    public function test_the_template_form_has_the_header_settings_too(): void
    {
        $this->actingAs($this->user());

        Livewire::test(CreateTemplate::class)
            ->fillForm(['name' => 'Main header', 'type' => 'header', 'applies_to' => Template::EVERYWHERE, 'settings' => ['sticky' => 'scroll_up']])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = Template::query()->sole();
        $this->assertSame('scroll_up', $template->settings['sticky']);
        $this->assertSame(HeaderBehaviour::clean(['sticky' => 'scroll_up']), $template->settings, 'Stored checked, with every setting.');

        $template->forceFill(['settings' => ['sticky' => 'sideways']])->save();

        Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])
            ->assertSchemaStateSet(['settings.sticky' => 'none', 'settings.over' => false])
            ->fillForm(['settings' => ['sticky' => 'always', 'over' => true, 'over_text' => 'dark']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['sticky' => 'always', 'shrink' => false, 'shadow' => true, 'over' => true, 'over_text' => 'dark', 'over_logo' => 'same'], $template->fresh()->settings);
    }

    public function test_branding_saves_the_standard_headers_behaviour_and_back_to_top(): void
    {
        $this->actingAs($this->user());
        $page = $this->page(['show_page_banner' => false]);
        $this->assertNull(app(Branding::class)->backToTop(), 'No Back to top button as standard.');

        Livewire::test(ManageBranding::class)
            ->assertSchemaStateSet(['header_behaviour.sticky' => 'none', 'back_to_top' => null])
            ->fillForm(['header_behaviour' => ['sticky' => 'always', 'shrink' => true], 'back_to_top' => 'left'])
            ->call('save')
            ->assertHasNoFormErrors();

        $branding = app(Branding::class);
        $this->assertSame(['sticky' => 'always', 'shrink' => true, 'shadow' => true, 'over' => false, 'over_text' => 'light', 'over_logo' => 'same'], $branding->get(Branding::HEADER_BEHAVIOUR));
        $this->assertSame('left', $branding->backToTop());

        $this->assertStringContainsString('sb-header-sticky sb-header-shrinks', $this->headerTag($page));
        $button = view('site-builder::theme.partials.back-to-top', ['place' => $branding->backToTop()])->render();
        $this->assertMatchesRegularExpression('/<button type="button".*class="sb-back-to-top fixed[^"]*left-5"\s+aria-label="Back to top"/s', $button);
        $this->assertStringContainsString("'instant' : 'smooth'", $button, 'Straight to the top for visitors who turn off animations.');
        $this->assertStringContainsString('right-5', view('site-builder::theme.partials.back-to-top', ['place' => 'right'])->render());

        Livewire::test(ManageBranding::class)
            ->fillForm(['header_behaviour' => ['sticky' => 'none'], 'back_to_top' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(app(Branding::class)->get(Branding::HEADER_BEHAVIOUR), 'The standard behaviour is stored as no value.');
        $this->assertNull(app(Branding::class)->backToTop());

        app(Branding::class)->save([Branding::BACK_TO_TOP => 'middle']);
        $this->assertNull(app(Branding::class)->backToTop(), 'An unknown place is ignored.');
    }
}
