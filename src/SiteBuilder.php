<?php

namespace ScBudgetWeb\SiteBuilder;

use BackedEnum;
use Closure;
use InvalidArgumentException;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Branding\BrandingSection;
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

    /** @var array<string, true> */
    private array $removedLinks = [];

    /** @var array{header?: array<int, array<string, mixed>>|Closure, footer?: array<string, mixed>|Closure, mobile?: array<string, mixed>|Closure} */
    private array $defaultMenus = [];

    /** @var array<string, LegalPage> */
    private array $legalPages = [];

    /** @var array<string, ColourGroup> */
    private array $brandingColours = [];

    /** @var array<string, BrandingSection> */
    private array $brandingSections = [];

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
     * Removes built-in links (the package's standard ones or anyone's) entirely, e.g. SiteBuilder::withoutLinks(['pricing']):
     * they're left out of the admin picker and the standard menus, whichever provider boots first. A saved menu that
     * already has one keeps it in its data, and the menu just doesn't show it.
     *
     * @param  array<int, string>  $keys
     */
    public function withoutLinks(array $keys): static
    {
        foreach ($keys as $key) {
            $this->removedLinks[$key] = true;
        }

        return $this;
    }

    /**
     * The built-in links, without the removed ones (whether or not they're available right now).
     *
     * @return array<string, Link>
     */
    public function registeredLinks(): array
    {
        return array_diff_key($this->links, $this->removedLinks);
    }

    public function link(?string $key): ?Link
    {
        return $key === null || isset($this->removedLinks[$key]) ? null : ($this->links[$key] ?? null);
    }

    /**
     * The site's standard menus, used while nothing is saved in Website › Navigation and by its Reset to default. Each is
     * in the saved format (see NavigationMenus), or a closure returning it, so labels are translated when it's read. A
     * null menu isn't changed by this call: until an app sets it, it's the package's standard one. Defaults are checked
     * like a saved menu (known fields, limits, links that apply), and saved menus are never changed by them.
     *
     * @param  array<int, array<string, mixed>>|(Closure(): array<int, array<string, mixed>>)|null  $header  header items
     * @param  array<string, mixed>|(Closure(): array<string, mixed>)|null  $footer  ['tagline', 'copyright', 'columns' => [['heading', 'links']]]
     * @param  array<string, mixed>|(Closure(): array<string, mixed>)|null  $mobile  ['open_from', 'show_logo', 'tagline']
     */
    public function defaultMenus(array|Closure|null $header = null, array|Closure|null $footer = null, array|Closure|null $mobile = null): static
    {
        foreach (['header' => $header, 'footer' => $footer, 'mobile' => $mobile] as $menu => $default) {
            if ($default !== null) {
                $this->defaultMenus[$menu] = $default;
            }
        }

        return $this;
    }

    /**
     * An app's standard menu ("header", "footer" or "mobile"), or null for the package's. A closure that throws or
     * returns something that isn't an array counts as not set, so a bad default can't break the page.
     *
     * @return array<int|string, mixed>|null
     */
    public function defaultMenu(string $menu): ?array
    {
        $default = $this->defaultMenus[$menu] ?? null;

        try {
            $default = $default instanceof Closure ? $default() : $default;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return is_array($default) ? $default : null;
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
        $group = ColourGroup::make($title, $colours, $contrastPairs);
        $this->assertNoSharedBrandingKeys(
            array_map(fn (string $key): string => ColourGroup::settingKey($key), array_keys($group->colours)),
            except: "colours:{$title}",
        );
        $this->brandingColours[$title] = $group;

        return $this;
    }

    /**
     * The app's own settings, as a tab on Website › Branding after the package's, e.g.
     * SiteBuilder::brandingSection('signup', 'Sign-up box', [BrandingField::text('signup_title', 'Title'), ...]).
     * Each field is saved as "branding.app_{key}" and read back, re-checked, with Branding::appValue(). Keys use a-z,
     * 0-9 and _ only, and can't be saved by two fields (or a field and a brandingColours() colour). A section with
     * the same key as an earlier one replaces it.
     *
     * @param  array<int, BrandingField>  $fields
     */
    public function brandingSection(string $key, string $title, array $fields, ?string $description = null, string|BackedEnum|null $icon = null): static
    {
        $section = BrandingSection::make($key, $title, $fields, $description, $icon);
        $this->assertNoSharedBrandingKeys($section->settingKeys(), except: "section:{$key}");
        $this->brandingSections[$key] = $section;

        return $this;
    }

    /**
     * @return array<string, BrandingSection>
     */
    public function registeredBrandingSections(): array
    {
        return $this->brandingSections;
    }

    /**
     * @param  array<int, string>  $settingKeys
     * @param  string  $except  the group or section being replaced, whose own keys don't count
     */
    private function assertNoSharedBrandingKeys(array $settingKeys, string $except): void
    {
        $taken = [];

        foreach ($this->brandingColours as $title => $group) {
            if ("colours:{$title}" !== $except) {
                foreach (array_keys($group->colours) as $colourKey) {
                    $taken[ColourGroup::settingKey($colourKey)] = true;
                }
            }
        }

        foreach ($this->brandingSections as $sectionKey => $section) {
            if ("section:{$sectionKey}" !== $except) {
                $taken += array_fill_keys($section->settingKeys(), true);
            }
        }

        foreach ($settingKeys as $settingKey) {
            if (isset($taken[$settingKey])) {
                throw new InvalidArgumentException("Branding setting [{$settingKey}] is already used by another Branding section or colour.");
            }
        }
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
