<?php

namespace ScBudgetWeb\SiteBuilder\Navigation;

use Closure;
use Throwable;

/**
 * A built-in page an admin can add to the header and footer menus (Website › Navigation › "A built-in page"), e.g.
 * Blog or Log in. The package registers the standard ones; host apps add theirs with SiteBuilder::links(). The URL is
 * worked out on every request, so a link can follow the app's settings. Never change a key once menus use it.
 */
final class Link
{
    private ?string $description = null;

    /** @var (Closure(): bool)|null */
    private ?Closure $availableWhen = null;

    /**
     * @param  string|Closure(): string  $label
     * @param  Closure(): string  $url
     */
    private function __construct(public readonly string $key, private string|Closure $label, private Closure $url) {}

    /**
     * @param  string|Closure(): string  $label  shown in the menu and the admin (translated when a string)
     * @param  Closure(): string  $url  resolved per request
     */
    public static function make(string $key, string|Closure $label, Closure $url): self
    {
        return new self($key, $label, $url);
    }

    /**
     * A short note for the admin picker, for links whose destination isn't obvious from the name.
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * When the link shows (e.g. only while the roadmap is switched on). Menus skip it otherwise.
     *
     * @param  Closure(): bool  $condition
     */
    public function availableWhen(Closure $condition): self
    {
        $this->availableWhen = $condition;

        return $this;
    }

    public function label(): string
    {
        return $this->label instanceof Closure ? ($this->label)() : __($this->label);
    }

    public function descriptionText(): ?string
    {
        return $this->description === null ? null : __($this->description);
    }

    /**
     * The URL, or null when the link isn't available (its condition fails, or its route doesn't exist).
     */
    public function url(): ?string
    {
        try {
            if ($this->availableWhen !== null && ($this->availableWhen)() !== true) {
                return null;
            }

            $url = ($this->url)();

            return is_string($url) && $url !== '' ? $url : null;
        } catch (Throwable) {
            return null;
        }
    }
}
