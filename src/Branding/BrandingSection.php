<?php

namespace ScBudgetWeb\SiteBuilder\Branding;

use BackedEnum;
use InvalidArgumentException;

/**
 * An app's own part of Website › Branding (SiteBuilder::brandingSection()): a tab after the package's, holding the
 * app's settings, e.g. a sign-up box's text, its button style and its banner.
 */
final class BrandingSection
{
    /**
     * @param  array<string, BrandingField>  $fields
     */
    private function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly array $fields,
        public readonly ?string $description,
        public readonly string|BackedEnum|null $icon,
    ) {}

    /**
     * @param  array<int, BrandingField>  $fields
     */
    public static function make(string $key, string $title, array $fields, ?string $description = null, string|BackedEnum|null $icon = null): self
    {
        if (! preg_match('/^[a-z0-9_]+$/', $key)) {
            throw new InvalidArgumentException("Branding section key [{$key}] may only use a-z, 0-9 and _.");
        }

        $keyed = [];
        $settingKeys = [];

        foreach ($fields as $field) {
            if (! $field instanceof BrandingField) {
                throw new InvalidArgumentException("Branding section [{$key}] may only hold ".BrandingField::class.'s.');
            }

            foreach ($field->settingKeys() as $settingKey) {
                if (isset($settingKeys[$settingKey])) {
                    throw new InvalidArgumentException("Branding section [{$key}] saves [{$settingKey}] twice.");
                }

                $settingKeys[$settingKey] = true;
            }

            $keyed[$field->key] = $field;
        }

        return new self($key, $title, $keyed, $description, $icon);
    }

    /**
     * @return array<int, string>
     */
    public function settingKeys(): array
    {
        return array_merge([], ...array_map(fn (BrandingField $field): array => $field->settingKeys(), array_values($this->fields)));
    }
}
