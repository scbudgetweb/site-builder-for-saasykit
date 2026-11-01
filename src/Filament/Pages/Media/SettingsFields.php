<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Pages\Media;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Settings\AudioUploads;
use ScBudgetWeb\SiteBuilder\Settings\ImageUploads;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\Settings\UploadRules;

/**
 * Website › Media's Images and Audio tabs: what pages can upload. Images: the types (SVG on or off) and the largest
 * file, and, with Laravel's image tools, shrinking large photos and saving them without their location data. Audio:
 * the types and the largest sound file. Each size is shown against the server's own limit, which the site can't raise.
 */
class SettingsFields
{
    /**
     * @return array<int, mixed>
     */
    public static function images(): array
    {
        $images = app(ImageUploads::class);

        return [
            CheckboxList::make('image_types')
                ->label(__('File types image fields take'))
                ->helperText(__('SVG drawings stay sharp at any size, and are only taken when they\'re plain drawings, with no scripts or links. A share image is never an SVG: social sites don\'t show them.'))
                ->options(array_map(fn (array $type): string => $type[0], ImageUploads::TYPES))
                ->descriptions(array_map(fn (array $type): string => '.'.implode(', .', $type[1]), ImageUploads::TYPES))
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->required()
                ->minItems(1),
            TextInput::make('image_max_megabytes')
                ->label(__('Largest image'))
                ->helperText(__('A photo straight from a phone is often 3 to 6 MB. With Shrink large photos on, a larger one is made smaller as it\'s uploaded.'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(ImageUploads::MAX_MEGABYTES)
                ->suffix('MB')
                ->required()
                ->live(onBlur: true),
            self::serverCallout('image_max_megabytes', $images, __('Image fields')),
            Section::make(__('When a photo is uploaded'))
                ->description($images->canProcess()
                    ? __('JPG, PNG and WebP photos. GIFs (which may move), AVIF, SVG drawings and very large photos are kept as uploaded. Photos already uploaded don\'t change.')
                    : __('These need Laravel 13 and Intervention Image 4 (composer require intervention/image:^4.0), with PHP\'s GD extension. Ask your developer to add them.'))
                ->compact()
                ->schema([
                    Select::make('image_max_width')
                        ->label(__('Shrink large photos'))
                        ->helperText(__('Wider photos are made this wide, keeping their shape, so pages load faster. 2560 pixels is sharp on large screens.'))
                        ->options(['' => __('No, keep them as uploaded')] + array_combine(
                            array_map('strval', ImageUploads::WIDTHS),
                            array_map(fn (int $width): string => __(':width pixels wide', ['width' => number_format($width)]), ImageUploads::WIDTHS),
                        ))
                        ->selectablePlaceholder(false)
                        ->disabled(! $images->canProcess()),
                    Toggle::make('image_strip_location')
                        ->label(__('Remove location data'))
                        ->helperText(__('Phones save where a photo was taken inside it. This saves photos again without it (or the rest of the camera details), so a published photo can\'t give away someone\'s home, say.'))
                        ->disabled(! $images->canProcess()),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function audio(): array
    {
        return [
            CheckboxList::make('audio_types')
                ->label(__('File types Audio blocks take'))
                ->helperText(__('MP3 plays in every browser; M4A, OGG and WAV in every current one. FLAC and WebM don\'t play in every browser, so offer them only alongside a download.'))
                ->options(array_map(fn (array $type): string => $type[0], AudioUploads::TYPES))
                ->descriptions(array_map(fn (array $type): string => '.'.implode(', .', $type[1]), AudioUploads::TYPES))
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->required()
                ->minItems(1),
            TextInput::make('audio_max_megabytes')
                ->label(__('Largest sound file'))
                ->helperText(__('An hour\'s talk at 128 kbps is about 60 MB. Only page editors upload files, and each file stays in the library until someone deletes it.'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(AudioUploads::MAX_MEGABYTES)
                ->suffix('MB')
                ->required()
                ->live(onBlur: true),
            self::serverCallout('audio_max_megabytes', app(AudioUploads::class), __('Audio blocks')),
        ];
    }

    /**
     * The Images tab's fields as saved.
     *
     * @return array<string, mixed>
     */
    public static function imageState(ImageUploads $images, Settings $settings): array
    {
        $width = $settings->get(ImageUploads::WIDTH_SETTING);

        return [
            'image_types' => $images->types(),
            'image_max_megabytes' => $images->chosenMegabytes(),
            'image_max_width' => is_numeric($width) ? (string) (int) $width : '',
            'image_strip_location' => (bool) $settings->get(ImageUploads::STRIP_LOCATION_SETTING, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function audioState(AudioUploads $audio): array
    {
        return [
            'audio_types' => $audio->types(),
            'audio_max_megabytes' => $audio->chosenMegabytes(),
        ];
    }

    /**
     * The Images tab's state as settings. Off (no width, location kept) is removed, so it falls back to the standard.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function imageSettings(array $data): array
    {
        $width = $data['image_max_width'] ?? null;

        return [
            ImageUploads::TYPES_SETTING => array_values(array_intersect(array_keys(ImageUploads::TYPES), (array) ($data['image_types'] ?? []))),
            ImageUploads::MEGABYTES_SETTING => (int) $data['image_max_megabytes'],
            ImageUploads::WIDTH_SETTING => is_numeric($width) && in_array((int) $width, ImageUploads::WIDTHS, true) ? (int) $width : null,
            ImageUploads::STRIP_LOCATION_SETTING => (bool) ($data['image_strip_location'] ?? false) ? true : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function audioSettings(array $data): array
    {
        return [
            AudioUploads::TYPES_SETTING => array_values(array_intersect(array_keys(AudioUploads::TYPES), (array) ($data['audio_types'] ?? []))),
            AudioUploads::MEGABYTES_SETTING => (int) $data['audio_max_megabytes'],
        ];
    }

    /**
     * The server's own upload limit under a size field, in orange when it's lower than the size typed.
     */
    private static function serverCallout(string $field, UploadRules $rules, string $takers): Callout
    {
        $serverKilobytes = $rules->serverMaxKilobytes();
        $isLower = fn (Get $get): bool => $serverKilobytes !== null && is_numeric($get($field)) && (int) $get($field) * 1024 > $serverKilobytes;

        return Callout::make(__('Your server takes uploads up to :size', ['size' => $serverKilobytes === null ? __('any size') : UploadRules::megabytes($serverKilobytes)]))
            ->description(fn (Get $get): string => $isLower($get)
                ? __('That\'s less than the size above, so :takers take files up to :size until your host raises PHP\'s upload_max_filesize and post_max_size (and the web server\'s own limit, such as nginx\'s client_max_body_size).', ['takers' => $takers, 'size' => UploadRules::megabytes((int) $serverKilobytes)])
                : __('Set by PHP\'s upload_max_filesize and post_max_size. If large files still fail to upload, ask your host about the web server\'s own limit too (such as nginx\'s client_max_body_size).'))
            ->color(fn (Get $get): string => $isLower($get) ? 'warning' : 'info')
            ->icon(Heroicon::OutlinedServer);
    }
}
