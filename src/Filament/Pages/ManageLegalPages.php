<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use ScBudgetWeb\SiteBuilder\Legal\LegalPage;
use ScBudgetWeb\SiteBuilder\Legal\LegalPages;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Admin › Website › Legal pages: a tab per legal page (Privacy Policy, Terms of Service, and any the app adds), each
 * in a rich editor with merge tags. A page with nothing saved opens with its built-in text, so admins edit it rather
 * than start blank. One Save button saves every tab.
 *
 * @property-read Schema $form
 */
class ManageLegalPages extends Page
{
    protected string $view = 'site-builder::filament.pages.manage-legal-pages';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'legal-pages';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return app(SiteBuilder::class)->navigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Legal pages');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Legal pages');
    }

    public function getSubheading(): ?string
    {
        return __('Your Privacy Policy and Terms of Service. They start with a standard template: check it against how your site works, and take legal advice if you\'re unsure.');
    }

    public static function canAccess(): bool
    {
        $permission = config('site-builder.permissions.legal', 'update settings');

        return is_string($permission) && (bool) auth()->user()?->can($permission);
    }

    public function mount(LegalPages $legalPages): void
    {
        $this->form->fill(collect($legalPages->all())->mapWithKeys(fn (LegalPage $page): array => [
            $page->key => $legalPages->hasCustomContent($page) ? $legalPages->content($page) : $legalPages->defaultHtml($page),
        ])->all());
    }

    public function form(Schema $schema): Schema
    {
        $legalPages = app(LegalPages::class);

        return $schema
            ->components([
                Form::make([
                    Tabs::make()
                        ->persistTabInQueryString('legal-tab')
                        ->tabs(array_values(array_map(fn (LegalPage $page): Tab => $this->tab($page, $legalPages), $legalPages->all()))),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Save changes'))
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(LegalPages $legalPages): void
    {
        $data = $this->form->getState();

        foreach ($legalPages->all() as $page) {
            $legalPages->save($page, is_string($data[$page->key] ?? null) ? $data[$page->key] : null);
        }

        // A cleared page is back on its template: show that in the editor too.
        $this->mount($legalPages);

        Notification::make()
            ->title(__('Legal pages saved'))
            ->success()
            ->send();
    }

    private function tab(LegalPage $page, LegalPages $legalPages): Tab
    {
        $url = $page->url();

        return Tab::make($page->label())
            ->icon($page->key === 'privacy_policy' ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedDocumentText)
            ->schema([
                RichEditor::make($page->key)
                    ->hiddenLabel()
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'link'],
                        ['h2', 'h3', 'h4', 'paragraph'],
                        ['bulletList', 'orderedList', 'blockquote', 'horizontalRule'],
                        ['mergeTags'],
                        ['clearFormatting', 'undo', 'redo'],
                    ])
                    ->mergeTags($legalPages->mergeTagLabels())
                    ->extraInputAttributes(['style' => 'min-height: 32rem;'])
                    ->helperText(($url ? __('Shown at :url.', ['url' => $url]).' ' : '')
                        .__('Type {{ to insert a merge tag (e.g. the site name or support email), which is filled in when the page is shown. Clear all the text and save to go back to the standard template.')),
            ]);
    }
}
