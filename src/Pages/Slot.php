<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Closure;

/**
 * A built-in public page that a published page can replace (Pages › Settings › Replaces built-in page), e.g. the
 * home page. The built-in page's view asks PageService::forSlot() and shows the page when there is one.
 * The theme registers "home"; host apps register theirs with SiteBuilder::slots().
 */
final class Slot
{
    private ?string $requiredBlock = null;

    /**
     * @param  string|Closure(): string  $label
     * @param  Closure(): string  $url
     */
    private function __construct(public readonly string $key, private string|Closure $label, private Closure $url) {}

    /**
     * @param  string|Closure(): string  $label  shown in the admin (translated when a string)
     * @param  Closure(): string  $url  the built-in page's URL, resolved per request
     */
    public static function make(string $key, string|Closure $label, Closure $url): self
    {
        return new self($key, $label, $url);
    }

    /**
     * The block a replacing page needs to keep the built-in page's purpose (e.g. a contact form). The editor warns
     * when it's missing.
     */
    public function requires(string $blockType): self
    {
        $this->requiredBlock = $blockType;

        return $this;
    }

    public function label(): string
    {
        return $this->label instanceof Closure ? ($this->label)() : __($this->label);
    }

    public function url(): string
    {
        return ($this->url)();
    }

    public function requiredBlock(): ?string
    {
        return $this->requiredBlock;
    }
}
