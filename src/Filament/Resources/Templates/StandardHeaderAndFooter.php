<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates;

use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageBranding;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageNavigation;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Templates\TemplateLayouts;

/**
 * One home for each piece of the header and footer:
 *  - the Theme builder list shows the standard header and footer as cards: where they're shown (or which template is
 *    shown instead), with Edit links (Navigation), Edit colours (Branding) and Customise, which makes a template from
 *    the standard one (TemplateLayouts::STANDARD) and opens it in the visual builder. When a template is shown on every
 *    page instead, Customise gives way to Edit "<name>", which opens that template;
 *  - Navigation and Branding say when a template is shown instead, naming it and linking to it.
 */
class StandardHeaderAndFooter
{
    public const TYPES = ['header', 'footer'];

    /**
     * The active templates of this type shown in place of the standard one: one for every page (the most recently
     * saved), and those for some pages.
     *
     * @return array{everywhere: Template|null, some: Collection<int, Template>}
     */
    public static function replacements(string $type): array
    {
        $templates = Template::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->latest('updated_at')
            ->get(['id', 'name', 'type', 'applies_to', 'updated_at']);

        return [
            'everywhere' => $templates->firstWhere('applies_to', Template::EVERYWHERE),
            'some' => $templates->where('applies_to', '!=', Template::EVERYWHERE)->values(),
        ];
    }

    /**
     * Where the standard header or footer is shown, for its card in the Theme builder list.
     */
    public static function status(string $type): string
    {
        ['everywhere' => $everywhere, 'some' => $some] = self::replacements($type);

        return match (true) {
            $everywhere !== null => __('Not shown: the template ":name" is shown on every page instead.', ['name' => $everywhere->name]),
            $some->isNotEmpty() => __('Shown everywhere except where a template applies (:names).', ['names' => self::names($some)]),
            default => __('Shown on every page.'),
        };
    }

    /**
     * The cards for the Theme builder list.
     */
    public static function cards(): Grid
    {
        return Grid::make(['default' => 1, 'lg' => 2])->schema(array_map(fn (string $type): Section => Section::make($type === 'header' ? __('Standard header') : __('Standard footer'))
            ->icon($type === 'header' ? Heroicon::OutlinedBars3BottomLeft : Heroicon::OutlinedBars3BottomRight)
            ->description(fn (): string => self::status($type))
            ->compact()
            ->schema([
                Text::make($type === 'header'
                    ? __('Its links are set in Website › Navigation, its colours and how it behaves as the page scrolls in Branding › Header & footer.')
                    : __('Its tagline, link columns and copyright line are set in Website › Navigation, its colours in Branding › Header & footer.')),
                Actions::make([
                    Action::make("{$type}Links")
                        ->label(__('Edit links'))
                        ->icon(Heroicon::OutlinedLink)
                        ->color('gray')
                        ->size('sm')
                        ->url(self::linksUrl($type)),
                    Action::make("{$type}Colours")
                        ->label(__('Edit colours'))
                        ->icon(Heroicon::OutlinedSwatch)
                        ->color('gray')
                        ->size('sm')
                        ->url(self::coloursUrl()),
                    self::customiseAction($type),
                    self::editInUseAction($type),
                ])->key("{$type}Actions"),
            ]), self::TYPES))
            ->columnSpanFull();
    }

