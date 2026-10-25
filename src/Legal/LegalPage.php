<?php

namespace ScBudgetWeb\SiteBuilder\Legal;

use Closure;
use Throwable;

/**
 * A legal page an admin edits at Website › Legal pages, e.g. the Privacy Policy. The package registers the Privacy
 * Policy and Terms of Service; host apps add theirs (e.g. a cookie policy) with SiteBuilder::legalPages() and show it
 * with <x-site-builder::legal-page key="..." /> in their own view. Never change a key once content is saved.
 */
final class LegalPage
{
    /**
     * @param  string|Closure(): string  $label
     * @param  Closure(): string  $url
     */
    private function __construct(public readonly string $key, private string|Closure $label, private Closure $url, private ?string $defaultView) {}

    /**
     * @param  string|Closure(): string  $label  the page title (translated when a string)
     * @param  Closure(): string  $url  where the page is shown, resolved per request
     * @param  string|null  $defaultView  the built-in text, shown until content is saved (and to pre-fill the editor)
     */
    public static function make(string $key, string|Closure $label, Closure $url, ?string $defaultView = null): self
    {
        return new self($key, $label, $url, $defaultView);
    }

    public function label(): string
    {
        return $this->label instanceof Closure ? ($this->label)() : __($this->label);
    }

    /**
     * The page's URL, or null when its route doesn't exist.
     */
    public function url(): ?string
    {
        try {
            return ($this->url)();
        } catch (Throwable) {
            return null;
        }
    }

    public function defaultView(): ?string
    {
        return $this->defaultView;
    }

    public function settingKey(): string
    {
        return 'legal.'.$this->key;
    }
}
