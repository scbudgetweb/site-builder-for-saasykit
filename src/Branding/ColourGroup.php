<?php

namespace ScBudgetWeb\SiteBuilder\Branding;

use InvalidArgumentException;

/**
 * An app's own element colours on Branding › Header & footer (SiteBuilder::brandingColours()), e.g. a booking
 * widget's background and text. Each colour is saved as "branding.app_{key}" and sets the given CSS variables on the
 * public site; text => background pairs get the same low-contrast warning as the built-in colours.
 */
final class ColourGroup
{
    /**
     * @param  array<string, array{label: string, variables: array<int, string>}>  $colours
     * @param  array<string, string>  $contrastPairs  text colour key => background colour key
     */
    private function __construct(public readonly string $title, public readonly array $colours, public readonly array $contrastPairs) {}

    /**
     * @param  array<string, array{0: string, 1: string|array<int, string>}>  $colours  key => [label, CSS variable(s)]
     * @param  array<string, string>  $contrastPairs  text colour key => background colour key
     */
    public static function make(string $title, array $colours, array $contrastPairs = []): self
    {
        $normalised = [];

        foreach ($colours as $key => [$label, $variables]) {
            $variables = (array) $variables;

            if (! preg_match('/^[a-z0-9_]+$/', (string) $key)) {
                throw new InvalidArgumentException("Branding colour key [{$key}] may only use a-z, 0-9 and _.");
            }

            foreach ($variables as $variable) {
                if (! is_string($variable) || ! preg_match('/^--[a-z0-9-]+$/', $variable)) {
                    throw new InvalidArgumentException("Branding colour [{$key}] needs CSS variables like --color-booking-bg.");
                }
            }

            $normalised[(string) $key] = ['label' => $label, 'variables' => array_values($variables)];
        }

        foreach ($contrastPairs as $text => $background) {
            if (! isset($normalised[$text], $normalised[$background])) {
                throw new InvalidArgumentException("Branding colour contrast pair [{$text} => {$background}] must use colours from the same group.");
            }
        }

        return new self($title, $normalised, $contrastPairs);
    }

    public static function settingKey(string $key): string
    {
        return 'branding.app_'.$key;
    }
}