    /**
     * Customise: a template made from the standard header or footer, as blocks (a header keeps the standard header's
     * behaviour from Branding), and opened in the visual builder. It's off until it's turned on, so visitors keep seeing
     * the standard one meanwhile.
     */
    public static function customiseAction(string $type): Action
    {
        return Action::make("{$type}Customise")
            ->label(__('Customise'))
            ->icon(Heroicon::OutlinedPaintBrush)
            ->size('sm')
            ->visible(fn (): bool => TemplateResource::canCreate() && self::replacements($type)['everywhere'] === null)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedPaintBrush)
            ->modalHeading($type === 'header' ? __('Customise the standard header?') : __('Customise the standard footer?'))
            ->modalDescription($type === 'header'
                ? __('This makes a header template from the standard header, as blocks you can change, and opens it in the visual builder. It\'s off until you turn it on, so visitors keep seeing the standard header meanwhile.')
                : __('This makes a footer template from the standard footer, as blocks you can change, and opens it in the visual builder. It\'s off until you turn it on, so visitors keep seeing the standard footer meanwhile.'))
            ->modalSubmitActionLabel(__('Customise'))
            ->action(function (Action $action) use ($type): void {
                $template = Template::query()->create([
                    'name' => $type === 'header' ? __('My header') : __('My footer'),
                    'type' => $type,
                    'is_active' => false,
                    'applies_to' => Template::EVERYWHERE,
                    'settings' => $type === 'header' ? app(Branding::class)->get(Branding::HEADER_BEHAVIOUR) : null,
                    'blocks' => TemplateLayouts::blocks($type, TemplateLayouts::STANDARD),
                ]);

                $action->redirect(TemplateResource::getUrl('visual', ['record' => $template]));
            });
    }

    /**
     * Edit "<name>": when a template is shown on every page instead of the standard one, the card opens it in the visual
     * builder rather than offering to make another (Customise). With templates on some pages only, the standard one
     * still shows everywhere else, so Customise stays.
     */
    public static function editInUseAction(string $type): Action
    {
        return Action::make("{$type}EditInUse")
            ->label(fn (): string => __('Edit ":name"', ['name' => self::replacements($type)['everywhere']?->name]))
            ->icon(Heroicon::OutlinedPaintBrush)
            ->size('sm')
            ->visible(fn (): bool => ($template = self::replacements($type)['everywhere']) !== null && TemplateResource::canEdit($template))
            ->url(fn (): ?string => ($template = self::replacements($type)['everywhere']) !== null
                ? TemplateResource::getUrl('visual', ['record' => $template])
                : null);
    }

    /**
     * Navigation's and Branding's notice when a template is shown instead of the standard header or footer, or null.
     * $page says which page it's on, for what still applies to the template.
     */
    public static function notice(string $type, string $page): ?Callout
    {
        ['everywhere' => $everywhere, 'some' => $some] = self::replacements($type);

        if ($everywhere === null && $some->isEmpty()) {
            return null;
        }

        $template = $everywhere ?? $some->first();
        $where = $everywhere !== null
            ? ($type === 'header'
                ? __('The header template ":name" is shown on every page instead of the standard header.', ['name' => $everywhere->name])
                : __('The footer template ":name" is shown on every page instead of the standard footer.', ['name' => $everywhere->name]))
            : ($type === 'header'
                ? __('Header templates are shown on some pages (:names); the standard header shows everywhere else.', ['names' => self::names($some)])
                : __('Footer templates are shown on some pages (:names); the standard footer shows everywhere else.', ['names' => self::names($some)]));
        $still = match ([$page, $type]) {
            ['navigation', 'header'] => __('A template\'s Header bar and Menu blocks still show the links set here.'),
            ['navigation', 'footer'] => __('A template\'s Footer links and Copyright blocks still show what\'s set here.'),
            ['branding', 'header'] => __('A template\'s blocks still use these colours unless they have colours of their own. How it behaves as the page scrolls is under Header settings in its visual builder.'),
            default => __('A template\'s blocks still use these colours unless they have colours of their own.'),
        };

        return Callout::make($where)
            ->description($still)
            ->info()
            ->actions([
                Action::make("{$type}TemplateOpen")
                    ->label(__('Open ":name"', ['name' => $template->name]))
                    ->icon(Heroicon::OutlinedPaintBrush)
                    ->size('sm')
                    ->url(TemplateResource::getUrl('visual', ['record' => $template])),
            ]);
    }

    public static function linksUrl(string $type): string
    {
        return ManageNavigation::getUrl(['navigation-tab' => $type]);
    }

    public static function coloursUrl(): string
    {
        return ManageBranding::getUrl(['branding-tab' => 'header-footer']);
    }

    /**
     * @param  Collection<int, Template>  $templates
     */
    private static function names(Collection $templates): string
    {
        return $templates->map(fn (Template $template): string => '"'.$template->name.'"')->implode(', ');
    }
}
