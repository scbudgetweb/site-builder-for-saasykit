<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * A Google map of an address or a place, from Google Maps' plain embed, so no API key is
 * needed. The map is built on the server from the address alone. Google Maps sets its own (marketing) cookies, so,
 * as with Video, a visitor who hasn't allowed them sees a placeholder with Show map until they choose to load it.
 * Optionally, under the map: the address as text and a Get directions link.
 */
class MapBlock extends PageBlock
{
    /** Zoom: label and Google's zoom level. */
    public const ZOOMS = [
        'street' => ['Street', 17],
        'neighbourhood' => ['Neighbourhood', 15],
        'town' => ['Town', 13],
        'area' => ['Wider area', 10],
    ];

    /** Height: label and the map's classes (lower on phones). */
    public const HEIGHTS = [
        'small' => ['Small', 'h-56 sm:h-72'],
        'medium' => ['Medium', 'h-72 sm:h-96'],
        'large' => ['Large', 'h-80 sm:h-[32rem]'],
    ];

    public static function name(): string
    {
        return 'map';
    }

    public static function label(): string
    {
        return __('Map');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedMapPin;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            TextInput::make('address')
                ->label(__('Address or place'))
                ->helperText(__('A full address, a postcode or a business\'s name, as you\'d search for it on Google Maps. No API key or Google account needed.'))
                ->required()
                ->maxLength(300)
                ->placeholder(__('1 High Street, Anytown')),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Select::make('zoom')
                    ->label(__('Zoom'))
                    ->options(array_map(fn (array $zoom): string => __($zoom[0]), self::ZOOMS))
                    ->default('neighbourhood')
                    ->selectablePlaceholder(false),
                Select::make('height')
                    ->label(__('Height'))
                    ->options(array_map(fn (array $height): string => __($height[0]), self::HEIGHTS))
                    ->default('medium')
                    ->selectablePlaceholder(false),
                Toggle::make('show_address')
                    ->label(__('Show the address under the map'))
                    ->default(false)
                    ->live(),
                Toggle::make('show_directions')
                    ->label(__('Show a Get directions link'))
                    ->default(true),
            ]),
            Textarea::make('address_text')
                ->label(__('Address as shown'))
                ->helperText(__('Optional, one line each. Blank: the address above.'))
                ->rows(3)
                ->maxLength(300)
                ->visible(fn (Get $get): bool => (bool) $get('show_address')),
        ];
    }

    protected static function styleFields(): array
    {
        return [static::imageStyleFieldset()];
    }

    /**
     * Google Maps' links for an address: embedUrl (the map), openUrl (the place on Google Maps) and directionsUrl. Null
     * for no address. Only the address goes to Google, encoded, so nothing else can be slipped into the map's address.
     *
     * @return array{embedUrl: string, openUrl: string, directionsUrl: string}|null
     */
    public static function urls(mixed $address, mixed $zoom = null): ?array
    {
        $address = is_string($address) ? trim((string) preg_replace('/\s+/', ' ', $address)) : '';

        if ($address === '') {
            return null;
        }

        return [
            'embedUrl' => 'https://www.google.com/maps?'.http_build_query([
                'q' => $address,
                'z' => self::ZOOMS[static::pick($zoom, self::ZOOMS, 'neighbourhood')][1],
                'hl' => str_replace('_', '-', app()->getLocale()),
                'output' => 'embed',
            ], '', '&', PHP_QUERY_RFC3986),
            'openUrl' => 'https://www.google.com/maps/search/?'.http_build_query(['api' => 1, 'query' => $address], '', '&', PHP_QUERY_RFC3986),
            'directionsUrl' => 'https://www.google.com/maps/dir/?'.http_build_query(['api' => 1, 'destination' => $address], '', '&', PHP_QUERY_RFC3986),
        ];
    }

    /**
     * The map depends on the visitor's cookie choices, so a page with a map isn't cached.
     */
    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function viewData(array $data, Page $page): array
    {
        $address = is_string($data['address'] ?? null) ? trim($data['address']) : '';
        $addressText = is_string($data['address_text'] ?? null) && trim($data['address_text']) !== '' ? trim($data['address_text']) : $address;

        return [
            'map' => static::urls($address, $data['zoom'] ?? null),
            'address' => $address,
            'addressLines' => (bool) ($data['show_address'] ?? false) ? preg_split('/\R/', $addressText) : [],
            'showsDirections' => ($data['show_directions'] ?? true) !== false,
            'heightClasses' => self::HEIGHTS[static::pick($data['height'] ?? null, self::HEIGHTS, 'medium')][1],
            'imageStyle' => static::imageStyle($data),
            // A placeholder unless the app's cookie banner says marketing cookies are allowed, as Video's.
            'showsMapStraightAway' => app(SiteBuilder::class)->consentAllows('marketing'),
        ];
    }
}
