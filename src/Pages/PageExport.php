<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockPresets;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\BlockSchema;
use ScBudgetWeb\SiteBuilder\Blocks\BlockTree;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Models\Template;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A page, template, library block, or one Section, row, column or block, as a JSON file to import on this site or
 * another (PageImport). Blocks are in site-builder:page's format, without uids (an import gives new ones). What only
 * exists on this site is written out in full: a linked library block becomes the block it shows, and a preset or saved
 * style becomes the settings it gives. A page's export holds its own blocks only: no title, address or SEO, and no
 * header or footer (those are templates, exported on their own).
 *
 * The file: {"site_builder": FORMAT, "kind", "schema": BlockSchema::VERSION, "exported_at", and by kind:
 * "blocks" (page, section, block, library), "row", "column", or "template" ({type, settings}) and "blocks"}.
 */
final class PageExport
{
    /** The file's own version: an import refuses a newer one. */
    public const FORMAT = 1;

    public const KINDS = ['page', 'template', 'library', 'section', 'row', 'column', 'block'];

    public function __construct(private BlockLibrary $library, private BlockPresets $presets, private BlockRegistry $registry) {}

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return array<string, mixed>
     */
    public function page(array $blocks): array
    {
        return $this->file('page', ['blocks' => $this->blocks($blocks)]);
    }

    /**
     * A template's blocks with its type and settings (a header's behaviour), without where it shows: that points at
     * this site's pages.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<string, mixed>
     */
    public function template(Template $template, array $blocks): array
    {
        return $this->file('template', [
            'template' => ['type' => $template->type, 'settings' => is_array($template->settings) ? $template->settings : []],
            'blocks' => $this->blocks($blocks),
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return array<string, mixed>
     */
    public function library(array $blocks): array
    {
        return $this->file('library', ['blocks' => $this->blocks($blocks)]);
    }

    /**
     * One block (or Section), e.g. a form builder card's, already in its list form.
     *
     * @param  array{type: string, data?: mixed}  $block
     * @return array<string, mixed>
     */
    public function block(array $block, bool $isNested = false): array
    {
        return $this->file($block['type'] === SectionBlock::NAME ? 'section' : 'block', ['blocks' => $this->blocks([$block], $isNested)]);
    }

    /**
     * The saved page, template or library block (the list's and Edit as a form's Export).
     *
     * @return array<string, mixed>
     */
    public function record(Page|Template|SavedBlock $record): array
    {
        $blocks = $record->blocks ?? [];

        return match (true) {
            $record instanceof Template => $this->template($record, $blocks),
            $record instanceof SavedBlock => $this->library($blocks),
            default => $this->page($blocks),
        };
    }

    /**
     * Export, on a page's, template's or library block's row in its list and on Edit as a form: the saved version.
     */
    public static function recordAction(): Action
    {
        return Action::make('export')
            ->label(__('Export'))
            ->tooltip(__('Download as a file (as last saved), to import here or on another site'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->hidden(fn (Model $record): bool => method_exists($record, 'trashed') && $record->trashed())
            ->action(function (Page|Template|SavedBlock $record): StreamedResponse {
                $export = app(self::class);

                return $export->download($export->record($record), $record instanceof Page ? $record->title : $record->name);
            });
    }

    /**
     * The Section, block, row or column with this uid, or null when there's none.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<string, mixed>|null
     */
    public function part(array $blocks, string $uid): ?array
    {
        $found = BlockTree::locate($blocks, $uid);

        return match ($found['kind'] ?? null) {
            'block' => $this->file($found['node']['type'] === SectionBlock::NAME ? 'section' : 'block', [
                'blocks' => $this->blocks([$found['node']], isNested: $found['path'] !== []),
            ]),
            'row' => $this->file('row', ['row' => $this->row($found['node'])]),
            'column' => $this->file('column', ['column' => $this->column($found['node'])]),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $file
     */
    public function download(array $file, string $name): StreamedResponse
    {
        $json = (string) json_encode($file, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(fn () => print ($json), self::fileName($name, (string) $file['kind']), ['Content-Type' => 'application/json']);
    }

    /**
     * e.g. "home-page-2026-11-03.json" or "pricing-section-2026-11-03.json".
     */
    public static function fileName(string $name, string $kind): string
    {
        $slug = Str::slug(Str::limit($name, 60, ''));

        return collect([$slug, Str::endsWith($slug, $kind) ? null : $kind, now()->format('Y-m-d')])->filter()->implode('-').'.json';
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function file(string $kind, array $content): array
    {
        return [
            'site_builder' => self::FORMAT,
            'kind' => $kind,
            'schema' => BlockSchema::VERSION,
            'exported_at' => now()->toIso8601String(),
            ...$content,
        ];
    }

    /**
     * The blocks as a list, linked library blocks swapped for theirs, presets and saved styles written out, and no uids.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function blocks(array $blocks, bool $isNested = false): array
    {
        $exported = [];

        foreach ($this->library->expand($blocks) as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $blockClass = $this->registry->find($block['type']);

            if ($blockClass !== null) {
                $data = $this->presets->apply($blockClass, $data, $isNested);
                unset($data['preset'], $data['style_presets']);
            }

            if ($block['type'] === SectionBlock::NAME && is_array($data['rows'] ?? null)) {
                $data['rows'] = array_values(array_map(fn (mixed $row): mixed => is_array($row) ? $this->row($row, isExpanded: true) : $row, $data['rows']));
            }

            $exported[] = ['type' => $block['type'], 'data' => $data];
        }

        return $exported;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row, bool $isExpanded = false): array
    {
        if (! $isExpanded) {
            // Its linked library blocks are swapped as a Section's are.
            $section = $this->library->expand([['type' => SectionBlock::NAME, 'data' => ['rows' => [$row]]]])[0];
            $row = $section['data']['rows'][0];
        }

        $row = SectionBlock::normaliseRow($row);
        unset($row['uid']);
        $row['columns'] = array_values(array_map(fn (array $column): array => $this->column($column, isExpanded: true), $row['columns']));

        return $row;
    }

    /**
     * @param  array<string, mixed>  $column
     * @return array<string, mixed>
     */
    private function column(array $column, bool $isExpanded = false): array
    {
        if (! $isExpanded) {
            return $this->row(['layout' => 'stacked', 'columns' => [$column]])['columns'][0];
        }

        unset($column['uid']);
        $column['blocks'] = $this->blocks(is_array($column['blocks'] ?? null) ? $column['blocks'] : [], isNested: true);

        return $column;
    }
}
