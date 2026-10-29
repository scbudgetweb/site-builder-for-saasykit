<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

/**
 * What a block's design options (BlockDesign) add to its HTML: classes from a fixed list and CSS custom properties,
 * for three targets, plus the Branding fonts it uses.
 *
 *  - "section": the block's outer element (its full-width section);
 *  - "box": the panel when the block has one, otherwise the section (borders, corners, hover colours);
 *  - "container": the element that sets the content's width.
 *
 * The package's CSS (resources/css/design) reads the properties, and a rule only applies when its class is present.
 * Nothing here trusts its input: classes and property names must look like the package's own, and values that could
 * break out of a style attribute or load a file are left out (lesson 11).
 */
final class DesignOutput
{
    public const TARGETS = ['section', 'box', 'container'];

    private const CLASS_PATTERN = '/^sb-[a-z0-9-]+$/';

    private const PROPERTY_PATTERN = '/^--sb-[a-z0-9-]+$/';

    /** Numbers, units, hex colours, font names and simple functions such as rgb() and var(--x); nothing else. */
    private const VALUE_PATTERN = '/^[A-Za-z0-9#.,%()\/\s\'"-]+$/';

    /** @var array<string, array<int, string>> */
    private array $classes = [];

    /** @var array<string, array<string, string>> */
    private array $properties = [];

    /** @var array<int, string> */
    private array $fonts = [];

    public function addClass(string $target, string $class): self
    {
        if (in_array($target, self::TARGETS, true) && preg_match(self::CLASS_PATTERN, $class) === 1) {
            $this->classes[$target][] = $class;
        }

        return $this;
    }

    public function setProperty(string $target, string $name, string $value): self
    {
        if (in_array($target, self::TARGETS, true) && preg_match(self::PROPERTY_PATTERN, $name) === 1 && self::isSafeValue($value)) {
            $this->properties[$target][$name] = $value;
        }

        return $this;
    }

    /**
     * A Branding font's slug (checked against the available fonts when the page loads its stylesheet).
     */
    public function addFont(string $slug): self
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $slug) === 1) {
            $this->fonts[] = $slug;
        }

        return $this;
    }

    public function merge(self $other): self
    {
        foreach (self::TARGETS as $target) {
            $this->classes[$target] = [...($this->classes[$target] ?? []), ...($other->classes[$target] ?? [])];
            $this->properties[$target] = [...($this->properties[$target] ?? []), ...($other->properties[$target] ?? [])];
        }

        $this->fonts = [...$this->fonts, ...$other->fonts];

        return $this;
    }

    public function classes(string $target): string
    {
        return implode(' ', array_unique($this->classes[$target] ?? []));
    }

    public function style(string $target): string
    {
        return implode(' ', array_map(
            fn (string $name, string $value): string => "{$name}: {$value};",
            array_keys($this->properties[$target] ?? []),
            $this->properties[$target] ?? [],
        ));
    }

    /**
     * @return array<int, string>
     */
    public function fonts(): array
    {
        return array_values(array_unique($this->fonts));
    }

    public function isEmpty(): bool
    {
        foreach (self::TARGETS as $target) {
            if (($this->classes[$target] ?? []) !== [] || ($this->properties[$target] ?? []) !== []) {
                return false;
            }
        }

        return true;
    }

    private static function isSafeValue(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= 200
            && preg_match(self::VALUE_PATTERN, $value) === 1
            && stripos($value, 'url(') === false
            && stripos($value, 'expression') === false;
    }
}
