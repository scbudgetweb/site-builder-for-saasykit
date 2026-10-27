<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Tabs;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\CreatePage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\ListPages;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;

class PageEditorTest extends PageTestCase
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
        $this->actingAs($this->user());
    }

    public function test_pages_sit_in_the_website_group_for_people_with_the_permission(): void
    {
        $this->assertTrue(PageResource::canViewAny());
        $this->assertSame('Website', PageResource::getNavigationGroup());

        $this->actingAs($this->user('someone@example.com'));
        $this->assertFalse(PageResource::canViewAny());
        $this->assertFalse(PageResource::canCreate());
    }

    public function test_the_list_shows_pages_with_their_address(): void
    {
        $page = $this->page();
        $home = $this->page(['title' => 'Home', 'slug' => 'home-page', 'replaces' => 'home']);

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords([$page, $home])
            ->assertSee('/about-us')
            ->assertSee('Home page');
    }

    public function test_creating_a_page_fills_the_url_from_the_title_and_opens_the_editor(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Our Story'])
            ->assertSchemaStateSet(['slug' => 'our-story'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(PageResource::getUrl('edit', ['record' => Page::query()->sole()]));

        $page = Page::query()->sole();
        $this->assertSame('our-story', $page->slug);
        $this->assertSame(PageStatus::Draft, $page->status);
    }

    public function test_the_url_must_be_free_and_well_formed(): void
    {
        $this->page(['slug' => 'taken']);

        foreach (['blog' => 'This URL is already used', 'taken' => 'Another page already uses this URL', 'Not OK!' => 'Use lower-case letters'] as $slug => $message) {
            Livewire::test(CreatePage::class)
                ->fillForm(['title' => 'A page', 'slug' => $slug])
                ->call('create')
                ->assertHasFormErrors(['slug'])
                ->assertSee($message);
        }
    }

    public function test_a_problem_inside_a_block_is_named_in_a_notification(): void
    {
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['blocks' => [
                'first' => self::block('text', ['content' => '<p>Fine</p>']),
                'second' => self::block('call_to_action', ['heading' => '']),
            ]])
            ->call('save')
            ->assertNotified('The page wasn\'t saved');

        $this->assertSame([], $page->fresh()->blocks);
    }

    public function test_blocks_are_saved_in_order(): void
    {
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['blocks' => [
                'first' => self::block('banner', ['heading' => 'Welcome']),
                'second' => self::block('text', ['content' => '<p>About the hall</p>']),
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $blocks = $page->fresh()->blockList();
        $this->assertSame(['banner', 'text'], array_column($blocks, 'type'));
        $this->assertSame('Welcome', $blocks[0]['data']['heading']);
        $this->assertArrayNotHasKey('preview_label', $blocks[0]['data']);
    }

    public function test_replacing_a_built_in_page_that_needs_a_block_warns_when_it_is_missing(): void
    {
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['replaces' => 'contact'])
            ->assertSee('needs the &quot;Call to action&quot; block', false)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('This page is missing a block');

        $this->assertSame('contact', $page->fresh()->replaces);
    }

    public function test_a_page_in_the_bin_keeps_its_status_and_cant_take_a_built_in_pages_place(): void
    {
        $page = $this->page(['status' => PageStatus::Draft]);
        $page->delete();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertFormFieldIsDisabled('status')
            ->assertFormFieldIsDisabled('replaces')
            ->fillForm(['title' => 'Renamed in the bin', 'status' => PageStatus::Published->value, 'replaces' => 'home'])
            ->call('save')
            ->assertHasNoFormErrors();

        $page = Page::withTrashed()->find($page->getKey());
        $this->assertSame('Renamed in the bin', $page->title);
        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertNull($page->replaces);
    }

    public function test_duplicating_a_page_picks_a_url_no_other_page_uses(): void
    {
        $page = $this->page();
        $this->page(['title' => 'Old copy', 'slug' => 'about-us-copy-abcd'])->delete();

        // Only the four-character copy suffix is faked; other random strings (cache versions) stay random.
        $suffixes = ['abcd', 'efgh'];
        Str::createRandomStringsUsing(function (int $length) use (&$suffixes): string {
            return $length === 4 && $suffixes !== [] ? array_shift($suffixes) : substr(bin2hex(random_bytes($length)), 0, $length);
        });

        try {
            Livewire::test(ListPages::class)->callAction(TestAction::make('duplicate')->table($page));
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertTrue(Page::query()->where('slug', 'about-us-copy-efgh')->where('status', PageStatus::Draft)->exists());
    }

    public function test_only_one_page_can_replace_a_built_in_page(): void
    {
        $this->page(['slug' => 'first-home', 'replaces' => 'home']);
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['replaces' => 'home'])
            ->call('save')
            ->assertHasFormErrors(['replaces' => 'unique']);
    }

    public function test_save_page_in_a_block_panel_keeps_the_panels_changes(): void
    {
        $page = $this->page(['blocks' => ['item-1' => self::block('text', ['content' => '<p>Old</p>'])]]);

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $key = array_key_first($component->get('data.blocks'));

        $component
            ->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $key]))
            ->set('mountedActions.0.data.content', '<p>New</p>')
            ->call('saveFromEditor')
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('New', $page->fresh()->blockList()[0]['data']['content']);
    }

    public function test_each_block_only_offers_the_style_settings_that_apply_to_it(): void
    {
        $page = $this->page(['blocks' => [
            'text' => self::block('text', ['content' => '<p>Hi</p>']),
            'cta' => self::block('call_to_action', ['heading' => 'Book']),
            'image' => self::block('image_text', ['heading' => 'Hall']),
            'banner' => self::block('banner', ['heading' => 'Welcome']),
        ]]);

        $panel = function (string $type) use ($page) {
            $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
            $item = collect($component->get('data.blocks'))->search(fn (array $block): bool => $block['type'] === $type);

            return $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $item]));
        };

        // Text has body text but no heading.
        $panel('text')
            ->assertFormFieldExists('appearance.text_size')
            ->assertFormFieldExists('appearance.spacing')
            ->assertFormFieldDoesNotExist('appearance.heading_decoration')
            ->assertFormFieldDoesNotExist('appearance.heading_size')
            ->assertFormFieldDoesNotExist('appearance.heading_align');
        // Call to action: its heading can be resized, but it isn't a decorated section heading.
        $panel('call_to_action')
            ->assertFormFieldExists('appearance.heading_size')
            ->assertFormFieldDoesNotExist('appearance.heading_decoration')
            ->assertFormFieldDoesNotExist('appearance.heading_align');
        $panel('image_text')
            ->assertFormFieldExists('appearance.heading_decoration')
            ->assertFormFieldExists('appearance.heading_size')
            ->assertFormFieldExists('image_corners')
            ->assertFormFieldDoesNotExist('appearance.heading_align');
        // The Banner draws its own background, so it has none of the shared settings.
        $panel('banner')
            ->assertFormFieldDoesNotExist('appearance.spacing')
            ->assertFormFieldExists('heading_size')
            ->assertFormFieldExists('css_class');
    }

    public function test_block_panels_put_layout_before_style(): void
    {
        $block = SectionBlock::make();
        $tabs = collect($block->getDefaultChildComponents())->first(fn ($component): bool => $component instanceof Tabs);

        $this->assertSame(['Rows', 'Layout', 'Style', 'Advanced'], array_map(fn ($tab): string => (string) $tab->getLabel(), $tabs->getDefaultChildComponents()));
    }

    public function test_every_blocks_panel_opens(): void
    {
        $registry = app(BlockRegistry::class);
        $page = $this->page(['blocks' => array_map(fn (string $type): array => self::block($type), array_keys($registry->all()))]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        foreach ($component->get('data.blocks') as $key => $item) {
            $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => $key]));

            $registry->find($item['type'])::hasAppearance()
                ? $component->assertFormFieldExists('appearance.spacing')
                : $component->assertFormFieldDoesNotExist('appearance.spacing');

            $component->unmountAction();
        }
    }

    public function test_card_switches_start_on_for_a_block_saved_without_card_settings(): void
    {
        // A Toggle with no saved value would hydrate as off and remove the cards on the next save (lesson 3).
        $page = $this->page(['blocks' => [self::block('features', ['items' => [['title' => 'Wifi']]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component
            ->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]))
            ->assertSet('mountedActions.0.data.card.background', true)
            ->assertSet('mountedActions.0.data.card.border', true);
    }

    public function test_style_settings_from_a_block_panel_are_saved_under_appearance(): void
    {
        $page = $this->page(['blocks' => ['cta' => self::block('call_to_action', ['heading' => 'Book'])]]);

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component
            ->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]))
            ->set('mountedActions.0.data.appearance.background', 'gradient')
            ->set('mountedActions.0.data.appearance.gradient_start', '#000000')
            ->set('mountedActions.0.data.appearance.gradient_end', '#1e3a8a')
            ->set('mountedActions.0.data.appearance.background_area', 'panel')
            ->call('saveFromEditor')
            ->assertHasNoFormErrors();

        $appearance = $page->fresh()->blockList()[0]['data']['appearance'];
        $this->assertSame('gradient', $appearance['background']);
        $this->assertSame('#1e3a8a', $appearance['gradient_end']);
        $this->assertSame('panel', $appearance['background_area']);
    }

    public function test_custom_classes_cant_pin_a_block_over_the_page(): void
    {
        $page = $this->page(['blocks' => ['cta' => self::block('call_to_action', ['heading' => 'Book'])]]);

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component
            ->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]))
            ->set('mountedActions.0.data.appearance.css_class', 'promo fixed')
            ->call('saveFromEditor')
            ->assertHasErrors(['mountedActions.0.data.appearance.css_class' => 'not_regex']);
    }

    public function test_duplicating_makes_a_draft_copy_that_replaces_nothing(): void
    {
        $page = $this->page(['replaces' => 'home']);

        Livewire::test(ListPages::class)
            ->callAction(TestAction::make('duplicate')->table($page))
            ->assertNotified('Page duplicated as a draft');

        $copy = Page::query()->whereKeyNot($page->getKey())->sole();
        $this->assertSame('About us (copy)', $copy->title);
        $this->assertStringStartsWith('about-us-copy-', $copy->slug);
        $this->assertSame(PageStatus::Draft, $copy->status);
        $this->assertNull($copy->replaces);
    }
}
