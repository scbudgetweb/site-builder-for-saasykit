<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages;

use Illuminate\Contracts\Support\Htmlable;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Schemas\TemplateForm;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

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

    protected function canvasPage(array $blocks): Page
    {
        return $this->template()->previewPage($blocks);
    }

    protected function publishTexts(): array
    {
        $template = $this->template();
        $where = __(TemplateForm::APPLIES_TO[$template->applies_to] ?? TemplateForm::APPLIES_TO[Template::EVERYWHERE]);
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
