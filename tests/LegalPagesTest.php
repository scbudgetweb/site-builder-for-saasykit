<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageLegalPages;
use ScBudgetWeb\SiteBuilder\Legal\LegalPage;
use ScBudgetWeb\SiteBuilder\Legal\LegalPages;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\SiteBuilderServiceProvider;

/**
 * Admin › Website › Legal pages and the public legal pages (HB-95, D0a-12).
 */
class LegalPagesTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.support_email', 'help@example.com');
    }

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->get('/privacy-policy', fn () => view('pages.privacy-policy'))->name('privacy-policy');
        $router->get('/terms-of-service', fn () => view('pages.terms-of-service'))->name('terms-of-service');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::define('update settings', fn ($user): bool => $user->getAttribute('email') === 'admin@example.com');
    }

    public function test_people_who_can_update_settings_can_open_it_in_the_website_group(): void
    {
        $this->actingAs($this->user());
        $this->assertTrue(ManageLegalPages::canAccess());
        $this->assertSame('Website', ManageLegalPages::getNavigationGroup());
        $this->assertSame(5, ManageLegalPages::getNavigationSort());

        $this->actingAs($this->user('someone@example.com'));
        $this->assertFalse(ManageLegalPages::canAccess());
    }

    public function test_the_editor_opens_with_the_saasykit_text_or_the_saved_content(): void
    {
        $this->actingAs($this->user());
        app(LegalPages::class)->save($this->legalPage('terms_of_service'), '<p>Our own terms.</p>');

        $component = Livewire::test(ManageLegalPages::class)->assertSee(['Privacy Policy', 'Terms of Service']);
        $state = $component->instance()->form->getState();

        $this->assertStringContainsString('Example Site (“us”, “we”, or “our”) operates', $state['privacy_policy']);
        $this->assertStringContainsString('Our own terms.', $state['terms_of_service']);
    }

    public function test_saving_stores_each_page_and_clearing_one_goes_back_to_the_template(): void
    {
        $this->actingAs($this->user());

        Livewire::test(ManageLegalPages::class)
            ->fillForm(['privacy_policy' => '<h2>Your data</h2><p>We keep it safe.</p>', 'terms_of_service' => '<p> </p>'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Legal pages saved');

        $legalPages = app(LegalPages::class);
        $this->assertTrue($legalPages->hasCustomContent($this->legalPage('privacy_policy')));
        $this->assertFalse($legalPages->hasCustomContent($this->legalPage('terms_of_service')));
    }

    public function test_unchanged_content_keeps_its_last_updated_date(): void
    {
        $legalPages = app(LegalPages::class);
        $page = $this->legalPage('privacy_policy');

        Carbon::setTestNow('2026-01-10 09:00');
        $legalPages->save($page, '<p>Version one</p>');

        Carbon::setTestNow('2026-03-01 09:00');
        $legalPages->save($page, '<p>Version one</p>');
        $this->assertSame('2026-01-10', $legalPages->lastUpdated($page)->toDateString());

        $legalPages->save($page, '<p>Version two</p>');
        $this->assertSame('2026-03-01', $legalPages->lastUpdated($page)->toDateString());

        Carbon::setTestNow();
    }

    public function test_the_public_page_shows_the_template_until_content_is_saved(): void
    {
        $this->app['view']->getFinder()->prependLocation(SiteBuilderServiceProvider::themePath());

        $html = Blade::render('<x-site-builder::legal-page key="privacy_policy" />');
        $this->assertStringContainsString('Example Site (“us”, “we”, or “our”) operates', $html);
        $this->assertStringContainsString('href="http://localhost/terms-of-service"', $html);
        $this->assertStringNotContainsString('Last updated', $html);

        app(LegalPages::class)->save($this->legalPage('privacy_policy'), '<p>Contact <span data-type="mergeTag" data-id="support_email"></span> about <span data-type="mergeTag" data-id="site_name"></span>.</p>');

        $html = html_entity_decode(Blade::render('<x-site-builder::legal-page key="privacy_policy" />'));
        $this->assertStringContainsString('help@example.com', $html);
        $this->assertStringContainsString('Example Site', $html);
        $this->assertStringContainsString('Last updated', $html);
        $this->assertStringNotContainsString('operates the', $html);
    }

    public function test_unsafe_html_is_not_shown(): void
    {
        app(LegalPages::class)->save($this->legalPage('terms_of_service'), '<p>Fine<script>alert(1)</script> <a href="javascript:alert(2)">bad</a> <img src="x" onerror="alert(3)"></p>');

        $html = app(LegalPages::class)->html($this->legalPage('terms_of_service'));

        $this->assertStringContainsString('Fine', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_apps_can_add_legal_pages_with_their_own_merge_tag(): void
    {
        app(SiteBuilder::class)->legalPages([LegalPage::make('cookie_policy', 'Cookie Policy', fn (): string => url('/cookies'))]);
        $this->actingAs($this->user());

        $component = Livewire::test(ManageLegalPages::class)->assertSee('Cookie Policy');
        $this->assertTrue(LegalPages::isBlank($component->instance()->form->getState()['cookie_policy'] ?? null));

        $this->assertSame('http://localhost/cookies', app(LegalPages::class)->mergeTagValues()['cookie_policy_url']);
        $this->assertSame('', trim(Blade::render('<x-site-builder::legal-page key="cookie_policy" />')));
    }

    public function test_the_theme_serves_the_legal_pages(): void
    {
        $this->app['view']->getFinder()->prependLocation(SiteBuilderServiceProvider::themePath());

        $finder = $this->app['view']->getFinder();
        $this->assertStringStartsWith(SiteBuilderServiceProvider::themePath(), $finder->find('pages.privacy-policy'));
        $this->assertStringStartsWith(SiteBuilderServiceProvider::themePath(), $finder->find('pages.terms-of-service'));
    }

    private function legalPage(string $key): LegalPage
    {
        return app(LegalPages::class)->find($key);
    }
}
