<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\CreateTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\EditTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\TemplateVisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\SiteBuilderServiceProvider;
use ScBudgetWeb\SiteBuilder\Templates\TemplateRenderer;
use ScBudgetWeb\SiteBuilder\Templates\Templates;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blocks\VisitorGreetingBlock;

/**
 * The Theme Builder: which template applies where, cached template HTML with per-visitor holes, the theme using a
 * template in place of its standard footer, and the admin screens.
 */
class TemplatesTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    private function template(array $attributes = []): Template
    {
        return Template::query()->create([
            'name' => 'Main footer',
            'type' => 'footer',
            'blocks' => [self::block('text', ['content' => '<p>Village Hall footer</p>'])],
            ...$attributes,
        ]);
    }

    public function test_the_most_specific_active_template_applies(): void
    {
        $templates = app(Templates::class);
        $about = $this->page();
        $contact = $this->page(['slug' => 'contact']);

        $this->assertNull($templates->for('footer', $about));

        $everywhere = $this->template();
        Carbon::setTestNow(now()->addMinute());
        $this->template(['is_active' => false]);
        $exceptAbout = $this->template(['applies_to' => Template::EXCEPT, 'page_ids' => [$about->id]]);
        $onlyContact = $this->template(['applies_to' => Template::ONLY, 'page_ids' => [$contact->id], 'updated_at' => now()->subYear()]);
        Carbon::setTestNow();

        $this->assertSame($everywhere->id, $templates->for('footer', $about)['id'], 'Off templates and ones excluding this page don\'t apply.');
        $this->assertSame($onlyContact->id, $templates->for('footer', $contact)['id'], 'One for chosen pages wins, however old.');
        $this->assertSame($exceptAbout->id, $templates->for('footer')['id'], 'Elsewhere (no page), the most recently saved.');
        $this->assertNull($templates->for('header', $about), 'Each type on its own.');

        $exceptAbout->delete();
        $this->assertSame($everywhere->id, $templates->for('footer')['id'], 'The cached list follows changes.');
    }

    public function test_the_theme_uses_a_template_in_place_of_its_standard_footer(): void
    {
        $this->app['view']->getFinder()->prependLocation(SiteBuilderServiceProvider::themePath());

        $this->assertStringNotContainsString('Village Hall footer', Blade::render('<x-layouts.app.footer />'));

        $this->template();

        $footer = Blade::render('<x-layouts.app.footer />');
        $this->assertStringContainsString('Village Hall footer', $footer);
        $this->assertStringStartsWith('<footer', trim($footer));

        // A page's own canvas hides the header and footer.
        request()->attributes->set(Templates::HIDE_CHROME_ATTRIBUTE, true);
        $this->assertStringNotContainsString('Village Hall footer', Blade::render('<x-layouts.app.footer />'));
    }

    public function test_a_template_is_cached_and_a_block_for_the_visitor_is_rendered_each_time(): void
    {
        app(SiteBuilder::class)->blocks([VisitorGreetingBlock::class]);
        $template = $this->template(['blocks' => [
            self::block('text', ['content' => '<p>Cached part</p>']),
            self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::block('visitor_greeting')]]]]),
        ]]);
        $found = app(Templates::class)->for('footer');

        $this->assertStringContainsString('Hello, guest', (string) app(Templates::class)->render('footer'));

        // The template row changes behind the cache's back: the cached part stays, the greeting is fresh.
        DB::table('site_builder_templates')->where('id', $template->id)->update(['blocks' => json_encode([self::block('text', ['content' => '<p>Changed</p>'])])]);
        $this->actingAs($this->user());

        DB::enableQueryLog();
        $html = (string) app(TemplateRenderer::class)->render($found['id'], $found['updated_at']);
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'site_builder_templates'));

        $this->assertStringContainsString('Cached part', $html);
        $this->assertStringContainsString('Hello, Test', $html);
        $this->assertStringNotContainsString('Changed', $html);
        $this->assertCount(0, $queries, 'The template isn\'t loaded again.');
    }

    public function test_without_the_templates_table_the_standard_header_and_footer_show(): void
    {
        // As just after updating the package, before migrating.
        Schema::rename('site_builder_templates', 'site_builder_templates_not_yet');

        $this->assertSame([], app(Templates::class)->active());
        $this->assertNull(app(Templates::class)->render('footer'));
    }

    public function test_a_broken_template_never_takes_the_page_down(): void
    {
        $this->template(['blocks' => [self::block('section', ['rows' => 'not rows'])]]);
        config(['app.debug' => false]);

        $this->assertNotNull(app(Templates::class)->render('footer'));
    }

    public function test_template_blocks_are_offered_only_where_they_fit_and_apps_add_types(): void
    {
        app(SiteBuilder::class)->templateTypes(['job' => 'Job page']);

        $this->assertSame(['header' => 'Header', 'footer' => 'Footer', 'job' => 'Job page'], app(Templates::class)->types());
        $this->assertSame([], array_filter(app(BlockRegistry::class)->offered(), fn (string $block): bool => $block::templateTypes() !== null), 'A page offers no template blocks.');

        $this->expectException(\InvalidArgumentException::class);
        app(SiteBuilder::class)->templateTypes(['Not a key' => 'x']);
    }

    public function test_admins_make_a_template_and_build_it_in_the_visual_builder(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());

        $this->get(TemplateResource::getUrl('index', panel: 'admin'))->assertSuccessful()->assertSee(__('No templates yet'));

        Livewire::test(CreateTemplate::class)
            ->fillForm(['name' => 'Main header', 'type' => 'header', 'applies_to' => Template::EVERYWHERE])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(TemplateResource::getUrl('visual', ['record' => Template::query()->sole()]));

        $template = Template::query()->sole();

        $editor = Livewire::test(TemplateVisualEditor::class, ['record' => $template->getRouteKey()]);
        $this->assertSame('header', app(BlockRegistry::class)->editingTemplateType(), 'Its pickers offer header blocks.');

        $editor->callAction(TestAction::make('addBlock'), ['type' => 'text'])->assertSet('mountedActions.0.name', 'editBlock');
        $this->assertCount(1, app(VisualDrafts::class)->open($template, $this->user())->blocks);

        $editor = Livewire::test(TemplateVisualEditor::class, ['record' => $template->getRouteKey()]);
        $this->assertTrue($editor->instance()->getAction('publish')->isConfirmationRequired(), 'It\'s on, so Save says visitors see it straight away.');
        $editor->callAction('publish')->assertNotified('Template saved');
        $this->assertCount(1, $template->fresh()->blocks);

        $this->get(app(VisualDrafts::class)->open($template->fresh(), $this->user())->canvasUrl())->assertSuccessful()->assertSee('data-sb-node="block"', false);

        Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])->assertFormFieldIsDisabled('type');

        $this->actingAs($this->user('someone@example.com'));
        $this->withExceptionHandling()->get(TemplateResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }
}
