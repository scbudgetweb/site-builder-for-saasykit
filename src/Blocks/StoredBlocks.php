<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A page's or library item's blocks: JSON, read and written in today's shape (BlockSchema), so rows saved by 1.4 and
 * earlier open in the editor with their blocks in columns, and every block, row and column has a uid. The shape's
 * version is saved beside them, in "{attribute}_version". Stored with plain slashes, so upload paths match a plain LIKE
 * (PageService::deleteUnusedUploads(), lesson 13).
 *
 * @implements CastsAttributes<array<int|string, mixed>|null, mixed>
 */
class StoredBlocks implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        $blocks = is_string($value) ? json_decode($value, true) : null;

        return is_array($blocks) ? BlockSchema::read($blocks, (int) ($attributes["{$key}_version"] ?? 1)) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        return [
            $key => json_encode(
                is_array($value) ? BlockSchema::write($value) : $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
            "{$key}_version" => BlockSchema::VERSION,
        ];
    }
}
