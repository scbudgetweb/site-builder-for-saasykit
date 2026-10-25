<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Facades\SiteBuilder as SiteBuilderFacade;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Legal\LegalPage;
use ScBudgetWeb\SiteBuilder\Navigation\Link;
use ScBudgetWeb\SiteBuilder\Pages\Slot;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blocks\OpeningHoursBlock;

/**
 * What a host app can add through the SiteBuilder facade (HB-97), with the example host block.
 */
class ExtensionApiTest extends PageTestCase
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

        SiteBuilderFacade::blocks([OpeningHoursBlock::class]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::define('update settings', fn ($user): bool => $user->getAttribute('email') === 'admin@example.com');
        $this->actingAs($this->user());
    }

    public function test_a_host_block_joins_the_picker_and_sections_and_renders_from_the_apps_view(): void
    {
        $registry = app(BlockRegistry::class);

        $this->assertSame(OpeningHoursBlock::class, $registry->find('opening_hours'));
        $this->assertSame('opening_hours', array_key_last($registry->all()));
        $this->assertContains('opening_hours', array_map(fn ($block) => $block->getName(), $registry->sectionBuilderBlocks()));

        $this->page(['blocks' => [
            self::block('opening_hours', ['heading' => 'When we\'re open', 'hours' => [['day' => 'Monday', 'times' => '9am–5pm'], 'broken', ['day' => ['x']]], 'appearance' => ['background' => 'light']]),
            self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::block('opening_hours', ['hours' => [['day' => 'Sunday', 'times' => 'Closed']]])]]]]),
        ]]);

        $this->get('/about-us')
            ->assertSuccessful()
            ->assertSeeInOrder(['When we\'re open', 'Monday', '9am–5pm', 'Sunday', 'Closed'])
            ->assertSee('class="opening-hours', false);
    }

    public function test_a_host_block_gets_the_shared_editor_tabs_and_validation(): void
    {
        $page = $this->page(['blocks' => [self::block('opening_hours', ['hours' => [['day' => 'Monday', 'times' => '9–5']]])]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component
            ->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]))
            ->assertFormFieldExists('hours')
            ->assertFormFieldExists('appearance.background')
            ->assertFormFieldExists('appearance.anchor');

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['blocks' => ['one' => self::block('opening_hours', ['hours' => []])]])
            ->call('save')
            ->assertNotified('The page wasn\'t saved');
    }

    public function test_only_page_blocks_can_be_registered_and_a_host_block_can_replace_a_core_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            SiteBuilderFacade::blocks([ExtensionApiTest::class]);
        } finally {
            $replacement = new class extends TextBlock
            {
                public static function label(): string
                {
                    return 'Our text';
                }
            };
            SiteBuilderFacade::blocks([$replacement::class]);
            $this->assertSame('Our text', app(BlockRegistry::class)->labelFor('text'));
        }
    }

    public function test_host_branding_colours_appear_on_branding_set_css_variables_and_warn_about_contrast(): void
    {
        SiteBuilderFacade::brandingColours('Booking widget', [
            'booking_bg' => ['Background colour', '--color-booking-bg'],
            'booking_text' => ['Text colour', ['--color-booking-text', '--color-booking-icon']],
        ], ['booking_text' => 'booking_bg']);

        Livewire::test(ManageBranding::class)
            ->assertSee('Booking widget')
            ->assertFormFieldExists('app_booking_bg')
            ->fillForm(['app_booking_bg' => '#ffffff', 'app_booking_text' => '#eeeeee'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Some colours may be hard to read');

        $css = app(Branding::class)->siteCssVariables();
        $this->assertStringContainsString('--color-booking-bg: #ffffff', $css);
        $this->assertStringContainsString('--color-booking-text: #eeeeee', $css);
        $this->assertStringContainsString('--color-booking-icon: #eeeeee', $css);
    }

    public function test_branding_colours_must_have_safe_keys_and_variables(): void
    {
        foreach ([
            [['bad key' => ['Label', '--ok']], []],
            [['ok' => ['Label', 'color: red; }']], []],
            [['text' => ['Text', '--text'], 'bg' => ['Background', '--bg']], ['text' => 'missing']],
        ] as [$colours, $pairs]) {
            try {
                SiteBuilderFacade::brandingColours('Bad', $colours, $pairs);
                $this->fail('Expected an exception.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame([], app(SiteBuilder::class)->registeredBrandingColours());
    }

    public function test_the_facade_reaches_every_extension_point(): void
    {
        SiteBuilderFacade::slots([Slot::make('contact', 'Contact', fn (): string => url('/contact'))])
            ->links([Link::make('halls', 'Find a hall', fn (): string => url('/halls'))])
            ->stats(['halls' => ['Halls', fn (): int => 12]])
            ->legalPages([LegalPage::make('cookie_policy', 'Cookie Policy', fn (): string => url('/cookies'))])
            ->consentChecker(fn (string $category): bool => $category === 'marketing');

        $this->assertSame('Contact', SiteBuilderFacade::slot('contact')?->label());
        $this->assertSame('Find a hall', SiteBuilderFacade::link('halls')?->label());
        $this->assertArrayHasKey('halls', SiteBuilderFacade::statSources());
        $this->assertArrayHasKey('cookie_policy', SiteBuilderFacade::registeredLegalPages());
        $this->assertTrue(SiteBuilderFacade::consentAllows('marketing'));
        $this->assertFalse(SiteBuilderFacade::consentAllows('analytics'));
    }
}
