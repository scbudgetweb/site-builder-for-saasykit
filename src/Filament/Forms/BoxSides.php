<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Forms;

use ScBudgetWeb\SiteBuilder\Blocks\Design\DesignValue;

/**
 * Four lengths, one per side (top, right, bottom, left) or corner (top left, top right, bottom right, bottom left),
 * that can be linked so typing one sets them all, and can differ by screen size: saved as
 * {desktop: {top, right, bottom, left}, tablet: {…}, phone: {…}}, each optional. One component for all of them.
 */
class BoxSides extends DeviceValue
{
    public const SIDES = ['top', 'right', 'bottom', 'left'];

    public const CORNERS = ['top_left', 'top_right', 'bottom_right', 'bottom_left'];

    protected string $view = 'site-builder::filament.forms.box-sides';

    /** @var array<int, string> */
    protected array $parts = self::SIDES;

    /**
     * {desktop: {side: length…}, tablet: …, phone: …}, each part empty or a length in range with one of the units.
     */
    protected function isValidState(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $device => $parts) {
            if (! array_key_exists($device, DesignValue::DEVICES) || ($parts !== null && ! is_array($parts))) {
                return false;
            }

            foreach ($parts ?? [] as $part => $length) {
                if (! $this->isValidPart($part, array_flip($this->parts), $length)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The four corners instead of the four sides (for corner radius).
     */
    public function corners(): static
    {
        $this->parts = self::CORNERS;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * @return array<string, string>
     */
    public function getPartLabels(): array
    {
        return $this->parts === self::CORNERS
            ? ['top_left' => __('Top left'), 'top_right' => __('Top right'), 'bottom_right' => __('Bottom right'), 'bottom_left' => __('Bottom left')]
            : ['top' => __('Top'), 'right' => __('Right'), 'bottom' => __('Bottom'), 'left' => __('Left')];
    }
}
