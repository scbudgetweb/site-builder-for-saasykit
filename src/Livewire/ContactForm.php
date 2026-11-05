<?php

namespace ScBudgetWeb\SiteBuilder\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use ScBudgetWeb\SiteBuilder\Blocks\Core\ContactFormBlock;
use ScBudgetWeb\SiteBuilder\Mail\ContactMessageReceived;
use ScBudgetWeb\SiteBuilder\Models\ContactMessage;
use Throwable;

/**
 * The Contact form block's form (<livewire:site-builder.contact-form />). Each message is kept in Website › Contact
 * messages and, when the block emails them, sent to its address ($deliverTo) with Reply-To set to the visitor.
 *
 * What the block chooses (its fields, where messages go, its wording, a page to go to after) is #[Locked]: it's in
 * Livewire's signed snapshot, so a visitor can't change it. Spam protection: a honeypot, a minimum time on the page and a per-IP rate
 * limit, as the mailing list form has.
 *
 * The visitor's answers are $answers, by the field's place in the list. Each message keeps every answer as
 * {label, type, value} in the form's order, and fills the name, email, phone, subject and message columns from the
 * Name, Email and first Phone field, a Text field labelled Subject, and the first Long text field, so the list,
 * search and Reply work for every form.
 */
class ContactForm extends Component
{
    public const MINIMUM_SECONDS_ON_PAGE = 3;

    public const MAX_MESSAGES_PER_HOUR = 5;

    public const MAX_MESSAGE_LENGTH = 5000;

    public const MAX_TEXT_LENGTH = 150;

    /**
     * The block's fields (ContactFormBlock::formFields()).
     *
     * @var array<int, array{type: string, label: string, placeholder: string|null, required: bool, width: string, options: array<int, string>, help: string|null, fill_from: string|null}>
     */
    #[Locked]
    public array $fields = [];

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

    /**
     * The visitor's answers, by the field's place in $fields: text, or a list of choices for Checkboxes.
     *
     * @var array<int, string|array<int, string>>
     */
    public array $answers = [];

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
        // Without fields of its own (an older embed), the standard form: Name, Email and Message.
        $this->fields = ContactFormBlock::formFields($this->fields === [] ? [] : ['fields' => $this->fields]);
        $this->resetAnswers();
        $this->fillFromLink();
        $this->renderedAt = now()->getTimestamp();
        $this->pageUrl = url()->current();
    }

    private function resetAnswers(): void
    {
        $this->answers = array_map(fn (array $field): string|array => $field['type'] === 'checkboxes' ? [] : '', $this->fields);
    }

    /**
     * Fields with a Filled in from the link take the page link's value (e.g. ?size=21-50 from a Pricing table's
     * button): text as it is, a choice when it matches one. Anything else is left blank; sending checks it as usual.
     */
    private function fillFromLink(): void
    {
        foreach ($this->fields as $index => $field) {
            $value = $field['fill_from'] !== null ? request()->query($field['fill_from']) : null;
            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                continue;
            }

            if (in_array($field['type'], ContactFormBlock::CHOICE_TYPES, true)) {
                $choice = self::matchingChoice($field['options'], $value);

                if ($choice !== null) {
                    $this->answers[$index] = $field['type'] === 'checkboxes' ? [$choice] : $choice;
                }

                continue;
            }

            $this->answers[$index] = mb_substr($value, 0, $field['type'] === 'textarea' ? self::MAX_MESSAGE_LENGTH : self::MAX_TEXT_LENGTH);
        }
    }

    /**
     * The choice a link's value means: the same text (ignoring case), or else the only choice starting with it, so
     * billing=annual picks "Annually".
     *
     * @param  array<int, string>  $choices
     */
    public static function matchingChoice(array $choices, string $value): ?string
    {
        $value = mb_strtolower($value);
        $starting = [];

        foreach ($choices as $choice) {
            $lower = mb_strtolower($choice);

            if ($lower === $value) {
                return $choice;
            }

            if (str_starts_with($lower, $value)) {
                $starting[] = $choice;
            }
        }

        return count($starting) === 1 ? $starting[0] : null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $rules = [];

        foreach ($this->fields as $index => $field) {
            $presence = $field['required'] ? 'required' : 'nullable';

            $rules["answers.{$index}"] = match ($field['type']) {
                'email' => ['required', 'string', 'email:rfc', 'max:255'],
                'name' => [$presence, 'string', 'max:100'],
                'phone' => [$presence, 'string', 'max:40', 'regex:/^[0-9 +().-]*$/'],
                'textarea' => [$presence, 'string', 'max:'.self::MAX_MESSAGE_LENGTH],
                'select', 'radio' => [$presence, 'string', Rule::in($field['options'])],
                'checkboxes' => [$presence, 'array', $field['required'] ? 'min:1' : 'min:0'],
                default => [$presence, 'string', 'max:'.self::MAX_TEXT_LENGTH],
            };

            if ($field['type'] === 'checkboxes') {
                $rules["answers.{$index}.*"] = ['string', Rule::in($field['options'])];
            }
        }

        return array_filter([...$rules, 'consent' => $this->requiresConsent ? ['accepted'] : null]);
    }

    /**
     * Each field's label, as the visitor sees it, for its error messages.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        $attributes = ['consent' => __('agreement')];

        foreach ($this->fields as $index => $field) {
            $attributes["answers.{$index}"] = mb_strtolower($field['label']);
            $attributes["answers.{$index}.*"] = mb_strtolower($field['label']);
        }

        return $attributes;
    }

    /**
     * Every answer as {label, type, value}, in the form's order: text trimmed, Checkboxes a list.
     *
     * @return array<int, array{label: string, type: string, value: string|array<int, string>}>
     */
    private function answered(): array
    {
        return array_map(function (array $field, int $index): array {
            $answer = $this->answers[$index] ?? '';
            $value = $field['type'] === 'checkboxes'
                ? array_values(array_intersect($field['options'], is_array($answer) ? $answer : []))
                : (is_string($answer) ? trim($answer) : '');

            return ['label' => $field['label'], 'type' => $field['type'], 'value' => $value];
        }, $this->fields, array_keys($this->fields));
    }

    /**
     * The first answer of a type (and, for Subject, a Text field labelled Subject), or null.
     *
     * @param  array<int, array{label: string, type: string, value: string|array<int, string>}>  $answers
     */
    private static function first(array $answers, string $type, ?string $label = null): ?string
    {
        foreach ($answers as $answer) {
            if ($answer['type'] === $type && is_string($answer['value']) && $answer['value'] !== ''
                && ($label === null || mb_strtolower($answer['label']) === mb_strtolower($label))) {
                return $answer['value'];
            }
        }

        return null;
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
            $this->addError('form', __('You have sent several messages recently. Please try again later.'));

            return;
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $answers = $this->answered();

        $contactMessage = ContactMessage::query()->create([
            'page_id' => $this->pageId,
            'form_name' => $this->formName !== null ? mb_substr($this->formName, 0, 255) : null,
            'name' => self::first($answers, 'name'),
            'email' => (string) self::first($answers, 'email'),
            'phone' => self::first($answers, 'phone'),
            'subject' => ($subject = self::first($answers, 'text', __('Subject'))) !== null ? mb_substr($subject, 0, 255) : null,
            'message' => self::first($answers, 'textarea') ?? '',
            'fields' => $answers,
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
        $this->resetAnswers();
        $this->reset(['consent', 'website']);
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
