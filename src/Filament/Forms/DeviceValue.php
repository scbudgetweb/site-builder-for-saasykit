<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;
use ScBudgetWeb\SiteBuilder\Blocks\Design\DesignValue;

/**
 * One length (a number and a unit, e.g. "24px") that can differ by screen size, saved as {desktop, tablet, phone}, as
 * DesignValue reads it. One component, however many screen sizes, so a block's form stays light.
 *
 * The screen size being edited is shared by every such field on the page ($store.sbDevice, from the plugin's
 * design-fields script); in the visual builder it follows the canvas. An empty size shows the value it falls back to.
 */
class DeviceValue extends Field
{
    protected string $view = 'site-builder::filament.forms.device-value';

    /** @var array<int, string> */
    protected array $units = ['px'];

    protected float $minValue = 0;

    protected float $maxValue = 1000;

    protected float $step = 1;

    protected bool $isResponsive = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Holds its own hover value, so the panel's Hover state doesn't dim it (HoverMode).
        $this->extraFieldWrapperAttributes(['data-sb-hoverable' => 'true'], merge: true);

        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && $value !== [] && ! $this->isValidState($value)) {
                $fail(__('Enter numbers from :min to :max.', ['min' => $this->minValue, 'max' => $this->maxValue]));
            }
        });
    }

    /**
     * {desktop, tablet, phone}, each empty or a length in range with one of the units.
     */
    protected function isValidState(mixed $value): bool
    {
        foreach (is_array($value) ? $value : ['desktop' => $value] as $device => $length) {
            if (! $this->isValidPart($device, DesignValue::DEVICES, $length)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $allowedKeys
     */
    protected function isValidPart(int|string $key, array $allowedKeys, mixed $length): bool
    {
        return array_key_exists($key, $allowedKeys)
            && ($length === null || $length === '' || DesignValue::length($length, $this->units, $this->minValue, $this->maxValue) !== null);
    }

    /**
     * The units it offers, the first being the default. '' allows a plain number (e.g. a line height).
     *
     * @param  array<int, string>  $units
     */
    public function units(array $units): static
    {
        $this->units = $units;

        return $this;
    }

    public function range(float $min, float $max, float $step = 1): static
    {
        $this->minValue = $min;
        $this->maxValue = $max;
        $this->step = $step;

        return $this;
    }

    /**
     * One value for every screen size.
     */
    public function sameOnEveryScreen(bool $condition = true): static
    {
        $this->isResponsive = ! $condition;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    public function getMinValue(): float
    {
        return $this->minValue;
    }

    public function getMaxValue(): float
    {
        return $this->maxValue;
    }

    public function getStep(): float
    {
        return $this->step;
    }

    public function isResponsive(): bool
    {
        return $this->isResponsive;
    }
}
