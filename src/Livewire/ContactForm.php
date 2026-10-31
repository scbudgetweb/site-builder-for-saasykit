<?php

namespace ScBudgetWeb\SiteBuilder\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;
use ScBudgetWeb\SiteBuilder\Mail\ContactMessageReceived;
use ScBudgetWeb\SiteBuilder\Models\ContactMessage;
use Throwable;

/**
 * The Contact form block's form (<livewire:site-builder.contact-form />). Each message is kept in Website › Contact
 * messages and, when the block emails them, sent to its address ($deliverTo) with Reply-To set to the visitor.
 *
 * What the block chooses (which fields, where messages go, its wording, a page to go to after) is #[Locked]: it's in
 * Livewire's signed snapshot, so a visitor can't change it. Spam protection: a honeypot, a minimum time on the page and a per-IP rate
 * limit, as the mailing list form has.
 */
class ContactForm extends Component
{
    public const MINIMUM_SECONDS_ON_PAGE = 3;

    public const MAX_MESSAGES_PER_HOUR = 5;

    public const MAX_MESSAGE_LENGTH = 5000;

    /** A field's setting => whether it's asked, and needed. */
    public const FIELD_CHOICES = ['required', 'optional', 'hidden'];

    #[Locked]
    public string $nameField = 'required';

    #[Locked]
    public string $phoneField = 'hidden';

    #[Locked]
    public string $subjectField = 'hidden';

    #[Locked]
    public bool $requiresConsent = false;

    #[Locked]
    public ?string $buttonText = null;

    #[Locked]
    public ?string $successMessage = null;

    /** A page to go to after sending (checked against the allowed link kinds), instead of the thank-you message. */
    #[Locked]
    public ?string $redirectUrl = null;

    /** The submit button's look: the block's Design › Buttons (PageBlock::buttonLook()). */
    #[Locked]
    public string $buttonClass = 'site-button site-button-1';

    #[Locked]
    public string $buttonStyle = '';

    /** Which form it was, for Website › Contact messages: the block's admin label or heading, or the page's title. */
    #[Locked]
    public ?string $formName = null;

    #[Locked]
    public ?int $pageId = null;

    /** Where each message is emailed (ContactFormBlock::deliverTo()); null keeps them in Contact messages only. */
    #[Locked]
    public ?string $deliverTo = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $subject = '';

    public string $message = '';

    public bool $consent = false;

    /** Honeypot: hidden from people, so anything typed here came from a bot. */
    public string $website = '';

    #[Locked]
    public int $renderedAt = 0;

    #[Locked]
    public string $pageUrl = '';

    public bool $isSent = false;

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
        $field = fn (string $choice, array $rules): ?array => match ($choice) {
            'required' => ['required', ...$rules],
            'optional' => ['nullable', ...$rules],
            default => null,
        };

        return array_filter([
            'name' => $field($this->nameField, ['string', 'max:100']),
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => $field($this->phoneField, ['string', 'max:40', 'regex:/^[0-9 +().-]*$/']),
            'subject' => $field($this->subjectField, ['string', 'max:150']),
            'message' => ['required', 'string', 'max:'.self::MAX_MESSAGE_LENGTH],
            'consent' => $this->requiresConsent ? ['accepted'] : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('name'),
            'email' => __('email address'),
            'phone' => __('phone number'),
            'subject' => __('subject'),
            'message' => __('message'),
            'consent' => __('agreement'),
        ];
    }

    public function send(): void
    {
        $this->validate();

        // Bots get the normal thank-you, so there's nothing for them to learn.
        if ($this->website !== '' || now()->getTimestamp() - $this->renderedAt < self::MINIMUM_SECONDS_ON_PAGE) {
            $this->markAsSent();

            return;
        }

        $rateLimitKey = 'site-builder.contact-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_MESSAGES_PER_HOUR)) {
            $this->addError('message', __('You have sent several messages recently. Please try again later.'));

            return;
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $contactMessage = ContactMessage::query()->create([
            'page_id' => $this->pageId,
            'form_name' => $this->formName !== null ? mb_substr($this->formName, 0, 255) : null,
            'name' => $this->nameField !== 'hidden' ? (trim($this->name) ?: null) : null,
            'email' => trim($this->email),
            'phone' => $this->phoneField !== 'hidden' ? (trim($this->phone) ?: null) : null,
            'subject' => $this->subjectField !== 'hidden' ? (trim($this->subject) ?: null) : null,
            'message' => trim($this->message),
            'page_url' => mb_substr($this->pageUrl, 0, 500),
        ]);

        if ($this->deliverTo !== null && filter_var($this->deliverTo, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($this->deliverTo)->send(new ContactMessageReceived($contactMessage));
            } catch (Throwable $exception) {
                // It's kept in Website › Contact messages, so the visitor's message isn't lost.
                Log::warning('Contact form email failed', ['message_id' => $contactMessage->id, 'error' => $exception->getMessage()]);
            }
        }

        if ($this->redirectUrl !== null && preg_match('/^(https?:\/\/|\/)/i', $this->redirectUrl) === 1) {
            $this->redirect($this->redirectUrl);

            return;
        }

        $this->markAsSent();
    }

    private function markAsSent(): void
    {
        $this->reset(['name', 'email', 'phone', 'subject', 'message', 'consent', 'website']);
        $this->isSent = true;
    }

    public function render(): View
    {
        return view('site-builder::livewire.contact-form', [
            'button' => filled($this->buttonText) ? $this->buttonText : __('Send message'),
            'thanks' => filled($this->successMessage) ? $this->successMessage : __('Thank you. Your message has been sent, and we\'ll be in touch soon.'),
            'privacyLink' => Route::has('privacy-policy') ? route('privacy-policy') : null,
        ]);
    }
}
