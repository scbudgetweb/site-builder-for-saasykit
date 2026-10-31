<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Blog\BlogPost;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * Blog post template: links to share the post (X, LinkedIn, Facebook, or by email), each opening that site's own share
 * screen. Nothing is loaded from those sites.
 */
class PostShareBlock extends PageBlock
{
    public const NETWORKS = ['x' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'email' => 'Email'];

    public static function name(): string
    {
        return 'post_share';
    }

    public static function label(): string
    {
        return __('Share links');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedShare;
    }

    public static function templateTypes(): ?array
    {
        return [Templates::BLOG_POST];
    }

    public static function headingSettings(): array
    {
        return ['decoration', 'size'];
    }

    public static function hasBodyText(): bool
    {
        return false;
    }

    protected static function fields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('heading')
                    ->label(__('Heading'))
                    ->maxLength(150)
                    ->placeholder(__('Share this post')),
                CheckboxList::make('networks')
                    ->label(__('Share on'))
                    ->options(array_map(fn (string $label): string => __($label), self::NETWORKS))
                    ->default(array_keys(self::NETWORKS))
                    ->in(array_keys(self::NETWORKS)),
            ]),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('align')
                ->label(__('Alignment'))
                ->options(array_map(fn (string $label): string => __($label), HeadingBlock::ALIGNMENTS))
                ->default('left')
                ->selectablePlaceholder(false),
        ];
    }

    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function isVisible(array $data): bool
    {
        return static::viewData($data, new Page)['links'] !== [];
    }

    /**
     * Each network's share link for the post.
     *
     * @param  array<int, string>  $networks
     * @return array<string, array{url: string, label: string}>
     */
    public static function links(BlogPost $post, array $networks): array
    {
        $url = rawurlencode($post->url);
        $title = rawurlencode($post->title);
        $links = [];

        foreach (array_keys(self::NETWORKS) as $network) {
            if (in_array($network, $networks, true)) {
                $links[$network] = [
                    'url' => match ($network) {
                        'x' => "https://x.com/intent/post?text={$title}&url={$url}",
                        'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
                        'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$url}",
                        'email' => "mailto:?subject={$title}&body={$url}",
                    },
                    'label' => __('Share on :network', ['network' => __(self::NETWORKS[$network])]),
                ];
            }
        }

        if (isset($links['email'])) {
            $links['email']['label'] = __('Share by email');
        }

        return $links;
    }

    public static function viewData(array $data, Page $page): array
    {
        $post = app(Blog::class)->post();

        return [
            'heading' => is_string($data['heading'] ?? null) ? trim($data['heading']) : '',
            'links' => $post === null ? [] : self::links($post, is_array($data['networks'] ?? null) ? $data['networks'] : array_keys(self::NETWORKS)),
            'align' => static::pick($data['align'] ?? null, HeadingBlock::ALIGNMENTS, 'left'),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
