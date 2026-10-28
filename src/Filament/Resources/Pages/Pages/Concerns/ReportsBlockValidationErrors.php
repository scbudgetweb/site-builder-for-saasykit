<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;

/**
 * With block previews, each block's fields live in a panel, so a validation error inside a block has nowhere visible
 * to show and Save seems to do nothing. Name the block and the problem instead (lesson 1).
 */
trait ReportsBlockValidationErrors
{
    protected function onValidationError(ValidationException $exception): void
    {
        $problems = [];

        foreach ($exception->errors() as $key => $messages) {
            // data.blocks.{id}.… or, in a Section's row, data.blocks.{id}.data.rows.{rowId}.columns.{columnId}.blocks.{innerId}.…
            if (! preg_match('/^data\.blocks\.([^.]+)\.(?:data\.rows\.([^.]+)\.columns\.([^.]+)\.blocks\.([^.]+)\.)?/', $key, $matches)) {
                continue;
            }

            $where = $this->describeBlock($this->data['blocks'] ?? [], $matches[1]);

            if (filled($matches[4] ?? null)) {
                $rows = $this->data['blocks'][$matches[1]]['data']['rows'] ?? [];
                $columns = $rows[$matches[2]]['columns'] ?? [];

                $where .= ' › '.__('Row :number', ['number' => $this->positionOf($rows, $matches[2])]);

                // "Column 2", only when the row has columns side by side.
                if (is_array($columns) && count($columns) > 1) {
                    $where .= ' › '.__('Column :number', ['number' => $this->positionOf($columns, $matches[3])]);
                }

                $where .= ' › '.$this->describeBlock($columns[$matches[3]]['blocks'] ?? [], $matches[4]);
            }

            $problems[] = $where.': '.Str::of($messages[0] ?? '')->trim();
        }

        if ($problems === []) {
            parent::onValidationError($exception);

            return;
        }

        Notification::make()
            ->title($this->blockValidationErrorTitle())
            ->body(new HtmlString(collect([...array_unique($problems), __('Click the block on the Content tab to fix it.')])
                ->map(fn (string $line): string => e($line))
                ->implode('<br>')))
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * The library editor says "The library block wasn't saved".
     */
    protected function blockValidationErrorTitle(): string
    {
        return __('The :thing wasn\'t saved', ['thing' => static::getResource()::getModelLabel()]);
    }

    /**
     * The item's position (from 1) among the repeater's items, or "?".
     */
    private function positionOf(mixed $items, string $id): int|string
    {
        $position = is_array($items) ? array_search($id, array_keys($items), true) : false;

        return $position === false ? '?' : $position + 1;
    }

    /**
     * "Block 2 (Call to action)" for the Builder item with this ID.
     *
     * @param  array<string, mixed>|mixed  $items
     */
    private function describeBlock(mixed $items, string $id): string
    {
        $items = is_array($items) ? $items : [];

        $position = array_search($id, array_keys($items), true);
        $type = $items[$id]['type'] ?? null;

        return __('Block :number (:block)', [
            'number' => $position === false ? '?' : $position + 1,
            'block' => is_string($type) ? app(BlockRegistry::class)->labelFor($type) : __('unknown'),
        ]);
    }
}
