<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Schemas\TemplateForm;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;
use ScBudgetWeb\SiteBuilder\Templates\HeaderBehaviour;

/**
 * Theme builder › Visual builder: a template built like a page, shown on its own (the site's header and footer
 * hidden), with its type's own blocks (a menu, the logo, account links…) in the pickers. Saving puts it live wherever
 * it applies, if it's on.
 */
class TemplateVisualEditor extends VisualEditor
{
    protected static string $resource = TemplateResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->template()->name;
    }

    protected function templateTypeBeingEdited(): ?string
    {
        return $this->template()->type;
    }

    public function statusText(): string
    {
        $drafts = app(VisualDrafts::class);
        $draft = $this->draft();

        if ($drafts->isOutOfDate($draft, $this->template())) {
            return __('This template has been saved somewhere else since you started. Saving will replace those changes.');
        }

        return $drafts->hasChanges($draft, $this->template())
            ? __('Unsaved changes, kept as you go. Visitors see them once you save.')
            : __('Click a block to change it, or use + to add the logo, menu and more.');
    }

    /**
     * A blog template's canvas shows the latest post (or a sample) in its blocks.
     */
    protected function canvasPage(array $blocks): Page
    {
        app(Blog::class)->useSampleFor($this->template()->type);

        return $this->template()->previewPage($blocks);
    }

    /**
     * Header settings, in the toolbar for a header: how it behaves as the page scrolls. Saved straight away (not in the
     * draft), then the canvas reloads to show them on its sample page. This person's draft stays up to date: saving
     * the settings isn't someone else's change to the blocks.
     */
    public function headerSettingsAction(): Action
    {
        return Action::make('headerSettings')
            ->label(__('Header settings'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->visible(fn (): bool => $this->template()->type === 'header')
            ->tooltip(__('Whether it stays at the top, shrinks, or sits over the first section'))
            ->modalHeading(__('Header settings'))
            ->modalDescription(__('How this header behaves as the page scrolls. Saved straight away, and shown on the sample page under the header.'))
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (): array => HeaderBehaviour::clean($this->template()->settings))
            ->schema(HeaderBehaviour::fields(forTemplate: true))
            ->modalSubmitActionLabel(__('Save settings'))
            ->action(function (array $data): void {
                $template = $this->template();
                $draft = $this->draft();
                $wasUpToDate = ! app(VisualDrafts::class)->isOutOfDate($draft, $template);

                $template->update(['settings' => HeaderBehaviour::clean($data)]);

                if ($wasUpToDate && $draft->exists) {
                    $draft->forceFill(['base_updated_at' => $template->updated_at])->save();
                }

                $this->resetDraft();
                $this->dispatch('site-builder-canvas-reload');

                Notification::make()
                    ->title(__('Header settings saved'))
                    ->body($template->is_active ? __('Visitors see them now, wherever this header is shown.') : __('The header is off, so visitors see them once it\'s turned on.'))
                    ->success()
                    ->send();
            });
    }

    protected function publishTexts(): array
    {
        $template = $this->template();
        $options = TemplateForm::appliesToOptions($template->type);
        $where = $options[$template->applies_to] ?? $options[Template::EVERYWHERE];
        $isOutOfDate = app(VisualDrafts::class)->isOutOfDate($this->draft(), $template);

        $warning = implode(' ', array_filter([
            $isOutOfDate ? __('Someone saved this template (:time) since you started, and saving replaces their changes.', ['time' => $template->updated_at?->diffForHumans() ?? '']) : null,
            $template->is_active ? __('It\'s on, so visitors see it straight away (:where).', ['where' => mb_strtolower($where)]) : null,
        ]));

        return [
            'label' => __('Save'),
            'warning' => $warning === '' ? null : $warning,
            'title' => __('Template saved'),
            'body' => $template->is_active
                ? __('Visitors see it now (:where).', ['where' => mb_strtolower($where)])
                : __('It\'s off: turn it on in the Theme builder list when it\'s ready.'),
        ];
    }

    private function template(): Template
    {
        $template = $this->getRecord();
        abort_unless($template instanceof Template, 404);

        return $template;
    }
}
