<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Links to the board's social profiles, as set in Admin › Settings › General Settings › Social Links.
 */
class SocialFollowBlock extends PageBlock
{
    public const NETWORKS = [
        'facebook' => 'Facebook',
        'x' => 'X (Twitter)',
        'linkedin' => 'LinkedIn',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
        'github' => 'GitHub',
        'discord' => 'Discord',
    ];

    public static function name(): string
    {
        return 'social_follow';
    }

    public static function label(): string
    {
        return __('Social follow');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedShare;
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
            Text::make(__('Shows an icon for each network that has a link in Settings › General Settings › Social Links.')),
            TextInput::make('heading')->label(__('Heading'))->maxLength(150)->placeholder(__('Follow us')),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('align')
                ->label(__('Alignment'))
                ->options(['left' => __('Left'), 'center' => __('Centre')])
                ->default('center')
                ->selectablePlaceholder(false),
        ];
    }

    /**
     * @return array<string, array{url: string, label: string}>
     */
    public static function links(): array
    {
        $links = [];

        foreach (self::NETWORKS as $network => $label) {
            // The base kit saves LinkedIn under "linkedin-openid".
            $url = $network === 'linkedin'
                ? (config('app.social_links.linkedin-openid') ?: config('app.social_links.linkedin'))
                : config("app.social_links.{$network}");

            if (filled($url)) {
                $links[$network] = ['url' => $url, 'label' => $label];
            }
        }

        return $links;
    }

    public static function viewData(array $data, Page $page): array
    {
        return ['links' => static::links()];
    }
}
