<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

class TabsBlock extends PageBlock
{
    public static function name(): string
    {
        return 'tabs';
    }

    public static function label(): string
    {
        return __('Tabs');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedQueueList;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            Repeater::make('items')
                ->label(__('Tabs'))
                ->schema([
                    TextInput::make('title')->label(__('Tab title'))->required()->maxLength(60),
                    static::richText('content', __('Content'))->required(),
                ])
                ->minItems(2)
                ->maxItems(8)
                ->defaultItems(2)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->addActionLabel(__('Add tab')),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'items' => collect($data['items'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['title'] ?? null))
                ->map(fn (array $item): array => ['title' => $item['title'], 'html' => static::html($item['content'] ?? null)])
                ->values()
                ->all(),
        ];
    }
}
