<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Settings\Settings;

/**
 * Presets: named styles for a block type, saved once and used by many blocks. A preset holds a block's style
 * settings only (StyleClipboard's "All styles" keys), never content. One preset per type can be the default, used by
 * every block of that type that hasn't picked another.
 *
 * A block saves its choice as "preset": a preset's id, "none" for no preset, or nothing for the type's default. When
 * the block is drawn, the preset's values fill in the settings the block leaves blank, so the block's own settings
 * always win. Changing a preset changes every block using it (saving a setting makes cached pages stale).
 *
 * Saved in the package's settings as "presets.{type}": {default: id|null, items: {id: {name, values}}}.
 */
class BlockPresets
{
    public const NONE = 'none';

    private const ID_PATTERN = '/^[a-z0-9]{8}$/';

    /**
     * The settings, from the container each time: this service lives for the whole app, but Settings is scoped (it keeps
     * what it read for one request or queue job only).
     */
    private function settings(): Settings
    {
        return app(Settings::class);
    }

    /**
     * Whether the signed-in user can make, change and delete presets (config site-builder.permissions.presets).
     */
    public static function canManage(): bool
    {
        $permission = config('site-builder.permissions.presets', 'update settings');

        return is_string($permission) && (bool) auth()->user()?->can($permission);
    }

    /**
     * The type's presets, id => name, in the order they were made.
     *
     * @return array<string, string>
     */
    public function names(string $type): array
    {
        return array_map(fn (array $preset): string => $preset['name'], $this->items($type));
    }

    public function defaultId(string $type): ?string
    {
        $default = $this->stored($type)['default'] ?? null;

        return is_string($default) && isset($this->items($type)[$default]) ? $default : null;
    }

    /**
     * The values of the preset the block uses (its own choice, or the type's default), or [] for none.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function valuesFor(string $type, array $data): array
    {
        $choice = $data['preset'] ?? null;

        if ($choice === self::NONE) {
            return [];
        }

        $items = $this->items($type);
        $id = is_string($choice) && isset($items[$choice]) ? $choice : $this->defaultId($type);

        return $id === null ? [] : $items[$id]['values'];
    }

    /**
     * The block's data with its preset's values under its own: each setting the block leaves blank, or that still
     * holds its default (PageBlock::fieldDefaults()), takes the preset's.
     *
     * @param  class-string<PageBlock>  $blockClass
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function apply(string $blockClass, array $data, bool $isNested = false): array
    {
        $values = $this->valuesFor($blockClass::name(), $data);

        return $values === [] ? $data : self::fillBlanks($data, $values, $blockClass::fieldDefaults($isNested));
    }

    /**
     * Saves a new preset and returns its id.
     *
     * @param  array<string, mixed>  $values
     */
    public function create(string $type, string $name, array $values, bool $isDefault = false): string
    {
        $stored = $this->stored($type);
        $id = Str::lower(Str::random(8));
        $stored['items'][$id] = ['name' => self::cleanName($name), 'values' => $values];

        if ($isDefault) {
            $stored['default'] = $id;
        }

        $this->save($type, $stored);

        return $id;
    }

    /**
     * Replaces a preset's values (its name stays).
     *
     * @param  array<string, mixed>  $values
     */
    public function update(string $type, string $id, array $values): void
    {
        $stored = $this->stored($type);

        if (isset($this->items($type)[$id])) {
            $stored['items'][$id]['values'] = $values;
            $this->save($type, $stored);
        }
    }

    public function rename(string $type, string $id, string $name): void
    {
        $stored = $this->stored($type);

        if (isset($this->items($type)[$id])) {
            $stored['items'][$id]['name'] = self::cleanName($name);
            $this->save($type, $stored);
        }
    }

    /**
     * Makes the preset the type's default, or (null) leaves the type without one.
     */
    public function makeDefault(string $type, ?string $id): void
    {
        $stored = $this->stored($type);
        $stored['default'] = $id !== null && isset($this->items($type)[$id]) ? $id : null;
        $this->save($type, $stored);
    }

    /**
     * Deletes a preset. Blocks that picked it use the type's default instead.
     */
    public function delete(string $type, string $id): void
    {
        $stored = $this->stored($type);
        unset($stored['items'][$id]);

        if (($stored['default'] ?? null) === $id) {
            $stored['default'] = null;
        }

        $this->save($type, $stored);
    }

    /**
     * $data with every blank setting (missing, null, '' or [], or the same as its default in $defaults, by dot key)
     * taken from $values, key by key, so nested settings (a padding's sides on each screen size) mix the two. Lists
     * (e.g. a repeater's items) are taken whole.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function fillBlanks(array $data, array $values, array $defaults = [], string $prefix = ''): array
    {
        foreach ($values as $key => $value) {
            $path = $prefix.$key;
            $own = $data[$key] ?? null;
            $isDefault = ! is_array($own) && array_key_exists($path, $defaults) && $own == $defaults[$path];

            if ($own === null || $own === '' || $own === [] || $isDefault) {
                $data[$key] = $value;
            } elseif (is_array($own) && is_array($value) && ! array_is_list($own) && ! array_is_list($value)) {
                $data[$key] = self::fillBlanks($own, $value, $defaults, $path.'.');
            }
        }

        return $data;
    }

    /**
     * @return array<string, array{name: string, values: array<string, mixed>}>
     */
    private function items(string $type): array
    {
        $items = [];

        foreach ((array) ($this->stored($type)['items'] ?? []) as $id => $preset) {
            if (is_string($id) && preg_match(self::ID_PATTERN, $id) === 1 && is_array($preset) && is_string($preset['name'] ?? null)) {
                $items[$id] = ['name' => $preset['name'], 'values' => is_array($preset['values'] ?? null) ? $preset['values'] : []];
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(string $type): array
    {
        $stored = $this->settings()->get(self::key($type));

        return is_array($stored) ? $stored : [];
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function save(string $type, array $stored): void
    {
        $this->settings()->set([self::key($type) => ($stored['items'] ?? []) === [] ? null : $stored]);
    }

    private static function key(string $type): string
    {
        return 'presets.'.$type;
    }

    private static function cleanName(string $name): string
    {
        return Str::limit(trim($name), 60, '') ?: __('Preset');
    }
}
