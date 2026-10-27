<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageNavigation;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;

/**
 * Admin › Website › Navigation (HB-95).
 */
class ManageNavigationPageTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->get('/privacy-policy', fn () => 'Privacy')->name('privacy-policy');
        $router->get('/terms-of-service', fn () => 'Terms')->name('terms-of-service');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
    }

    public function test_people_who_can_update_pages_can_open_it_in_the_website_group(): void
    {
        $this->assertTrue(ManageNavigation::canAccess());
        $this->assertSame('Website', ManageNavigation::getNavigationGroup());
        $this->assertSame(3, ManageNavigation::getNavigationSort());

        $this->actingAs($this->user('someone@example.com'));
        $this->assertFalse(ManageNavigation::canAccess());
    }

    public function test_it_opens_with_the_standard_links(): void
    {
        Livewire::test(ManageNavigation::class)
            ->assertSuccessful()
            ->assertSee(['Pricing', 'Blog', 'Privacy Policy'])
            ->assertSchemaStateSet(['mobile.open_from' => 'top', 'mobile.show_logo' => true]);
    }

    public function test_saving_updates_the_menus(): void
    {
        $page = $this->page(['title' => 'Our rooms', 'slug' => 'rooms']);

        Livewire::test(ManageNavigation::class)
            ->fillForm([
                'header' => [
                    ['type' => 'page', 'page_id' => $page->id, 'label' => '', 'menu' => 'dropdown', 'children' => []],
                    ['type' => 'none', 'label' => 'More', 'menu' => 'dropdown', 'children' => [['type' => 'builtin', 'link' => 'blog']]],
                ],
                'mobile' => ['open_from' => 'right', 'show_logo' => true, 'tagline' => 'Book your hall'],
                'footer' => ['tagline' => 'Hello', 'copyright' => '', 'columns' => [['heading' => 'Visit', 'links' => [['type' => 'url', 'url' => '/find-us', 'label' => 'Find us']]]]],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Navigation saved');

        $menus = app(NavigationMenus::class);
        $this->assertSame(['Our rooms', 'More'], array_column($menus->header(), 'label'));
        $this->assertSame('Blog', $menus->header()[1]['children'][0]['label']);
        $this->assertSame(['open_from' => 'right', 'show_logo' => true, 'tagline' => 'Book your hall'], $menus->mobile());
        $this->assertSame('Find us', $menus->footer()['columns'][0]['links'][0]['label']);
    }

    public function test_links_are_checked_before_saving(): void
    {
        Livewire::test(ManageNavigation::class)
            ->fillForm([
                'header' => [
                    'bad' => ['type' => 'url', 'url' => 'javascript:alert(1)', 'label' => 'Bad', 'menu' => 'dropdown'],
                    'nolabel' => ['type' => 'url', 'url' => 'https://example.com', 'label' => '', 'menu' => 'dropdown'],
                    'empty' => ['type' => 'none', 'label' => 'Empty', 'menu' => 'dropdown', 'children' => []],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['header.bad.url' => 'regex', 'header.nolabel.label' => 'required', 'header.empty.children']);

        $this->assertSame(app(NavigationMenus::class)->standardHeader(), app(NavigationMenus::class)->headerItems());
    }

    public function test_draft_pages_are_marked_in_the_page_picker(): void
    {
        $this->page(['title' => 'Coming soon', 'slug' => 'soon', 'status' => PageStatus::Draft]);

        Livewire::test(ManageNavigation::class)
            ->fillForm(['header' => ['one' => ['type' => 'page', 'page_id' => null, 'menu' => 'dropdown']]])
            ->assertFormFieldExists('header.one.page_id', fn ($field): bool => in_array('Coming soon (draft, hidden until published)', $field->getOptions(), true));
    }

    public function test_reset_to_default_brings_back_the_standard_links(): void
    {
        app(NavigationMenus::class)->save([['type' => 'url', 'url' => '/x', 'label' => 'Custom']], NavigationMenus::defaultFooter(), ['open_from' => 'right']);

        Livewire::test(ManageNavigation::class)
            ->assertSee('Custom')
            ->callAction(TestAction::make('resetToDefault'))
            ->assertNotified('The menus are now this site\'s standard ones.')
            ->assertSchemaStateSet(['mobile.open_from' => 'top']);

        $this->assertSame(['Pricing', 'Blog'], array_column(app(NavigationMenus::class)->header(), 'label'));
    }
}
