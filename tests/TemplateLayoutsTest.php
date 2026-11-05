<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageNavigation;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\CreateTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\ListTemplates;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\StandardHeaderAndFooter;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;
use ScBudgetWeb\SiteBuilder\Templates\TemplateLayouts;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * Ready-made headers and footers: the Top bar block, the layouts a new template starts from (picked with pictures),
 * Branding's header and footer colours as a background, the standard header and footer in the Theme builder list
 * (with Customise), and Navigation's and Branding's notices when a template is shown instead.
 */
class TemplateLayoutsTest extends PageTestCase
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

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::define('update settings', fn (): bool => true);
    }

    public function test_the_top_bar_shows_a_line_contact_details_and_social_icons(): void
    {
        config(['app.social_links.facebook' => 'https://facebook.com/villagehall']);
        $registry = app(BlockRegistry::class);
        $this->assertArrayNotHasKey('site_top_bar', $registry->offered());
        $this->assertArrayHasKey('site_top_bar', $registry->editingTemplate('header')->offered());
        $this->assertArrayNotHasKey('site_top_bar', $registry->editingTemplate('footer')->offered());

        $this->page(['blocks' => [self::block('site_top_bar', ['text' => 'Open every day', 'phone' => '01234 567 890', 'email' => 'hello@example.com'])]]);
        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertStringContainsString('bg-primary-900', $html, 'On the site colour, dark, as standard.');
        $this->assertStringContainsString('<span>Open every day</span>', $html);
        $this->assertStringContainsString('href="tel:01234567890"', $html);
        $this->assertStringContainsString('href="mailto:hello@example.com"', $html);
        $this->assertStringContainsString('href="https://facebook.com/villagehall"', $html);
        $this->assertStringContainsString('justify-between', $html);
        $this->assertStringNotContainsString('hidden sm:block', $html);

        $this->page(['slug' => 'centred', 'blocks' => [self::block('site_top_bar', ['email' => 'not an email', 'show_social' => false, 'layout' => 'centre', 'hide_on_phones' => true])]]);
        $html = $this->get('/centred')->getContent();
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('facebook.com', $html);
        $this->assertStringContainsString('justify-center text-center', $html);
        $this->assertStringContainsString('sb-top-bar text-sm hidden sm:block', $html);
    }

    public function test_branding_header_and_footer_colours_are_a_background_in_templates(): void
    {
        $footer = BlockAppearance::resolve(['background' => 'footer']);
        $this->assertStringContainsString('bg-sb-footer-bg', $footer['class']);
        $this->assertStringContainsString('text-sb-footer-text', $footer['class']);
        $this->assertTrue($footer['isDark'], 'The standard footer is dark grey.');

        $header = BlockAppearance::resolve(['background' => 'header']);
        $this->assertStringContainsString('bg-sb-header-bg', $header['class']);
        $this->assertStringContainsString('text-sb-header-text', $header['class']);
        $this->assertFalse($header['isDark'], 'The standard header is white.');

        app(Branding::class)->save([Branding::FOOTER_BG_COLOR => '#fef3c7']);
        $this->assertFalse(BlockAppearance::resolve(['background' => 'footer'])['isDark'], 'A pale footer colour takes dark headings.');
        $this->assertStringContainsString('text-white', BlockAppearance::resolve(['background' => 'footer', 'text' => 'light'])['class'], 'Text colour still wins.');

        $this->assertSame('bg-sb-footer-bg text-sb-footer-text', BlockAppearance::backgroundLook(['background' => 'footer'])['class'], 'Rows and columns too.');

        $options = fn (): array => BlockAppearance::backgroundFields()[0]->getOptions();
        $this->assertArrayNotHasKey('footer', $options(), 'Not offered on pages.');
        app(BlockRegistry::class)->editingTemplate('footer');
        $this->assertArrayHasKey('footer', $options());
        $this->assertArrayHasKey('header', $options());
    }

    public function test_every_layout_renders(): void
    {
        $menus = app(NavigationMenus::class);
        $menus->save($menus->headerItems(), ['tagline' => 'Your village hall', 'copyright' => '', 'columns' => [['heading' => 'Visit', 'links' => [['type' => 'url', 'url' => '/find-us', 'label' => 'Find us']]]]], NavigationMenus::defaultMobile());

        foreach (['header', 'footer'] as $type) {
            foreach (array_keys(TemplateLayouts::options($type)) as $layout) {
                $blocks = TemplateLayouts::blocks($type, $layout);
                $slug = "{$type}-".str_replace('_', '-', $layout);
                $this->page(['slug' => $slug, 'show_page_banner' => false, 'blocks' => $blocks]);

                $html = $this->get('/'.$slug)->assertSuccessful()->getContent();

                if ($layout === TemplateLayouts::BLANK) {
                    $this->assertSame([], $blocks);
                } elseif ($type === 'footer') {
                    $this->assertStringContainsString('Example Site', $html, "{$layout}: the copyright line or logo.");
                }
            }
        }

        $standard = TemplateLayouts::blocks('footer', 'standard');
        $this->assertSame('footer', $standard[0]['data']['appearance']['background'], 'On the Branding footer colours.');
        $this->assertSame('1-2', $standard[0]['data']['rows'][0]['layout'], 'The logo beside the link columns.');
        $this->assertStringContainsString('Your village hall', $this->get('/footer-standard')->getContent(), 'The tagline, as it is now.');
        $this->assertStringContainsString('Find us', $this->get('/footer-columns')->getContent());

        $menus->save($menus->headerItems(), ['tagline' => '', 'copyright' => '', 'columns' => []], NavigationMenus::defaultMobile());
        $this->assertSame('stacked', TemplateLayouts::blocks('footer', 'standard')[0]['data']['rows'][0]['layout'], 'No link columns: the logo on its own.');

        $this->assertSame(['site_top_bar', 'site_header'], array_column(TemplateLayouts::blocks('header', 'top_bar'), 'type'));
        $this->assertSame('split', TemplateLayouts::blocks('header', 'split')[0]['data']['style']);
        $this->assertSame([], TemplateLayouts::blocks('blog_post', 'standard'));
    }

    public function test_a_new_header_or_footer_starts_from_the_layout_picked(): void
    {
        $this->actingAs($this->user());

        Livewire::test(CreateTemplate::class)
            ->assertSchemaStateSet(['layout' => TemplateLayouts::STANDARD])
            ->assertSee('The standard header')
            ->fillForm(['name' => 'Shop footer', 'type' => 'footer', 'applies_to' => Template::EVERYWHERE, 'layout' => 'simple'])
            ->call('create')
            ->assertHasNoFormErrors();

        $footer = Template::query()->where('name', 'Shop footer')->sole();
        $this->assertSame(['section'], array_column($footer->blocks, 'type'));
        $this->assertSame('small', $footer->blocks[0]['data']['appearance']['spacing']);
        $this->assertArrayNotHasKey('layout', $footer->getAttributes());

        Livewire::test(CreateTemplate::class)
            ->fillForm(['name' => 'Empty header', 'type' => 'header', 'applies_to' => Template::EVERYWHERE, 'layout' => TemplateLayouts::BLANK])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame([], Template::query()->where('name', 'Empty header')->sole()->blocks);

        Livewire::test(CreateTemplate::class)
            ->fillForm(['name' => 'Odd header', 'type' => 'header', 'applies_to' => Template::EVERYWHERE, 'layout' => 'diagonal'])
            ->call('create')
            ->assertHasFormErrors(['layout']);
    }

    public function test_the_theme_builder_lists_the_standard_header_and_footer(): void
    {
        $this->actingAs($this->user());

        Livewire::test(ListTemplates::class)
            ->assertSee('Standard header')
            ->assertSee('Standard footer')
            ->assertSee('Shown on every page.')
            ->assertSee(StandardHeaderAndFooter::linksUrl('footer'), false)
            ->assertSee(StandardHeaderAndFooter::coloursUrl(), false);

        $this->assertStringContainsString('navigation-tab=footer', StandardHeaderAndFooter::linksUrl('footer'));
        $this->assertStringContainsString('branding-tab=header-footer', StandardHeaderAndFooter::coloursUrl());

        Template::query()->create(['name' => 'Main header', 'type' => 'header', 'blocks' => []]);
        Template::query()->create(['name' => 'Shop footer', 'type' => 'footer', 'applies_to' => Template::ONLY, 'page_ids' => [1], 'blocks' => []]);
        Template::query()->create(['name' => 'Draft footer', 'type' => 'footer', 'is_active' => false, 'blocks' => []]);

        $this->assertSame('Not shown: the template "Main header" is shown on every page instead.', StandardHeaderAndFooter::status('header'));
        $this->assertSame('Shown everywhere except where a template applies ("Shop footer").', StandardHeaderAndFooter::status('footer'));
    }

    public function test_customise_makes_a_template_from_the_standard_one_that_starts_off(): void
    {
        $this->actingAs($this->user());
        app(Branding::class)->save([Branding::HEADER_BEHAVIOUR => ['sticky' => 'always']]);

        Livewire::test(ListTemplates::class)
            ->callAction(TestAction::make('headerCustomise')->schemaComponent('headerActions', 'content'))
            ->assertHasNoActionErrors();

        $header = Template::query()->sole();
        $this->assertSame('header', $header->type);
        $this->assertFalse($header->is_active, 'Visitors keep seeing the standard header until it\'s turned on.');
        $this->assertSame(Template::EVERYWHERE, $header->applies_to);
        $this->assertSame(['site_header'], array_column($header->blocks, 'type'));
        $this->assertSame('always', $header->settings['sticky'], 'It behaves as the standard header does.');
        $this->assertNull(app(Templates::class)->for('header'));

        Livewire::test(ListTemplates::class)
            ->callAction(TestAction::make('footerCustomise')->schemaComponent('footerActions', 'content'))
            ->assertRedirect(TemplateResource::getUrl('visual', ['record' => Template::query()->where('type', 'footer')->sole()]));
        $this->assertSame(['section'], array_column(Template::query()->where('type', 'footer')->sole()->blocks, 'type'));
    }

    public function test_the_card_edits_the_template_shown_on_every_page_instead_of_customising_again(): void
    {
        $this->actingAs($this->user());

        Livewire::test(ListTemplates::class)
            ->assertActionVisible(TestAction::make('headerCustomise')->schemaComponent('headerActions', 'content'))
            ->assertDontSee('Edit &quot;', false);

        Template::query()->create(['name' => 'Shop header', 'type' => 'header', 'applies_to' => Template::ONLY, 'page_ids' => [1], 'blocks' => []]);

        Livewire::test(ListTemplates::class)
            ->assertActionVisible(TestAction::make('headerCustomise')->schemaComponent('headerActions', 'content'))
            ->assertDontSee('Edit &quot;', false);

        Template::query()->create(['name' => 'Draft header', 'type' => 'header', 'is_active' => false, 'blocks' => []]);
        $main = Template::query()->create(['name' => 'Main header', 'type' => 'header', 'blocks' => []]);

        $list = Livewire::test(ListTemplates::class);
        $headerCard = Str::between($list->html(), 'Standard header', 'Standard footer');
        $this->assertStringContainsString('Edit &quot;Main header&quot;', $headerCard);
        $this->assertStringNotContainsString('Customise', $headerCard, 'No second header is offered.');

        $list
            ->assertActionVisible(TestAction::make('headerEditInUse')->schemaComponent('headerActions', 'content'))
            ->assertActionHasLabel(TestAction::make('headerEditInUse')->schemaComponent('headerActions', 'content'), 'Edit "Main header"')
            ->assertActionHasUrl(TestAction::make('headerEditInUse')->schemaComponent('headerActions', 'content'), TemplateResource::getUrl('visual', ['record' => $main]))
            ->assertActionVisible(TestAction::make('footerCustomise')->schemaComponent('footerActions', 'content'));

        $this->assertSame(3, Template::query()->count(), 'Nothing new was made.');
    }

    public function test_navigation_and_branding_say_when_a_template_is_shown_instead(): void
    {
        $this->actingAs($this->user());

        Livewire::test(ManageNavigation::class)->assertDontSee('is shown on every page instead of the standard header');
        $this->assertNull(StandardHeaderAndFooter::notice('header', 'navigation'));

        $template = Template::query()->create(['name' => 'Main header', 'type' => 'header', 'blocks' => []]);

        Livewire::test(ManageNavigation::class)
            ->assertSee('The header template "Main header" is shown on every page instead of the standard header.')
            ->assertSee('still show the links set here')
            ->assertSee(TemplateResource::getUrl('visual', ['record' => $template]), false);

        Livewire::test(ManageBranding::class)
            ->assertSee('The header template "Main header" is shown on every page instead of the standard header.')
            ->assertSee('under Header settings in its visual builder');

        Template::query()->create(['name' => 'Shop footer', 'type' => 'footer', 'applies_to' => Template::ONLY, 'page_ids' => [1], 'blocks' => []]);
        $this->assertSame(
            'Footer templates are shown on some pages ("Shop footer"); the standard footer shows everywhere else.',
            StandardHeaderAndFooter::notice('footer', 'navigation')?->getHeading(),
        );
    }
}
