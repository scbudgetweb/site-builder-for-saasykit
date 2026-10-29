<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Forms;

use Closure;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;
use Filament\Support\Facades\FilamentAsset;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * A block's colour, as Colour picker: any colour, with transparency, or one of the site's global colours
 * (Branding › Brand › Colours). A global colour is saved as a reference ("gc:primary"), so changing it in Branding
 * changes every block using it; Unlink swaps the reference for its current value.
 *
 * Saved as #rrggbb, #rrggbbaa (see-through) or a reference, and read back through Colour::css(). The picker is
 * Filament's own (its colour picker module, with the transparency slider), so it looks like every other picker.
 */
class SiteColour extends Field
{
    use HasPlaceholder;

    protected string $view = 'site-builder::filament.forms.site-colour';

    protected bool|Closure $hasAlpha = true;

    protected bool|Closure $hasGlobals = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (filled($value) && ! $this->isValidValue($value)) {
                $fail($this->hasAlpha()
                    ? __('Enter a hex colour such as #1d41d5, or #1d41d580 to make it see-through.')
                    : __('Enter a 6-digit hex colour, for example #1d41d5.'));
            }
        });
    }

    /**
     * Whether it offers transparency (#rrggbbaa). Off for colours that are worked out into shades or contrast checks.
     */
    public function alpha(bool|Closure $condition = true): static
    {
        $this->hasAlpha = $condition;

        return $this;
    }

    /**
     * Whether it offers the global colours.
     */
    public function globals(bool|Closure $condition = true): static
    {
        $this->hasGlobals = $condition;

        return $this;
    }

    public function hasAlpha(): bool
    {
        return (bool) $this->evaluate($this->hasAlpha);
    }

    public function hasGlobals(): bool
    {
        return (bool) $this->evaluate($this->hasGlobals);
    }

    /**
     * The global colours for the picker's swatches: id, name, the saved colour (null for a built-in that isn't set)
     * and how to show it.
     *
     * @return array<int, array{id: string, name: string, colour: ?string, preview: string}>
     */
    public function getGlobalColours(): array
    {
        if (! $this->hasGlobals()) {
            return [];
        }

        $colours = [];

        foreach (app(Branding::class)->globalColours() as $id => $colour) {
            $colours[] = [
                'id' => $id,
                'name' => $colour['name'],
                'colour' => $colour['colour'],
                // A built-in colour that isn't set follows the theme: shown with the panel's own primary colour.
                'preview' => $colour['colour'] ?? ($id === 'primary' ? 'var(--primary-500)' : 'var(--gray-400)'),
            ];
        }

        return $colours;
    }

    public function getPickerSrc(): string
    {
        return FilamentAsset::getAlpineComponentSrc('color-picker', 'filament/forms');
    }

    protected function isValidValue(mixed $value): bool
    {
        return match (true) {
            Colour::isReference($value) => $this->hasGlobals(),
            $this->hasAlpha() => Colour::isValidHexWithAlpha($value),
            default => Colour::isValidHex($value),
        };
    }
}
