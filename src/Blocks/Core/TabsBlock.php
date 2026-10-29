<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
            static::itemTooltips(Repeater::make('items'), __('tab'))
                ->label(__('Tabs'))
                ->schema([
                    TextInput::make('title')->label(__('Tab title'))->required()->maxLength(60),
                    static::richText('content', __('Content'))->required(),
                    static::imageUpload('image', __('Image (optional)'))
                        ->helperText(__('Shown under the text, e.g. a screenshot.')),
                    TextInput::make('alt')
                        ->label(__('Alternative text'))
                        ->helperText(__('Describes the image for people who can\'t see it.'))
                        ->requiredWith('image')
                        ->maxLength(200),
                ])
                ->minItems(2)
                ->maxItems(8)
                ->defaultItems(2)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->addActionLabel(__('Add tab')),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('tabs_align')
                ->label(__('Line up the tabs'))
                ->options(['left' => __('Left'), 'center' => __('Centre'), 'right' => __('Right')])
                ->default('left')
                ->selectablePlaceholder(false),
        ];
    }

    protected static function styleToggleTitle(): string
    {
        return __('Image');
    }

    protected static function styleFields(): array
    {
        return [
            static::imageStyleFieldset(),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'imageStyle' => static::imageStyle($data, defaultShadow: 'site'),
            'tabsAlignClass' => ['left' => 'justify-start', 'center' => 'justify-center', 'right' => 'justify-end'][static::pick($data['tabs_align'] ?? null, ['left' => 1, 'center' => 1, 'right' => 1], 'left')],
            'items' => collect($data['items'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['title'] ?? null))
                ->map(fn (array $item): array => [
                    'title' => $item['title'],
                    'html' => static::html($item['content'] ?? null),
                    'imageUrl' => static::imageUrl($item['image'] ?? null),
                    'alt' => is_string($item['alt'] ?? null) ? $item['alt'] : '',
                ])
                ->values()
                ->all(),
        ];
    }
}
