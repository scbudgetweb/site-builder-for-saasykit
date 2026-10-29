<?php

namespace ScBudgetWeb\SiteBuilder\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Branding\BrandingStyle;
use ScBudgetWeb\SiteBuilder\Mail\MailingListSubscribed;
use ScBudgetWeb\SiteBuilder\MailingLists\MailingListException;
use ScBudgetWeb\SiteBuilder\MailingLists\MailingListService;
use ScBudgetWeb\SiteBuilder\Models\MailingListProvider;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * The mailing list sign-up form (<livewire:site-builder.mailing-list-signup />, and the Mailing list block). It adds
 * the email to the list chosen in Website › Mailing lists, or the one a block chose, and can tell an admin
 * (site-builder.mailing_lists.notify_email). Styled in Branding › Mailing list; renders nothing until a service and
 * list are set.
 *
 * What a block chooses (its list, a name field, a consent tick box, a page to go to after) is #[Locked]: it's in
 * Livewire's signed snapshot, so a visitor can't change it, e.g. to sign people up to another list on the account.
 * Spam protection: a honeypot, a minimum time on the page and a per-IP rate limit.
 */
class MailingListSignup extends Component
{
    public const MINIMUM_SECONDS_ON_PAGE = 3;

    public const MAX_SIGNUPS_PER_HOUR = 5;

    /** A block's own wording; blank uses Branding's. */
    #[Locked]
    public ?string $heading = null;

    #[Locked]
    public ?string $intro = null;

    #[Locked]
    public ?string $buttonText = null;

    #[Locked]
    public ?string $successMessage = null;

    /** Another list on the account (a block's choice); null for the default one. */
    #[Locked]
    public ?string $listId = null;

    #[Locked]
    public bool $asksName = false;

    #[Locked]
    public bool $requiresConsent = false;

    /** A page to go to after signing up (checked against the allowed link kinds), instead of the thank-you message. */
    #[Locked]
    public ?string $redirectUrl = null;

    public string $email = '';

    public string $firstName = '';

    public string $lastName = '';

    public bool $consent = false;

    /** Honeypot: hidden from people, so anything typed here came from a bot. */
    public string $website = '';

    #[Locked]
    public int $renderedAt = 0;

    #[Locked]
    public string $pageUrl = '';

    public bool $isSubscribed = false;

    public function mount(): void
    {
        $this->renderedAt = now()->getTimestamp();
        $this->pageUrl = url()->current();
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return array_filter([
            'email' => ['required', 'email:rfc', 'max:255'],
            'firstName' => $this->asksName ? ['nullable', 'string', 'max:100'] : null,
            'lastName' => $this->asksName ? ['nullable', 'string', 'max:100'] : null,
            'consent' => $this->requiresConsent ? ['accepted'] : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'email' => __('email address'),
            'firstName' => __('first name'),
            'lastName' => __('last name'),
            'consent' => __('agreement'),
        ];
    }

    public function subscribe(MailingListService $mailingLists): void
    {
        $this->validate();

        // Bots get the normal thank-you, so there's nothing for them to learn.
        if ($this->website !== '' || now()->getTimestamp() - $this->renderedAt < self::MINIMUM_SECONDS_ON_PAGE) {
            $this->markAsSubscribed();

            return;
        }

        $rateLimitKey = 'site-builder.mailing-list-signup:'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_SIGNUPS_PER_HOUR)) {
            $this->addError('email', __('You have signed up several times recently. Please try again later.'));

            return;
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $provider = MailingListProvider::active();

        if ($provider === null) {
            $this->failed(null, 'No mailing list service is in use.');

            return;
        }

        // A block's list must still be on the account; otherwise the default one.
        $listId = $this->listId !== null && isset($mailingLists->lists($provider)[$this->listId]) ? $this->listId : null;

        try {
            $mailingLists->subscribe($provider, $this->email, $listId, $this->asksName ? ['first_name' => trim($this->firstName), 'last_name' => trim($this->lastName)] : []);
        } catch (MailingListException $exception) {
            $this->failed($provider, $exception->getMessage());

            return;
        }

        $notify = config('site-builder.mailing_lists.notify_email');

        if (is_string($notify) && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
            Mail::to($notify)->send(new MailingListSubscribed(
                subscriberEmail: $this->email,
                providerName: $provider->name,
                listName: $listId !== null ? ($mailingLists->lists($provider)[$listId] ?? $listId) : ($provider->list_name ?? (string) $provider->list_id),
                pageUrl: $this->pageUrl,
            ));
        }

        if ($this->redirectUrl !== null && preg_match('/^(https?:\/\/|\/)/i', $this->redirectUrl) === 1) {
            $this->redirect($this->redirectUrl);

            return;
        }

        $this->markAsSubscribed();
    }

    /**
     * The visitor sees a general apology; the service's explanation goes to the log for admins.
     */
    private function failed(?MailingListProvider $provider, string $error): void
    {
        Log::warning('Mailing list sign-up failed', ['provider' => $provider?->slug, 'error' => $error]);

        $this->addError('email', __('Sorry, we couldn\'t sign you up just now. Please try again later.'));
    }

    private function markAsSubscribed(): void
    {
        $this->reset(['email', 'firstName', 'lastName', 'consent', 'website']);
        $this->isSubscribed = true;
    }

    /**
     * Wording and look from Branding › Mailing list (blank text reads as each field's default there); a block's own
     * wording wins.
     */
    public function render(Branding $branding, BrandingStyle $style): View
    {
        $consentColor = $branding->appValue('newsletter_consent_color');
        $isDoubleOptIn = (bool) MailingListProvider::active()?->double_opt_in;

        return view('site-builder::livewire.mailing-list-signup', [
            'isAvailable' => MailingListProvider::active() !== null,
            'eyebrow' => (string) $branding->appValue('newsletter_eyebrow'),
            'title' => filled($this->heading) ? $this->heading : (string) $branding->appValue('newsletter_title'),
            'subtext' => filled($this->intro) ? $this->intro : (string) $branding->appValue('newsletter_subtext'),
            'placeholder' => (string) $branding->appValue('newsletter_placeholder'),
            'button' => filled($this->buttonText) ? $this->buttonText : (string) $branding->appValue('newsletter_button_text'),
            'thanks' => filled($this->successMessage) ? $this->successMessage : ($isDoubleOptIn
                ? __('Nearly there: please check your inbox and click the link to confirm.')
                : (string) $branding->appValue('newsletter_success_message')),
            'consentHtml' => $this->consentHtml((string) $branding->appValue('newsletter_consent_text')),
            'consentColor' => Colour::isValidHex($consentColor) ? $consentColor : null,
            'buttonSlot' => $style->appButtonSlot('newsletter_button_style', isOnDarkBackground: true),
        ]);
    }

    /**
     * The small print as HTML: the text escaped, with ":privacy" and ":terms" as links to the legal pages.
     */
    private function consentHtml(string $text): string
    {
        $link = fn (string $route, string $label): string => Route::has($route)
            ? '<a href="'.e(route($route)).'" class="underline hover:no-underline">'.e($label).'</a>'
            : e($label);

        return strtr(e($text), [
            ':privacy' => $link('privacy-policy', __('Privacy Policy')),
            ':terms' => $link('terms-of-service', __('Terms of Service')),
        ]);
    }
}
