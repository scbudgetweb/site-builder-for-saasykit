<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Questions and answers in an accordion, with FAQPage structured data for search engines.
 */
class FaqBlock extends PageBlock
{
    public static function name(): string
    {
        return 'faq';
    }

    public static function label(): string
    {
        return __('FAQ');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedQuestionMarkCircle;
    }

    public static function defaultWidth(): string
    {
        return 'narrow';
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            static::itemTooltips(Repeater::make('items'), __('question'))
                ->label(__('Questions'))
                ->schema([
                    TextInput::make('question')->label(__('Question'))->required()->maxLength(250),
                    static::richText('answer', __('Answer'))->required(),
                ])
                ->minItems(1)
                ->defaultItems(3)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                ->addActionLabel(__('Add question')),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $items = collect($data['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['question'] ?? null))
            ->map(fn (array $item): array => [
                'question' => $item['question'],
                'html' => static::html($item['answer'] ?? null),
            ])
            ->values();

        return [
            'items' => $items->all(),
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $items->map(fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['html']],
                ])->all(),
            ],
        ];
    }
}
