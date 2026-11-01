<?php

namespace ScBudgetWeb\SiteBuilder\Support;

/**
 * Livewire refuses an update whose property path has more than livewire.payload.max_nesting_depth parts (10 by
 * default). The rich editor sends each change at its place in the editor's nested document, two parts per level, and a
 * block's panel adds its own (mountedActions.0.data.items.{key}.answer), so a bullet list in a panel's repeater is
 * already past 10. Filament's own advice is to raise the limit; this raises it to what the builder needs, and never
 * lowers it or lifts it entirely. Set site-builder.livewire_nesting_depth to false to leave Livewire's setting alone.
 *
 * Livewire also refuses any upload over its temporary upload limit (12 MB by default), whatever the field allows, so
 * raiseUploadSize() raises that limit's max: rule to the largest file Audio blocks take (Website › Media). It never
 * lowers it, and each field still checks its own size. Set site-builder.livewire_upload_size to false to leave it alone.
 */
class LivewireLimits
{
    /** Room for a list nested a few levels deep inside a table cell, in a repeater, in a block opened from a Section. */
    public const NESTING_DEPTH = 48;

    /** The most an app can ask for here: the limit guards against very deep payloads, so it stays bounded. */
    public const MAX_NESTING_DEPTH = 128;

    public static function apply(): void
    {
        $wanted = config('site-builder.livewire_nesting_depth', self::NESTING_DEPTH);
        $current = config('livewire.payload.max_nesting_depth');

        if ($wanted === false || $wanted === null || ! is_numeric($wanted) || $current === null) {
            return;
        }

        $wanted = min((int) $wanted, self::MAX_NESTING_DEPTH);

        if (! is_numeric($current) || (int) $current < $wanted) {
            config(['livewire.payload.max_nesting_depth' => $wanted]);
        }
    }

    /** Livewire's own rules when the app sets none (FileUploadConfiguration::rules()). */
    private const LIVEWIRE_UPLOAD_RULES = ['required', 'file', 'max:12288'];

    /** The minutes an upload may take once it's over Livewire's standard size, so a large file on a slow line finishes. */
    private const LARGE_UPLOAD_MINUTES = 30;

    public static function raiseUploadSize(int $kilobytes): void
    {
        if (config('site-builder.livewire_upload_size', true) === false) {
            return;
        }

        $rules = config('livewire.temporary_file_upload.rules') ?? self::LIVEWIRE_UPLOAD_RULES;
        $rules = is_string($rules) ? explode('|', $rules) : $rules;

        if (! is_array($rules)) {
            return;
        }

        $raised = false;

        foreach ($rules as $index => $rule) {
            if (is_string($rule) && preg_match('/^max:(\d+)$/', $rule, $matches) === 1 && (int) $matches[1] < $kilobytes) {
                $rules[$index] = "max:{$kilobytes}";
                $raised = true;
            }
        }

        if (! $raised) {
            return;
        }

        $minutes = config('livewire.temporary_file_upload.max_upload_time');

        config([
            'livewire.temporary_file_upload.rules' => $rules,
            'livewire.temporary_file_upload.max_upload_time' => max(is_numeric($minutes) ? (int) $minutes : 5, self::LARGE_UPLOAD_MINUTES),
        ]);
    }
}
