<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockConditions;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\RenderCache;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;

/**
 * Stage 4 of the visual builder: display conditions (checked on each request, even on a cached page), hover values, sticky,
 * scroll effects, and the full entrance animation settings.
 */
class StatesAndBehaviourTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    public function test_conditions_check_who_is_looking_and_when(): void
    {
        $monday9am = CarbonImmutable::parse('2026-11-02 09:00');
        $request = Request::create('/about-us?utm_source=newsletter');
        $request->cookies->set('member', 'yes');
        $passes = fn (array $rules, string $match = 'all', bool $signedIn = false): bool => BlockConditions::passes(
            ['conditions' => $rules, 'conditions_match' => $match],
            $signedIn ? $this->user() : null,
            $request,
            $monday9am,
        );

        $this->assertTrue(BlockConditions::passes([]), 'No conditions: always shown.');
        $this->assertTrue($passes([['type' => 'signed_out']]));
        $this->assertFalse($passes([['type' => 'signed_in']]));
        $this->assertTrue($passes([['type' => 'signed_in']], signedIn: true));
        $this->assertFalse($passes([['type' => 'role', 'role' => 'admin']], signedIn: true), 'A user model without roles has none.');
        $this->assertTrue($passes([['type' => 'dates', 'from' => '2026-11-01 00:00', 'until' => '2026-11-03 00:00']]));
        $this->assertFalse($passes([['type' => 'dates', 'from' => '2026-11-05 00:00']]));
        $this->assertTrue($passes([['type' => 'days', 'days' => ['1', '2']]]));
        $this->assertFalse($passes([['type' => 'days', 'days' => [6, 7]]]));
        $this->assertTrue($passes([['type' => 'times', 'from_time' => '08:00', 'until_time' => '17:30']]));
        $this->assertFalse($passes([['type' => 'times', 'from_time' => '22:00', 'until_time' => '06:00']]), 'Overnight window.');
        $this->assertTrue($passes([['type' => 'url_parameter', 'name' => 'utm_source', 'value' => 'newsletter']]));
        $this->assertFalse($passes([['type' => 'url_parameter', 'name' => 'utm_source', 'value' => 'ads']]));
        $this->assertTrue($passes([['type' => 'cookie', 'name' => 'member']]));
        $this->assertFalse($passes([['type' => 'cookie', 'name' => 'member', 'value' => 'no']]));

        $this->assertFalse($passes([['type' => 'signed_out'], ['type' => 'signed_in']]), 'All must be true…');
        $this->assertTrue($passes([['type' => 'signed_out'], ['type' => 'signed_in']], 'any'), '…or any.');
        $this->assertTrue($passes([['type' => 'no-such-rule']]), 'Unknown rules are ignored.');
    }

    public function test_a_conditional_block_is_checked_on_every_request_while_the_page_is_cached(): void
    {
        config(['site-builder.pages.cache' => true]);
        $this->assertTrue(RenderCache::isEnabled());

        $this->page(['blocks' => [
            self::block('text', ['content' => '<p>Welcome to the Village Hall</p>']),
            self::block('text', ['content' => '<p>Members area</p>', 'conditions' => [['type' => 'signed_in']]]),
            self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [
                self::block('text', ['content' => '<p>Sometown newsletter readers</p>', 'conditions' => [['type' => 'url_parameter', 'name' => 'from', 'value' => 'news']]]),
            ]]]]),
        ]]);

        $this->get('/about-us')->assertSee('Welcome to the Village Hall')->assertDontSee('Members area')->assertDontSee('Sometown newsletter readers');
        $this->get('/about-us?from=news')->assertSee('Sometown newsletter readers');

        $this->actingAs($this->user());
        $this->get('/about-us')->assertSee('Welcome to the Village Hall')->assertSee('Members area');
    }

    public function test_hover_sticky_scroll_effects_and_animation_settings(): void
    {
        $output = BlockDesign::resolve(['motion' => [
            'hover_scale' => 105, 'hover_lift' => 8, 'hover_shadow' => 'strong', 'hover_opacity' => 90,
            'sticky' => 'top', 'sticky_offset' => 72,
            'scroll_fade_in' => 0, 'scroll_move_y_in' => 80, 'scroll_scale_out' => 90, 'scroll_blur_out' => 100,
        ], 'effects' => [
            'animation' => 'bounce', 'animation_duration' => 1000, 'animation_delay' => 200, 'animation_intensity' => 100,
            'animation_opacity' => 20, 'animation_curve' => 'linear', 'animation_loop' => true,
        ]]);

        $classes = $output->classes('section');
        $style = $output->style('section');

        foreach (['sb-d-hov', 'sb-d-hover-scale', 'sb-d-hover-lift', 'sb-d-hover-opacity', 'sb-d-sticky-top', 'sb-d-scroll', 'sb-d-animate-bounce', 'sb-d-animate-loop'] as $class) {
            $this->assertStringContainsString($class, $classes);
        }

        foreach (['--sb-h-scale: 1.05;', '--sb-h-lift: -8px;', '--sb-h-opacity: 0.9;', '--sb-sticky-offset: 72px;', '--sb-s-fade-in: 0;', '--sb-s-move-y-in: 80px;', '--sb-s-scale-out: 0.9;',
            '--sb-anim-duration: 1000ms;', '--sb-anim-delay: 200ms;', '--sb-anim-k: 2;', '--sb-anim-opacity: 0.2;', '--sb-anim-curve: linear;'] as $property) {
            $this->assertStringContainsString($property, $style);
        }

        $this->assertStringNotContainsString('--sb-s-blur-out', $style, 'Out of range: left out.');
        $this->assertStringContainsString('sb-d-hover-shadow', $output->classes('box'));
        $this->assertStringContainsString('--sb-h-shadow: 0 10px 25px', $output->style('box'));

        $this->assertTrue(BlockDesign::resolve(['motion' => ['sticky' => 'fixed', 'hover_scale' => 900, 'scroll_fade_in' => 100, 'hover_shadow' => 'custom']])->isEmpty(), 'Unknown, out of range or neutral values give nothing.');

        $css = (string) file_get_contents(__DIR__.'/../resources/css/design/motion.css');
        $this->assertStringContainsString('@supports (animation-timeline: view())', $css);
        $this->assertStringContainsString('.sb-d-sticky-top:not([data-sb-node] > *) { position: sticky;', $css, 'Not sticky on the canvas.');
    }

    public function test_settings_have_hover_values(): void
    {
        $output = BlockDesign::resolve([
            'spacing' => ['padding' => ['desktop' => ['top' => '20px'], 'hover' => ['top' => '30px']], 'width' => ['hover' => '90%']],
            'borders' => ['border_width' => ['hover' => ['bottom' => '4px']], 'radius' => ['hover' => ['top_left' => '24px']], 'hover_border' => '#ff0000'],
            'typography' => ['heading' => ['size' => ['hover' => '40px'], 'colour' => '#111111', 'colour_hover' => '#336699']],
            'position' => ['translate_y' => ['hover' => '-6px']],
            'effects' => ['greyscale' => 100, 'greyscale_hover' => 0, 'brightness_hover' => 120],
        ]);

        $this->assertStringContainsString('sb-d-pt-h', $output->classes('box'));
        $this->assertStringContainsString('--sb-pt-h: 30px;', $output->style('box'));
        $this->assertStringContainsString('--sb-w-h: 90%;', $output->style('container'));
        $this->assertStringContainsString('--sb-bwb-h: 4px;', $output->style('box'));
        $this->assertStringContainsString('--sb-rtl-h: 24px;', $output->style('box'));
        $this->assertStringContainsString('--sb-hs-h: 40px;', $output->style('section'));
        $this->assertStringContainsString('--sb-hc-h: #336699;', $output->style('section'));
        $this->assertStringContainsString('--sb-ty-h: -6px;', $output->style('section'));
        $this->assertStringContainsString('--sb-filter-h: brightness(120%);', $output->style('section'), 'Greyscale 0 on hover changes nothing, so only brightness is left.');

        $css = (string) file_get_contents(__DIR__.'/../resources/css/design/hover.css');
        $this->assertStringContainsString(':is(.sb-d-pt-h:hover, [data-block]:hover > .sb-d-pt-h, [data-block]:hover > * > .sb-d-pt-h) { padding-top: var(--sb-pt-h); }', $css);
        $this->assertStringContainsString('.sb-d-hs-h:hover :is(h1, h2, h3, h4, h5, h6, .section-heading, .block-heading) { font-size: var(--sb-hs-h); }', $css);
    }

    public function test_the_panels_hover_state_shows_hover_values_beside_their_settings(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Hi</p>'])]]);

        $component = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]));

        $hoverOnly = fn ($field): bool => ($field->getExtraFieldWrapperAttributes()['x-show'] ?? null) === "\$store.sbDevice.current === 'hover'";
        $normalOnly = fn ($field): bool => str_starts_with((string) ($field->getExtraFieldWrapperAttributes()['x-show'] ?? ''), '! (');

        $component
            ->assertFormFieldExists('design.borders.hover_border', 'mountedActionSchema0', $hoverOnly)
            ->assertFormFieldExists('design.borders.border_color', 'mountedActionSchema0', $normalOnly)
            ->assertFormFieldExists('design.typography.heading.colour_hover', 'mountedActionSchema0', $hoverOnly)
            ->assertFormFieldExists('design.effects.greyscale_hover', 'mountedActionSchema0', $hoverOnly)
            ->assertFormFieldExists('design.motion.hover_shadow', 'mountedActionSchema0', $hoverOnly)
            ->assertFormFieldExists('design.spacing.padding', 'mountedActionSchema0', fn ($field): bool => ($field->getExtraFieldWrapperAttributes()['data-sb-hoverable'] ?? null) === 'true');
    }

    public function test_animation_and_hover_can_apply_to_each_item(): void
    {
        $output = BlockDesign::resolve([
            'effects' => ['animation' => 'slide-up', 'animation_target' => 'items'],
            'motion' => ['hover_lift' => 6, 'hover_shadow' => 'medium', 'hover_target' => 'items'],
        ]);

        foreach (['sb-d-animate-items', 'sb-d-hover-items', 'sb-d-hover-lift', 'sb-d-hover-shadow'] as $class) {
            $this->assertStringContainsString($class, $output->classes('section'));
        }

        $this->assertStringContainsString('--sb-h-shadow:', $output->style('section'), 'Each item gets the shadow from the section.');

        $this->page(['blocks' => [self::block('testimonials', ['items' => [['quote' => 'Lovely hall', 'name' => 'Sam'], ['quote' => 'Easy to book', 'name' => 'Alex']], 'design' => ['effects' => ['animation' => 'fade', 'animation_target' => 'items']]])]]);

        $html = $this->get('/about-us')->getContent();
        $this->assertSame(2, substr_count($html, '<figure class="sb-item '));
        $this->assertStringContainsString("rootMargin: '0px 0px -20% 0px'", $html, 'It plays once well into view, not as it peeks in.');
        $this->assertStringContainsString('.sb-d-animate-items .sb-item:not([data-sb-watched])', $html);
    }
}
