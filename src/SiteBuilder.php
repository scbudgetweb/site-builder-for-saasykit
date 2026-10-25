<?php

namespace ScBudgetWeb\SiteBuilder;

use Closure;
use InvalidArgumentException;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Branding\ColourGroup;
use ScBudgetWeb\SiteBuilder\Legal\LegalPage;
use ScBudgetWeb\SiteBuilder\Navigation\Link;
use ScBudgetWeb\SiteBuilder\Pages\Slot;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use Throwable;

/**
 * The package's entry point (also the SiteBuilder facade). Host apps register their own blocks, replaceable built-in
 * pages, menu links and legal pages here, from a service provider's boot().
 */
class SiteBuilder
{
    /** @var array<string, Slot> */
    private array $slots = [];

    /** @var array<int, class-string<PageBlock>> */
    private array $blocks = [];

    /** @var array<string, Link> */
    private array $links = [];

    /** @var array<string, LegalPage> */
    private array $legalPages = [];

    /** @var array<string, ColourGroup> */
    private array $brandingColours = [];

    /** @var array<string, array{0: string, 1: Closure(): (int|float)}> */
    private array $stats = [];

    /** @var (Closure(string): bool)|null */
    private ?Closure $consentChecker = null;

    public function __construct(private Settings $settings) {}

    public function settings(): Settings
    {
        return $this->settings;
    }

    public function navigationGroup(): string
    {
        return __((string) config('site-builder.navigation.group', 'Website'));
    }

    public function uploadDisk(): string
    {
        return (string) config('site-builder.uploads.disk', 'public');
    }

    public function uploadDirectory(string $subfolder = ''): string
    {
        return trim(config('site-builder.uploads.directory', 'site-builder').'/'.$subfolder, '/');
    }

    /**
     * Built-in pages a page can replace. A slot with the same key as an earlier one replaces it.
     *
     * @param  array<int, Slot>  $slots
     */
    public function slots(array $slots): static
    {
        foreach ($slots as $slot) {
            $this->slots[$slot->key] = $slot;
        }

        return $this;
    }

    /**
     * @return array<string, Slot>
     */
    public function registeredSlots(): array
    {
        return $this->slots;
    }

    public function slot(?string $key): ?Slot
    {
        return $key === null ? null : ($this->slots[$key] ?? null);
    }

    /**
     * Built-in pages for the header and footer menus. A link with the same key as an earlier one replaces it.
     *
     * @param  array<int, Link>  $links
     */
    public function links(array $links): static
    {
        foreach ($links as $link) {
            $this->links[$link->key] = $link;
        }

        return $this;
    }

    /**
     * @return array<string, Link>
     */
    public function registeredLinks(): array
    {
        return $this->links;
    }

    public function link(?string $key): ?Link
    {
        return $key === null ? null : ($this->links[$key] ?? null);
    }

    /**
     * Legal pages edited at Website › Legal pages. A page with the same key as an earlier one replaces it.
     *
     * @param  array<int, LegalPage>  $pages
     */
    public function legalPages(array $pages): static
    {
        foreach ($pages as $page) {
            $this->legalPages[$page->key] = $page;
        }

        return $this;
    }

    /**
     * @return array<string, LegalPage>
     */
    public function registeredLegalPages(): array
    {
        return $this->legalPages;
    }

    /**
     * The app's own element colours, as a group on Branding › Header & footer. Each colour sets CSS variables on the
     * public site, e.g. SiteBuilder::brandingColours('Booking widget', ['booking_bg' => ['Background colour',
     * '--color-booking-bg'], 'booking_text' => ['Text colour', '--color-booking-text']], ['booking_text' => 'booking_bg']).
     * A group with the same title as an earlier one replaces it.
     *
     * @param  array<string, array{0: string, 1: string|array<int, string>}>  $colours  key => [label, CSS variable(s)]
     * @param  array<string, string>  $contrastPairs  text colour key => background colour key, checked for contrast
     */
    public function brandingColours(string $title, array $colours, array $contrastPairs = []): static
    {
        $this->brandingColours[$title] = ColourGroup::make($title, $colours, $contrastPairs);

        return $this;
    }

    /**
     * @return array<string, ColourGroup>
     */
    public function registeredBrandingColours(): array
    {
        return $this->brandingColours;
    }

    /**
     * The app's own blocks, added to the block picker after the core blocks.
     *
     * @param  array<int, class-string<PageBlock>>  $blocks
     */
    public function blocks(array $blocks): static
    {
        foreach ($blocks as $block) {
            if (! is_subclass_of($block, PageBlock::class)) {
                throw new InvalidArgumentException("{$block} must extend ".PageBlock::class.'.');
            }

            $this->blocks[] = $block;
        }

        return $this;
    }

    /**
     * @return array<int, class-string<PageBlock>>
     */
    public function registeredBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Live figures for the Stats block, as key => [label, callback], e.g. ['halls' => ['Halls on the site', fn () => Hall::count()]].
     * The admin picks them as "Live: {label}"; the result is cached for ten minutes.
     *
     * @param  array<string, array{0: string, 1: Closure(): (int|float)}>  $sources
     */
    public function stats(array $sources): static
    {
        foreach ($sources as $key => $source) {
            $this->stats[(string) $key] = $source;
        }

        return $this;
    }

    /**
     * @return array<string, array{0: string, 1: Closure(): (int|float)}>
     */
    public function statSources(): array
    {
        return $this->stats;
    }

    /**
     * Says whether the visitor has allowed a cookie category (e.g. "marketing"), from the app's cookie banner.
     * Until one is set, nothing is allowed: videos are click-to-play (lesson 14).
     *
     * @param  Closure(string): bool  $checker
     */
    public function consentChecker(Closure $checker): static
    {
        $this->consentChecker = $checker;

        return $this;
    }

    public function consentAllows(string $category): bool
    {
        try {
            return $this->consentChecker !== null && ($this->consentChecker)($category) === true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
