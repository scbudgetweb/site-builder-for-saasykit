<?php

namespace ScBudgetWeb\SiteBuilder\Branding;

use InvalidArgumentException;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * One setting in an app's own Branding section (SiteBuilder::brandingSection()): text, a toggle, a choice, a colour, a
 * button style, an image or a whole banner. It's saved as "branding.app_{key}" (a banner as
 * "branding.app_{key}_{banner field}", like the hero and inner banners), and read back with Branding::appValue(), which
 * re-checks the saved value every time (lesson 11).
 */
final class BrandingField
{
    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const TOGGLE = 'toggle';

    public const SELECT = 'select';

    public const COLOUR = 'colour';

    public const BUTTON_STYLE = 'button_style';

    public const IMAGE = 'image';

    public const BANNER = 'banner';

    /** A button style choice: a Branding button style ("1"–"4"), or this, which suits the background. */
    public const AUTOMATIC_BUTTON_STYLE = 'auto';

    public const IMAGE_FILE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private ?string $helperText = null;

    private ?string $tooltip = null;

    private mixed $default = null;

    /** @var array<string, string> */
    private array $options = [];

    private int $maxLength = 255;

    /** @var array<int, string> */
    private array $variables = [];

    private ?string $contrastWith = null;

    /** @var array<int, string> */
    private array $fileTypes = self::IMAGE_FILE_TYPES;

    private int $maxSizeInKilobytes = 2048;

    private function __construct(public readonly string $type, public readonly string $key, public readonly string $label)
    {
        if (! preg_match('/^[a-z0-9_]+$/', $key)) {
            throw new InvalidArgumentException("Branding field key [{$key}] may only use a-z, 0-9 and _.");
        }
    }

    public static function text(string $key, string $label, int $maxLength = 255): self
    {
        $field = new self(self::TEXT, $key, $label);
        $field->maxLength = $maxLength;

        return $field;
    }

    public static function textarea(string $key, string $label, int $maxLength = 1000): self
    {
        $field = new self(self::TEXTAREA, $key, $label);
        $field->maxLength = $maxLength;

        return $field;
    }

    public static function toggle(string $key, string $label): self
    {
        return (new self(self::TOGGLE, $key, $label))->default(false);
    }

    /**
     * @param  array<string, string>  $options  value => label (translated when shown)
     */
    public static function select(string $key, string $label, array $options): self
    {
        $field = new self(self::SELECT, $key, $label);
        $field->options = array_map('strval', $options);

        return $field;
    }

    /**
     * A colour, optionally set as CSS variables on the public site (like SiteBuilder::brandingColours()).
     *
     * @param  string|array<int, string>  $variables  e.g. "--color-signup-bg"
     */
    public static function colour(string $key, string $label, string|array $variables = []): self
    {
        $field = new self(self::COLOUR, $key, $label);

        foreach ((array) $variables as $variable) {
            if (! is_string($variable) || ! preg_match('/^--[a-z0-9-]+$/', $variable)) {
                throw new InvalidArgumentException("Branding colour [{$key}] needs CSS variables like --color-signup-bg.");
            }
        }

        $field->variables = array_values((array) $variables);

        return $field;
    }

    /**
     * One of the four Branding button styles, or Automatic. Read it with BrandingStyle::appButtonSlot().
     */
    public static function buttonStyle(string $key, string $label): self
    {
        return (new self(self::BUTTON_STYLE, $key, $label))->default(self::AUTOMATIC_BUTTON_STYLE);
    }

    /**
     * An image on the package's upload disk, in the Branding folder. Read its URL with Branding::appImageUrl().
     */
    public static function image(string $key, string $label): self
    {
        return new self(self::IMAGE, $key, $label);
    }

    /**
     * A banner with the same settings as the hero and inner banners (background, gradient, image and overlay, text
     * colours). Show it with <x-site-builder::banner variant="app_{key}">.
     */
    public static function banner(string $key, string $label): self
    {
        return new self(self::BANNER, $key, $label);
    }

