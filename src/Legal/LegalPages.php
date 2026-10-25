<?php

namespace ScBudgetWeb\SiteBuilder\Legal;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Carbon;
use ScBudgetWeb\SiteBuilder\Models\Setting;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Admin-written legal pages (Website › Legal pages), one setting per page ("legal.{key}"). A page with no saved
 * content shows its built-in text, so a new site always has one.
 */
class LegalPages
{
    public function __construct(private SiteBuilder $siteBuilder) {}

    /**
     * @return array<string, LegalPage>
     */
    public function all(): array
    {
        return $this->siteBuilder->registeredLegalPages();
    }

    public function find(string $key): ?LegalPage
    {
        return $this->all()[$key] ?? null;
    }

    public function content(LegalPage $page): ?string
    {
        $content = $this->siteBuilder->settings()->get($page->settingKey());

        return is_string($content) ? $content : null;
    }

    public function hasCustomContent(LegalPage $page): bool
    {
        return ! self::isBlank($this->content($page));
    }

    /**
     * The saved content as safe HTML, with merge tags filled in. The renderer rebuilds the HTML from the editor's
     * schema, so anything the editor can't produce (scripts, styles, event handlers, javascript: links) is dropped.
     */
    public function html(LegalPage $page): string
    {
        $content = $this->content($page);

        if (self::isBlank($content)) {
            return '';
        }

        return RichContentRenderer::make($content)
            ->mergeTags($this->mergeTagValues())
            ->toHtml();
    }

    public function lastUpdated(LegalPage $page): ?Carbon
    {
        return $this->hasCustomContent($page) ? Setting::query()->where('key', $page->settingKey())->first()?->updated_at : null;
    }

    /**
     * Saving blank content removes the setting, so the page goes back to its built-in text. Unchanged content isn't
     * saved, so "Last updated" only changes on a real edit.
     */
    public function save(LegalPage $page, ?string $content): void
    {
        if (self::isBlank($content)) {
            $this->siteBuilder->settings()->forget([$page->settingKey()]);

            return;
        }

        if ($content === $this->content($page)) {
            return;
        }

        $this->siteBuilder->settings()->set([$page->settingKey() => $content]);
    }

    /**
     * The built-in text as HTML, to pre-fill the editor until content is saved.
     */
    public function defaultHtml(LegalPage $page): string
    {
        return $page->defaultView() !== null ? trim(view($page->defaultView())->render()) : '';
    }

    /**
     * @return array<string, string>
     */
    public function mergeTagLabels(): array
    {
        return [
            'site_name' => __('Site name'),
            'site_url' => __('Site URL'),
            'support_email' => __('Support email'),
            ...collect($this->all())->mapWithKeys(fn (LegalPage $page): array => [$page->key.'_url' => __(':page URL', ['page' => $page->label()])])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function mergeTagValues(): array
    {
        return [
            'site_name' => (string) config('app.name'),
            'site_url' => (string) config('app.url'),
            'support_email' => (string) config('app.support_email'),
            ...collect($this->all())->mapWithKeys(fn (LegalPage $page): array => [$page->key.'_url' => (string) $page->url()])->all(),
        ];
    }

    public static function isBlank(?string $content): bool
    {
        return trim(html_entity_decode(strip_tags((string) $content)), " \t\n\r\0\x0B\u{A0}") === '';
    }
}
