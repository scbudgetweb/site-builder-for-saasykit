<?php

namespace ScBudgetWeb\SiteBuilder;

use Filament\FontProviders\LocalFontProvider;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\FileViewFinder;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockPresets;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\PageRenderer;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Branding\BrandingField;
use ScBudgetWeb\SiteBuilder\Branding\BrandingStyle;
use ScBudgetWeb\SiteBuilder\Console\CleanUnusedUploadsCommand;
use ScBudgetWeb\SiteBuilder\Console\GeneratePageSitemapCommand;
use ScBudgetWeb\SiteBuilder\Console\InstallCommand;
use ScBudgetWeb\SiteBuilder\Legal\LegalPage;
use ScBudgetWeb\SiteBuilder\Livewire\MailingListSignup;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;
use ScBudgetWeb\SiteBuilder\Pages\PagePolicy;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Pages\SavedBlockPolicy;
use ScBudgetWeb\SiteBuilder\Pages\Slot;
use ScBudgetWeb\SiteBuilder\Pages\TemplatePolicy;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\Support\LivewireLimits;
use ScBudgetWeb\SiteBuilder\Templates\Templates;
use Throwable;

class SiteBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/site-builder.php', 'site-builder');

        // Scoped: it keeps the settings it reads for the request (or queue job) only.
        $this->app->scoped(Settings::class);
        $this->app->singleton(SiteBuilder::class);
        $this->app->singleton(Branding::class);
        $this->app->singleton(BrandingStyle::class);
        $this->app->singleton(PageService::class);
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(PageRenderer::class);
        $this->app->singleton(BlockPresets::class);
        $this->app->singleton(Templates::class);

        // The base theme: its views are found before SaaSykit's, so theirs are shadowed, never edited.
        $this->callAfterResolving('view', function ($view): void {
            if (config('site-builder.theme.enabled', true)) {
                self::addThemePath($view->getFinder());
            }
        });

        // "after" so it runs once the app's panel providers (their own resolving callbacks) have registered their panels.
        $this->app->afterResolving(PanelRegistry::class, function (PanelRegistry $registry): void {
            foreach ($registry->all() as $panel) {
                $this->brandPanel($panel);
            }
        });
    }

    public function boot(): void
    {
        // Loaded rather than published, so package updates bring their own migrations.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'site-builder');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'site-builder');

        // Room for rich text's nested updates in the builder's panels (Livewire allows 10 path parts by default).
        LivewireLimits::apply();

        if (config('site-builder.pages.routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(SavedBlock::class, SavedBlockPolicy::class);
        Gate::policy(Template::class, TemplatePolicy::class);

        // The standard built-in menu links (Website › Navigation). Apps add or replace theirs with SiteBuilder::links().
        $this->app->make(SiteBuilder::class)->links(NavigationMenus::standardLinks());

        // The legal pages (Website › Legal pages), with SaaSykit's text until an admin saves their own.
        $this->app->make(SiteBuilder::class)->legalPages([
            LegalPage::make('privacy_policy', 'Privacy Policy', fn (): string => route('privacy-policy'), 'site-builder::legal.privacy-policy-default'),
            LegalPage::make('terms_of_service', 'Terms of Service', fn (): string => route('terms-of-service'), 'site-builder::legal.terms-of-service-default'),
        ]);

        // The mailing list sign-up form (Website › Mailing lists, the Mailing list block), and its Branding section.
        Livewire::component('site-builder.mailing-list-signup', MailingListSignup::class);
        $this->app->make(SiteBuilder::class)->brandingSection('newsletter', __('Mailing list'), self::mailingListBrandingFields(),
            description: __('The mailing list sign-up form: its wording, button and colours. It shows once Website › Mailing lists has a service and list in use.'),
            icon: 'heroicon-o-envelope-open');

        // The theme's home page can be replaced by a page (the theme's home view asks PageService::forSlot('home')).
        if (config('site-builder.theme.enabled', true)) {
            $this->app->make(SiteBuilder::class)->slots([
                Slot::make('home', 'Home page', fn (): string => route('home')),
            ]);
        }

        $this->overrideLogoConfig();
        $this->registerPanelHooks();

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, GeneratePageSitemapCommand::class, CleanUnusedUploadsCommand::class]);

            $this->publishes([__DIR__.'/../config/site-builder.php' => config_path('site-builder.php')], 'site-builder-config');
            $this->publishes([__DIR__.'/../resources/fonts' => public_path(Branding::PACKAGE_FONTS_DIRECTORY)], 'site-builder-assets');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/site-builder')], 'site-builder-views');
            $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/site-builder')], 'site-builder-translations');
        }
    }

    /**
     * Branding › Mailing list: the sign-up form's default wording and look (a block can have its own wording).
     *
     * @return array<int, BrandingField>
     */
    private static function mailingListBrandingFields(): array
    {
        return [
            BrandingField::text('newsletter_eyebrow', __('Small heading'), 60)->default(__('Stay up to date')),
            BrandingField::text('newsletter_title', __('Heading'), 120)->default(__('Subscribe to our newsletter')),
            BrandingField::textarea('newsletter_subtext', __('Text'), 250)->default(__('The latest news, straight to your inbox.')),
            BrandingField::text('newsletter_placeholder', __('Email field placeholder'), 60)->default(__('Your email address')),
            BrandingField::text('newsletter_button_text', __('Button text'), 40)->default(__('Subscribe')),
            BrandingField::textarea('newsletter_success_message', __('Thank-you message'), 200)->default(__('Thanks for subscribing! Please check your inbox.')),
            BrandingField::textarea('newsletter_consent_text', __('Small print'), 250)
                ->helperText(__('Shown under the form. ":privacy" and ":terms" become links to the Privacy Policy and Terms of Service.'))
                ->default(__('By signing up you agree to our :privacy and :terms.')),
            BrandingField::buttonStyle('newsletter_button_style', __('Button style')),
            BrandingField::colour('newsletter_consent_color', __('Small print colour'))
                ->helperText(__('Blank: the subtitle colour.')),
            BrandingField::banner('newsletter', __('Background & text colours'))
                ->helperText(__('The sign-up form\'s background, wherever it shows.')),
        ];
    }

    public static function themePath(): string
    {
        return dirname(__DIR__).'/resources/views/theme';
    }

    /**
     * Puts the theme straight after the app's own theme folder (site-builder.theme.app_path) when view.paths has it,
     * so the app's overrides win, otherwise in front of every path. Either way it comes before SaaSykit's views.
     */
    private static function addThemePath(FileViewFinder $finder): void
    {
        $appThemePath = config('site-builder.theme.app_path');
        $paths = $finder->getPaths();
        // The finder stores real paths, so compare like with like.
        $position = is_string($appThemePath) && $appThemePath !== ''
            ? array_search(realpath($appThemePath) ?: $appThemePath, $paths, true)
            : false;

        if ($position === false) {
            $finder->prependLocation(self::themePath());

            return;
        }

        array_splice($paths, $position + 1, 0, [self::themePath()]);
        $finder->setPaths($paths);
    }

    /**
     * Applies Branding to a panel without editing its provider. Anything not set in Branding keeps the
     * panel's own setting (colour, font), so Branding only ever adds to what the app chose.
     */
    private function brandPanel(Panel $panel): void
    {
        $branding = fn (): Branding => $this->app->make(Branding::class);

        // Colour sets are merged in order, so this only replaces "primary" when a colour is set.
        $panel->colors(fn (): array => ($palette = $branding()->panelPalette($panel->getId())) ? ['primary' => $palette] : []);

        // The font provider can't be a closure, so the dashboard font is read now. With none chosen, the panel keeps its own font.
        try {
            if (($font = $branding()->appFont()) !== null) {
                $panel->font(
                    fn (): string => $branding()->fontFamily($font),
                    url: fn (): string => $branding()->fontStylesheetUrl($font),
                    provider: LocalFontProvider::class,
                );
            }
        } catch (Throwable) {
            // The settings table may not exist yet (a fresh install, before migrating).
        }

        $panel
            ->favicon(fn (): string => $branding()->faviconUrl())
            ->brandLogo(fn (): ?string => $branding()->showsLogoInPanels() ? $branding()->logoUrlFor(Branding::LOGO_DASHBOARDS) : null)
            // Dark mode uses the other logo, or the same one when only one is uploaded.
            ->darkModeBrandLogo(fn (): ?string => $branding()->showsLogoInPanels()
                ? ($branding()->logoUrl($branding()->logoVariantFor(Branding::LOGO_DASHBOARDS) === 'light' ? 'dark' : 'light') ?? $branding()->logoUrlFor(Branding::LOGO_DASHBOARDS))
                : null)
            ->brandLogoHeight('2rem');
    }

    private function registerPanelHooks(): void
    {
        // Point Tailwind's primary-* utilities in custom panel views at the panel's own primary colour
        // (SaaSykit's colors.css sets them to its purple).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): HtmlString => new HtmlString('<style>:root { '.collect([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950])
                ->map(fn (int $shade): string => "--color-primary-{$shade}: var(--primary-{$shade})")
                ->implode('; ').'; }</style>'),
        );

        // Dashboard headings in the Typography heading font, when one is chosen.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): HtmlString => new HtmlString($this->app->make(Branding::class)->panelHeadingFontHtml()),
        );
    }

    /**
     * SaaSykit's views (emails, sign-in screens, social cards) read these through asset(), which passes absolute
     * URLs through. SaaSykit's "dark" logo is the one shown on white, so it gets the Sign-in screens & emails choice.
     */
    private function overrideLogoConfig(): void
    {
        try {
            $branding = $this->app->make(Branding::class);

            config(array_filter([
                'app.logo.light' => $branding->logoUrl('light'),
                'app.logo.dark' => $branding->logoUrlFor(Branding::LOGO_AUTH) ?? $branding->logoUrl('light') ?? $branding->logoUrl('dark'),
            ]));
        } catch (Throwable) {
            // The settings table may not exist yet (a fresh install, before migrating).
        }
    }
}
