<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\EditSavedBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\EditTemplate;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Support\LivewireLimits;

/**
 * The rich editor sends Livewire each change at its place in the editor's nested document, two path parts for every
 * level, and Livewire refuses a path over livewire.payload.max_nesting_depth parts (10 by default). A bullet list or a
 * table inside a panel's repeater goes well past 10, so the package raises the limit to what the builder needs.
 */
class RichTextNestingTest extends PageTestCase
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

    /**
     * A rich text document with these nodes one inside another, ending in a text node.
     *
     * @param  array<int, string>  $types
     * @return array<string, mixed>
     */
    private static function document(array $types): array
    {
        $node = ['type' => 'text', 'text' => 'Village Hall'];

        foreach (array_reverse($types) as $type) {
            $node = ['type' => $type, 'content' => [$node]];
        }

        return ['type' => 'doc', 'content' => [$node]];
    }

    /**
     * The path to the text node at the bottom of document($types), as the editor sends it when that text changes.
     *
     * @param  array<int, string>  $types
     */
    private static function textPath(string $field, array $types): string
    {
        return $field.str_repeat('.content.0', count($types) + 1).'.text';
    }

    public function test_livewire_allows_the_paths_the_builder_needs(): void
    {
        $this->assertGreaterThanOrEqual(LivewireLimits::NESTING_DEPTH, config('livewire.payload.max_nesting_depth'));
    }

    public function test_typing_in_a_repeaters_nested_list_or_table_in_the_visual_builder(): void
    {
        // A list inside a list inside a table cell.
        $types = ['table', 'tableRow', 'tableCell', 'bulletList', 'listItem', 'bulletList', 'listItem', 'paragraph'];
        $blocks = [
            'answer' => self::block('faq', ['items' => [['question' => 'When do you deliver?', 'answer' => '<p>Monthly</p>']]]),
            'content' => self::block('tabs', ['items' => [['title' => 'Plans', 'content' => '<p>Three sizes</p>']]]),
        ];

        foreach ($blocks as $field => $block) {
            $page = $this->page(['slug' => $block['type'], 'blocks' => [$block]]);
            $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
                ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]));
            $item = array_key_first($editor->get('mountedActions.0.data.items'));
            $text = "mountedActions.0.data.items.{$item}.{$field}";
            $path = self::textPath($text, $types);
            $this->assertGreaterThan(10, count(explode('.', $path)), 'Past Livewire\'s standard limit.');

            $editor->set($text, self::document($types))->set($path, 'Sometown');

            $this->assertSame('Sometown', data_get($editor->get($text), substr($path, strlen($text) + 1)), $block['type']);
        }
    }

    public function test_typing_in_a_text_blocks_bullet_list_in_the_visual_builder(): void
    {
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<ul><li><p>Village Hall</p></li></ul>'])]]);

        $editor = Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]));

        // list › item › paragraph › text: 13 parts with the panel's own.
        $types = ['bulletList', 'listItem', 'paragraph'];
        $editor->set('mountedActions.0.data.content', self::document($types))
            ->set(self::textPath('mountedActions.0.data.content', $types), 'Sometown');

        $this->assertSame('Sometown', data_get($editor->get('mountedActions.0.data.content'), 'content.0.content.0.content.0.content.0.text'));
    }

    public function test_typing_in_a_list_inside_a_section_in_every_form(): void
    {
        $section = ['rows' => [['layout' => '2', 'columns' => [
            ['blocks' => [self::block('faq', ['items' => [['question' => 'When?', 'answer' => '<p>Monthly</p>']]])]],
            ['blocks' => []],
        ]]]];
        $forms = [
            'page' => [EditPage::class, $this->page(['blocks' => [self::block('section', $section)]])->getRouteKey()],
            'library item' => [EditSavedBlock::class, app(BlockLibrary::class)->save('Questions', 'section', $section)->getRouteKey()],
            'template' => [EditTemplate::class, Template::query()->create(['name' => 'Main footer', 'type' => 'footer', 'blocks' => [self::block('section', $section)]])->getRouteKey()],
        ];
        $types = ['bulletList', 'listItem', 'bulletList', 'listItem', 'paragraph'];

        foreach ($forms as $name => [$form, $record]) {
            // The Section's panel, then the FAQ's panel over it.
            $component = Livewire::test($form, ['record' => $record]);
            $component->mountAction(TestAction::make('edit')->schemaComponent('blocks')->arguments(['item' => array_key_first($component->get('data.blocks'))]));
            $row = array_key_first($component->get('mountedActions.0.data.rows'));
            $column = array_key_first($component->get("mountedActions.0.data.rows.{$row}.columns"));
            $blocks = "rows.{$row}.columns.{$column}.blocks";
            $component->mountAction(TestAction::make('edit')->schemaComponent($blocks, 'mountedActionSchema0')->arguments(['item' => array_key_first($component->get("mountedActions.0.data.{$blocks}"))]));

            $item = array_key_first($component->get('mountedActions.1.data.items'));
            $answer = "mountedActions.1.data.items.{$item}.answer";
            $component->set($answer, self::document($types))->set(self::textPath($answer, $types), 'Sometown');

            $this->assertSame('Sometown', data_get($component->get($answer), 'content.0.content.0.content.0.content.0.content.0.content.0.text'), $name);
        }
    }

    public function test_the_limit_is_only_ever_raised_and_can_be_left_alone(): void
    {
        $at = function (mixed $current, mixed $setting = LivewireLimits::NESTING_DEPTH): mixed {
            config(['livewire.payload.max_nesting_depth' => $current, 'site-builder.livewire_nesting_depth' => $setting]);
            LivewireLimits::apply();

            return config('livewire.payload.max_nesting_depth');
        };

        $this->assertSame(LivewireLimits::NESTING_DEPTH, $at(10), 'Livewire\'s standard 10 is raised.');
        $this->assertSame(100, $at(100), 'A higher limit set by the app is kept.');
        $this->assertNull($at(null), 'No limit stays no limit.');
        $this->assertSame(10, $at(10, false), 'Turned off in config/site-builder.php: Livewire\'s own setting stands.');
        $this->assertSame(64, $at(10, 64), 'An app can choose its own depth.');
        $this->assertSame(LivewireLimits::MAX_NESTING_DEPTH, $at(10, 10_000), 'Never past a sensible bound.');
    }
}