    public function helperText(string $text): self
    {
        $this->helperText = $text;

        return $this;
    }

    /**
     * A short explanation shown when hovering the ? beside the setting, for a setting someone new might not understand.
     */
    public function tooltip(string $text): self
    {
        $this->tooltip = $text;

        return $this;
    }

    /**
     * The value used while nothing is saved (or the saved value fails its checks). Text defaults show as placeholders.
     */
    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    /**
     * For a text colour: the key of the (app) background colour it sits on, for the low-contrast warning.
     */
    public function contrastWith(string $backgroundKey): self
    {
        $this->contrastWith = $backgroundKey;

        return $this;
    }

    /**
     * @param  array<int, string>  $mimeTypes
     */
    public function fileTypes(array $mimeTypes): self
    {
        $this->fileTypes = array_values($mimeTypes);

        return $this;
    }

    public function maxSize(int $kilobytes): self
    {
        $this->maxSizeInKilobytes = $kilobytes;

        return $this;
    }

    public function getHelperText(): ?string
    {
        return $this->helperText === null ? null : __($this->helperText);
    }

    public function getTooltip(): ?string
    {
        return $this->tooltip === null ? null : __($this->tooltip);
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * @return array<int, string>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getContrastWith(): ?string
    {
        return $this->contrastWith;
    }

    /**
     * @return array<int, string>
     */
    public function getFileTypes(): array
    {
        return $this->fileTypes;
    }

    public function getMaxSize(): int
    {
        return $this->maxSizeInKilobytes;
    }

    public function settingKey(): string
    {
        return ColourGroup::settingKey($this->key);
    }

    /**
     * The banner's name for Branding::banner() and <x-site-builder::banner> (banner fields only).
     */
    public function bannerName(): string
    {
        return 'app_'.$this->key;
    }

    /**
     * Every setting the field saves: one, or a banner's whole set.
     *
     * @return array<int, string>
     */
    public function settingKeys(): array
    {
        if ($this->type !== self::BANNER) {
            return [$this->settingKey()];
        }

        return array_map(fn (string $field): string => Branding::bannerKey($this->bannerName(), $field), Branding::BANNER_FIELDS);
    }

    /**
     * The saved value if it passes the field's checks, otherwise null: text within its length, a toggle as a bool, a
     * listed choice, a 6-digit hex colour, a known button style, or an image path inside the Branding upload folder.
     */
    public function clean(mixed $value): mixed
    {
        return match ($this->type) {
            self::TEXT, self::TEXTAREA => (is_string($value) || is_int($value)) && trim((string) $value) !== ''
                ? mb_substr(trim((string) $value), 0, $this->maxLength)
                : null,
            self::TOGGLE => match (true) {
                in_array($value, [true, 1, '1'], true) => true,
                in_array($value, [false, 0, '0'], true) => false,
                default => null,
            },
            self::SELECT => (is_string($value) || is_int($value)) && array_key_exists((string) $value, $this->options) ? (string) $value : null,
            self::COLOUR => Colour::isValidHex($value) ? $value : null,
            self::BUTTON_STYLE => (is_string($value) || is_int($value)) && in_array((string) $value, [self::AUTOMATIC_BUTTON_STYLE, ...array_map('strval', BrandingStyle::BUTTON_SLOTS)], true)
                ? (string) $value
                : null,
            self::IMAGE => self::isBrandingUpload($value) ? $value : null,
            default => null,
        };
    }

    /**
     * Whether a stored path is a file in the Branding upload folder (not elsewhere on the disk, and no "..").
     */
    public static function isBrandingUpload(mixed $path): bool
    {
        $directory = app(SiteBuilder::class)->uploadDirectory(Branding::UPLOAD_SUBFOLDER);

        return is_string($path) && str_starts_with($path, $directory.'/') && ! str_contains($path, '..');
    }
}
