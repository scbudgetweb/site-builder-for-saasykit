<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\BlockPresets;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;
use ScBudgetWeb\SiteBuilder\Blocks\DynamicContent;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Facades\SiteBuilder as SiteBuilderFacade;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Stage 5 of the visual builder: global colours and colours with transparency, block presets, and dynamic content.
 */
class PresetsColoursDynamicTest extends PageTestCase
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

        Gate::define('update settings', fn (User $user): bool => $user->getAttribute('email') === 'admin@example.com');
        config(['app.name' => 'Village Hall']);
    }

    public function test_block_colours_can_be_see_through_or_global(): void
    {
        $this->assertSame('#1d41d580', Colour::css('#1d41d580'));
        $this->assertSame('#1d41d5', Colour::css('#1d41d5'));
        $this->assertSame('var(--color-primary-500)', Colour::css('gc:primary'));
        $this->assertSame('var(--sb-gc-k3x9a2bc)', Colour::css('gc:k3x9a2bc'));

        foreach (['red', '#12345', 'gc:', 'gc:Bad-Id', 'gc:a) ; position: fixed', '#1d41d5; color: red', null, ['#fff']] as $bad) {
            $this->assertNull(Colour::css($bad), 'Rejected: '.json_encode($bad));
        }

        $this->assertSame('rgba(29, 65, 213, 0.5)', Colour::withOpacity('#1d41d5', 0.5));
        $this->assertSame('color-mix(in srgb, var(--sb-gc-k3x9a2bc) 25%, transparent)', Colour::withOpacity('gc:k3x9a2bc', 0.25));

        $shadow = BorderShadow::shadowStyle(['shadow' => 'custom', 'shadow_color' => 'gc:primary', 'shadow_opacity' => 40]);
        $this->assertStringContainsString('color-mix(in srgb, var(--color-primary-500) 40%, transparent)', $shadow);
    }

    public function test_global_colours_are_set_in_branding_and_followed_by_blocks(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());

        Livewire::test(ManageBranding::class)
            ->fillForm([
                'site_color' => '#0b1f44',
                'global_colours' => [
                    ['id' => 'sunrise1', 'name' => 'Sunrise orange', 'colour' => '#f97316'],
                    ['id' => 'mist0001', 'name' => 'Mist', 'colour' => '#ffffff80'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $branding = $this->app->make(Branding::class);
        $colours = $branding->globalColours();

        $this->assertSame(['primary', 'secondary', 'sunrise1', 'mist0001'], array_keys($colours));
        $this->assertSame(['name' => 'Site colour', 'colour' => '#0b1f44'], $colours['primary']);
        $this->assertNull($colours['secondary']['colour'], 'Not set: follows the theme.');
        $this->assertStringContainsString('--sb-gc-sunrise1: #f97316', $branding->siteCssVariables());
        $this->assertStringContainsString('--sb-gc-mist0001: #ffffff80', $branding->siteCssVariables());

        // Blocks save the reference, so a change in Branding changes them all.
        $output = BlockDesign::resolve(['typography' => ['heading' => ['colour' => 'gc:sunrise1']], 'borders' => ['border_width' => ['desktop' => ['top' => '2px']], 'border_color' => 'gc:mist0001']]);
        $this->assertStringContainsString('var(--sb-gc-sunrise1)', $output->style('section'));
        $this->assertStringContainsString('var(--sb-gc-mist0001)', $output->style('box'));

        // Contrast is worked out from the global colour's value: white text on the dark site colour.
        $this->assertTrue(BlockAppearance::resolve(['background' => 'custom', 'background_color' => 'gc:primary'])['isDark']);
        $this->assertSame('background-color: var(--color-primary-500);', BlockAppearance::resolve(['background' => 'custom', 'background_color' => 'gc:primary'])['style']);
        $this->assertFalse(BlockAppearance::resolve(['background' => 'custom', 'background_color' => 'gc:gone0000'])['isDark'], 'A deleted colour counts as light.');
    }

    public function test_a_blocks_colour_setting_offers_global_colours_and_checks_what_it_is_given(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $this->app->make(Branding::class)->save([Branding::GLOBAL_COLOURS => [['id' => 'sunrise1', 'name' => 'Sunrise orange', 'colour' => '#f97316']]]);
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Hi</p>'])]]);

        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertFormFieldExists('design.borders.border_color', 'mountedActionSchema0', fn (SiteColour $field): bool => collect($field->getGlobalColours())->pluck('id')->all() === ['primary', 'secondary', 'sunrise1']);

        $component->setActionData(['design' => ['borders' => ['border_color' => 'red; position: fixed']]])
            ->callMountedAction()
            ->assertHasActionErrors(['design.borders.border_color']);

        $component->setActionData(['design' => ['borders' => ['border_color' => 'gc:sunrise1', 'border_style' => 'solid']]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('gc:sunrise1', $component->instance()->draft()->fresh()->blocks[0]['data']['design']['borders']['border_color']);
    }

    public function test_a_custom_button_needs_only_a_background_and_can_use_a_global_colour(): void
    {
        $this->app->make(Branding::class)->save([Branding::SITE_SECONDARY_COLOR => '#0b1f44']);

        $look = PageBlock::buttonLook(['style' => 'custom', 'bg_color' => 'gc:secondary'], isDark: false);
        $this->assertStringContainsString('--site-button-bg: var(--color-secondary-500);', $look['style']);
        $this->assertStringContainsString('--site-button-text: #ffffff;', $look['style'], 'White reads on the dark secondary colour.');

        $this->assertStringContainsString('--site-button-text: #111827;', PageBlock::buttonLook(['style' => 'custom', 'bg_color' => '#fef3c7'], isDark: false)['style']);
        $this->assertSame('', PageBlock::buttonLook(['style' => 'custom'], isDark: false)['style'], 'No colours: the Branding look.');
    }

    public function test_a_button_switched_to_its_own_look_but_left_as_it_was_follows_the_block(): void
    {
        $blockLook = ['style' => 'custom', 'bg_color' => '#1e288a'];

        $this->assertFalse(PageBlock::hasOwnLook(['own_style' => true, 'style' => 'auto']));
        $this->assertSame('#1e288a', PageBlock::withLook(['label' => 'Hello!', 'own_style' => true, 'style' => 'auto'], $blockLook)['bg_color']);
        $this->assertTrue(PageBlock::hasOwnLook(['own_style' => true, 'style' => 'custom', 'bg_color' => '#ffffff']));
        $this->assertFalse(PageBlock::hasOwnLook(['own_style' => false, 'style' => 'custom']), 'Turned off: the block\'s look.');
    }

    public function test_the_canvas_menu_is_shielded_from_livewire_updates(): void
    {
        $view = (string) file_get_contents(__DIR__.'/../resources/views/filament/pages/visual-editor.blade.php');

        $this->assertMatchesRegularExpression('/<div wire:ignore>\s*<div\s+x-show="menu.open"/', $view, 'Livewire never touches the menu\'s own style, which hides it.');
        $this->assertStringContainsString('x-bind:style="{ left: menu.x + \'px\', top: menu.y + \'px\' }"', $view, 'An object: a string style would wipe x-show\'s display: none.');
    }

    public function test_a_preset_fills_in_what_a_block_leaves_as_it_is(): void
    {
        $presets = $this->app->make(BlockPresets::class);
        $navy = $presets->create('text', 'Navy panel', ['appearance' => ['background' => 'custom', 'background_color' => '#0b1f44']], isDefault: true);
        $cream = $presets->create('text', 'Cream', ['appearance' => ['background' => 'custom', 'background_color' => '#fef3c7']]);

        $this->assertSame([$navy => 'Navy panel', $cream => 'Cream'], $presets->names('text'));
        $this->assertSame($navy, $presets->defaultId('text'));
        $this->assertSame('none', TextBlock::fieldDefaults()['appearance.background'] ?? null, 'Defaults are known, so a setting saved with its default still takes the preset.');

        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Default preset</p>', 'appearance' => ['background' => 'none']]),
            self::block('text', ['content' => '<p>Picked preset</p>', 'preset' => $cream]),
            self::block('text', ['content' => '<p>No preset</p>', 'preset' => BlockPresets::NONE]),
            self::block('text', ['content' => '<p>Own colour</p>', 'appearance' => ['background' => 'custom', 'background_color' => '#123456']]),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'background-color: #0b1f44'), 'Only the block on the default preset.');
        $this->assertSame(1, substr_count($html, 'background-color: #fef3c7'));
        $this->assertStringContainsString('background-color: #123456', $html, 'The block\'s own setting wins.');

        // Changing a preset changes its blocks; deleting it puts them on the default.
        $presets->update('text', $cream, ['appearance' => ['background' => 'custom', 'background_color' => '#336699']]);
        $this->assertStringContainsString('background-color: #336699', $this->get('/about-us')->getContent());

        $presets->delete('text', $cream);
        $this->assertSame(2, substr_count($this->get('/about-us')->getContent(), 'background-color: #0b1f44'));
        $this->assertSame([], $presets->valuesFor('text', ['preset' => BlockPresets::NONE]));
    }

    public function test_filling_blanks_mixes_nested_settings_and_takes_lists_whole(): void
    {
        $filled = BlockPresets::fillBlanks(
            ['design' => ['spacing' => ['padding' => ['desktop' => ['top' => '10px']]]], 'appearance' => ['spacing' => 'medium', 'text' => ''], 'items' => [['a' => 1]]],
            ['design' => ['spacing' => ['padding' => ['desktop' => ['top' => '40px', 'bottom' => '40px']]]], 'appearance' => ['spacing' => 'large', 'text' => 'light'], 'items' => [['b' => 2]]],
            ['appearance.spacing' => 'medium'],
        );

        $this->assertSame(['top' => '10px', 'bottom' => '40px'], $filled['design']['spacing']['padding']['desktop']);
        $this->assertSame(['spacing' => 'large', 'text' => 'light'], $filled['appearance'], 'A default or blank setting takes the preset\'s.');
        $this->assertSame([['a' => 1]], $filled['items'], 'A list the block has is kept whole.');
    }

    public function test_the_panel_offers_presets_and_only_admins_manage_them(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $id = $this->app->make(BlockPresets::class)->create('text', 'Navy panel', []);
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Hi</p>'])]]);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertFormFieldExists('preset', 'mountedActionSchema0', fn ($field): bool => $field->getOptions() === [BlockPresets::NONE => 'No preset', $id => 'Navy panel']);

        $this->assertTrue(BlockPresets::canManage());
        $this->actingAs($this->user('editor@example.com'));
        $this->assertFalse(BlockPresets::canManage());
    }

    public function test_dynamic_content_is_filled_in_and_escaped(): void
    {
        config(['site-builder.pages.cache' => true]);
        SiteBuilderFacade::dynamicContent('venue_name', 'Venue name', fn (): string => 'Sometown <Village> Hall', isPerRequest: false);

        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Welcome to {{ site_name }}, {{ user_name | friend }}. {{ page_title }} © {{current_year}} {{ venue_name }} {{ not_a_tag }}</p>']),
            self::block('text', ['content' => '<p>Merge tag: <span data-type="mergeTag" data-id="site_name"></span></p>']),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();
        $this->assertStringContainsString('Welcome to Village Hall, friend. About us © '.now()->format('Y').' Sometown &lt;Village&gt; Hall {{ not_a_tag }}', $html);
        $this->assertStringContainsString('Merge tag: <span data-type="mergeTag" data-id="site_name">Village Hall</span>', $html);

        // Per visitor, even on a cached page; a value's braces never make another tag.
        $visitor = $this->user();
        $visitor->forceFill(['name' => '{{ site_name }}']);
        $this->actingAs($visitor);
        $this->assertStringContainsString('Welcome to Village Hall, &#123;&#123; site_name &#125;&#125;.', $this->get('/about-us')->getContent());

        $this->assertTrue(DynamicContent::isPerRequest(['heading' => 'Hi {{ user_name }}']));
        $this->assertFalse(DynamicContent::isPerRequest(['heading' => '{{ site_name }} and {{ venue_name }}']));
        $this->assertArrayHasKey('venue_name', DynamicContent::labels());
    }

    public function test_rich_text_offers_dynamic_content_as_merge_tags(): void
    {
        $editor = (new \ReflectionMethod(PageBlock::class, 'richText'))->invoke(null, 'content', 'Text');

        $this->assertSame(DynamicContent::labels(), $editor->getMergeTags());
    }
}
