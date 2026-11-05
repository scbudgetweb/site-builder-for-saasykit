<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\Core\ContactFormBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages\ContactMessageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use ScBudgetWeb\SiteBuilder\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Livewire\ContactForm;
use ScBudgetWeb\SiteBuilder\Mail\ContactMessageReceived;
use ScBudgetWeb\SiteBuilder\Models\ContactMessage;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;

/**
 * The Contact form block: the form (ContactForm), its spam checks, the email to the site's address, and Website ›
 * Contact messages, where every message is kept.
 */
class ContactFormTest extends PageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            Fixtures\AdminPanelProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('site-builder.contact-form:127.0.0.1');
        Mail::fake();
        config(['app.support_email' => 'support@sometown-hall.test']);
    }

    /**
     * The form as a visitor fills it in, a few seconds after the page loaded. Without fields of its own it's the
     * standard form: 0 Name, 1 Email, 2 Message; $answers are by the field's place.
     *
     * @param  array<string, mixed>  $choices
     * @param  array<int, mixed>  $answers
     */
    private function fill(array $choices = [], array $answers = [0 => 'Sam Jones', 1 => 'sam@example.com', 2 => "Is the hall free on 12 June?\nIt's for a party."]): Testable
    {
        $form = Livewire::test(ContactForm::class, ['formName' => 'Hire enquiries', 'deliverTo' => 'bookings@sometown-hall.test', ...$choices]);
        $this->travel(5)->seconds();

        foreach ($answers as $index => $value) {
            $form->set("answers.{$index}", $value);
        }

        return $form;
    }

    /**
     * A form with every type of field, as ContactFormBlock::formFields() gives it.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function everyType(): array
    {
        return ContactFormBlock::formFields(['fields' => [
            ['type' => 'name', 'label' => 'Your name', 'required' => true, 'width' => 'half'],
            ['type' => 'email', 'label' => 'Email', 'width' => 'half', 'placeholder' => 'you@example.com'],
            ['type' => 'phone', 'label' => 'Phone', 'required' => false],
            ['type' => 'text', 'label' => 'Subject', 'required' => true],
            ['type' => 'select', 'label' => 'Room', 'required' => true, 'options' => "Main hall\nSmall hall\n\nMain hall"],
            ['type' => 'radio', 'label' => 'Time of day', 'required' => false, 'options' => "Morning\nEvening"],
            ['type' => 'checkboxes', 'label' => 'Extras', 'required' => false, 'options' => "Kitchen\nStage\nParking"],
            ['type' => 'textarea', 'label' => 'Anything else', 'required' => false, 'help' => 'Numbers, timings, access needs.'],
        ]]);
    }

    public function test_the_block_shows_the_form_it_chose(): void
    {
        $this->page(['blocks' => [self::block('contact_form', ['heading' => 'Get in touch', 'require_consent' => true, 'fields' => self::everyType()])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        foreach (['Get in touch', 'wire:model="answers.0"', 'type="email" wire:model="answers.1"', 'placeholder="you@example.com"', 'type="tel" wire:model="answers.2"', '<select', '<option value="Small hall">', 'type="radio"', 'type="checkbox" value="Parking"', '<textarea', 'Numbers, timings, access needs.', 'wire:model="consent"', 'Send message'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertSame(1, substr_count($html, '<option value="Main hall">'), 'Repeated choices are shown once.');
        $this->assertMatchesRegularExpression('/>\s*Phone(?:<!--.*?-->)?\s*<span class="font-normal opacity-70">\(optional\)<\/span>/', $html);
        $this->assertFalse(ContactFormBlock::isCacheable([]), 'A form for each visitor.');

        $data = ContactFormBlock::viewData(['heading' => 'Get in touch', 'fields' => [['type' => 'everything', 'label' => 'Notes']]], $this->page(['slug' => 'contact']))['form'];
        $this->assertSame([['email', true], ['text', false]], array_map(fn (array $field): array => [$field['type'], $field['required']], $data['fields']), 'An unknown type is Text, and a form always has its Email field.');
        $this->assertSame('Get in touch', $data['formName'], 'The heading names the form.');
    }

    public function test_a_message_is_kept_and_emailed_with_reply_to_the_visitor(): void
    {
        $page = $this->page(['slug' => 'contact']);

        $fields = ContactFormBlock::formFields(['fields' => [
            ['type' => 'name', 'label' => 'Name', 'required' => true],
            ['type' => 'email', 'label' => 'Email address'],
            ['type' => 'phone', 'label' => 'Phone number'],
            ['type' => 'textarea', 'label' => 'Message', 'required' => true],
        ]]);

        $this->fill(['pageId' => $page->id, 'fields' => $fields], ['Sam Jones', 'sam@example.com', '01234 567890', "Is the hall free on 12 June?\nIt's for a party."])
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('isSent', true)
            ->assertSee('Thank you. Your message has been sent');

        $message = ContactMessage::query()->sole();
        $this->assertSame(['Sam Jones', 'sam@example.com', '01234 567890', null, 'Hire enquiries', $page->id], [$message->name, $message->email, $message->phone, $message->subject, $message->form_name, $message->page_id]);
        $this->assertNull($message->read_at);

        Mail::assertSent(ContactMessageReceived::class, function (ContactMessageReceived $mail): bool {
            $text = $mail->render();

            return $mail->hasTo('bookings@sometown-hall.test')
                && $mail->hasReplyTo('sam@example.com', 'Sam Jones')
                && $mail->envelope()->subject === 'Contact form: a message from Sam Jones'
                && str_contains($text, "It's for a party.")
                && str_contains($text, 'Phone number: 01234 567890');
        });
    }

    public function test_with_email_off_messages_are_only_kept(): void
    {
        $this->fill(['deliverTo' => null])->call('send')->assertSet('isSent', true);

        $this->assertSame(1, ContactMessage::query()->count());
        Mail::assertNothingSent();
    }

    public function test_messages_go_to_the_blocks_address_or_the_support_email(): void
    {
        $this->assertSame('support@sometown-hall.test', ContactFormBlock::deliverTo([]), 'On, and to the Support Email, unless chosen.');
        $this->assertSame('hire@sometown-hall.test', ContactFormBlock::deliverTo(['deliver_to' => 'hire@sometown-hall.test']));
        $this->assertSame('support@sometown-hall.test', ContactFormBlock::deliverTo(['deliver_to' => 'not an address']));
        $this->assertNull(ContactFormBlock::deliverTo(['send_email' => false, 'deliver_to' => 'hire@sometown-hall.test']), 'Off: kept only.');

        config(['app.support_email' => '']);
        $this->assertNull(ContactFormBlock::deliverTo([]), 'No Support Email: kept only.');

        $page = $this->page(['slug' => 'contact']);
        $this->assertSame('hire@sometown-hall.test', ContactFormBlock::viewData(['deliver_to' => 'hire@sometown-hall.test'], $page)['form']['deliverTo'], 'Locked into the form.');
    }

    public function test_its_panel_chooses_where_messages_go(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('contact_form', ['heading' => 'Get in touch'])]]);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $page->blocks[0]['uid']]))
            ->assertSet('mountedActions.0.data.send_email', true)
            ->assertFormFieldVisible('deliver_to', 'mountedActionSchema0')
            ->assertFormFieldExists('deliver_to', 'mountedActionSchema0', fn ($field): bool => $field->getPlaceholder() === 'support@sometown-hall.test')
            ->set('mountedActions.0.data.deliver_to', 'not an address')
            ->callMountedAction()
            ->assertHasFormErrors(['deliver_to'])
            ->set('mountedActions.0.data.send_email', false)
            ->assertFormFieldHidden('deliver_to', 'mountedActionSchema0');
    }

    public function test_a_failed_email_doesnt_lose_the_message(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mail server down'));

        $this->fill()->call('send')->assertSet('isSent', true);

        $this->assertSame(1, ContactMessage::query()->count());
    }

    public function test_fields_are_checked_as_the_block_chose(): void
    {
        $this->fill(['fields' => self::everyType(), 'requiresConsent' => true], [0 => '', 1 => 'not-an-email', 3 => '', 4 => ''])
            ->call('send')
            ->assertHasErrors(['answers.0' => 'required', 'answers.1' => 'email', 'answers.3' => 'required', 'answers.4' => 'required', 'consent' => 'accepted'])
            ->assertHasNoErrors(['answers.2', 'answers.5', 'answers.6', 'answers.7'])
            ->assertSee('The your name field is required.')
            ->assertSee('[[data-text-scheme=light]_&amp;]:text-red-300', false);

        $this->fill(['fields' => self::everyType()], [0 => 'Sam', 1 => 'sam@example.com', 2 => 'call me', 3 => 'Party', 4 => 'Main hall'])
            ->call('send')
            ->assertHasErrors(['answers.2' => 'regex']);

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_a_field_the_block_doesnt_ask_for_isnt_kept(): void
    {
        $this->fill([], [0 => 'Sam Jones', 1 => 'sam@example.com', 2 => 'Hello', 3 => '01234 567890', 9 => 'Sneaky'])->call('send');

        $message = ContactMessage::query()->sole();
        $this->assertNull($message->phone);
        $this->assertNull($message->subject);
        $this->assertSame(['Name', 'Email address', 'Message'], array_column($message->fields, 'label'), 'Only the form\'s own fields.');
    }

    public function test_the_blocks_choices_cant_be_changed_by_a_visitor(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->fill()->set('formName', 'Something else');
    }

    public function test_the_fields_cant_be_changed_by_a_visitor(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->fill()->set('fields.0.type', 'checkboxes');
    }

    public function test_every_type_of_field_is_kept_in_order_and_shown_in_view_and_the_email(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->fill(['fields' => self::everyType()], [
            0 => 'Sam Jones', 1 => 'sam@example.com', 2 => '', 3 => 'Party booking', 4 => 'Small hall',
            5 => 'Evening', 6 => ['Stage', 'Kitchen'], 7 => "About 60 people.\nStep-free, please.",
        ])->call('send')->assertHasNoErrors()->assertSet('isSent', true)->assertSet('answers.6', []);

        $message = ContactMessage::query()->sole();
        $this->assertSame(
            [['Your name', 'Sam Jones'], ['Email', 'sam@example.com'], ['Phone', ''], ['Subject', 'Party booking'], ['Room', 'Small hall'], ['Time of day', 'Evening'], ['Extras', ['Kitchen', 'Stage']], ['Anything else', "About 60 people.\nStep-free, please."]],
            array_map(fn (array $answer): array => [$answer['label'], $answer['value']], $message->fields),
            'Every answer in the form\'s order; Checkboxes in the order of their choices.',
        );
        $this->assertSame(['Sam Jones', 'sam@example.com', null, 'Party booking', "About 60 people.\nStep-free, please."], [$message->name, $message->email, $message->phone, $message->subject, $message->message], 'The standard columns are filled, for the list, search and Reply.');

        Mail::assertSent(ContactMessageReceived::class, function (ContactMessageReceived $mail): bool {
            $text = $mail->render();

            return $mail->hasReplyTo('sam@example.com', 'Sam Jones')
                && $mail->envelope()->subject === 'Contact form: Party booking'
                && str_contains($text, 'Room: Small hall')
                && str_contains($text, 'Extras: Kitchen, Stage')
                && str_contains($text, "Anything else:\nAbout 60 people.\nStep-free, please.")
                && ! str_contains($text, 'Phone:');
        });

        $this->actingAs($this->user());
        Gate::define('update settings', fn ($user): bool => true);

        Livewire::test(ViewContactMessage::class, ['record' => $message->getRouteKey()])
            ->assertSeeInOrder(['Your name', 'Sam Jones', 'Phone', '-', 'Room', 'Small hall', 'Extras', 'Kitchen, Stage', 'Anything else', 'About 60 people.']);
    }

    public function test_choices_must_be_ones_the_form_offers(): void
    {
        $answers = [0 => 'Sam', 1 => 'sam@example.com', 3 => 'Party', 4 => 'Main hall'];

        $this->fill(['fields' => self::everyType()], [...$answers, 4 => 'The roof'])
            ->call('send')
            ->assertHasErrors(['answers.4' => 'in']);

        $this->fill(['fields' => self::everyType()], [...$answers, 5 => 'Midnight'])
            ->call('send')
            ->assertHasErrors(['answers.5' => 'in']);

        $this->fill(['fields' => self::everyType()], [...$answers, 6 => ['Stage', 'Swimming pool']])
            ->call('send')
            ->assertHasErrors(['answers.6.1' => 'in'])
            ->assertSee('The selected extras is invalid.');

        $required = ContactFormBlock::formFields(['fields' => [['type' => 'email', 'label' => 'Email'], ['type' => 'checkboxes', 'label' => 'Days', 'required' => true, 'options' => "Saturday\nSunday"]]]);
        $this->fill(['fields' => $required], [0 => 'sam@example.com', 1 => []])
            ->call('send')
            ->assertHasErrors(['answers.1' => 'required']);

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_old_forms_become_a_list_of_fields_that_looks_the_same(): void
    {
        $this->assertSame([
            ['type' => 'name', 'label' => 'Name', 'required' => true, 'width' => 'half'],
            ['type' => 'email', 'label' => 'Email address', 'required' => true, 'width' => 'half'],
            ['type' => 'textarea', 'label' => 'Message', 'required' => true, 'width' => 'full'],
        ], ContactFormBlock::upgrade(['heading' => 'Get in touch'])['fields'], 'The standard form: Name and Email side by side, then Message.');

        $upgraded = ContactFormBlock::upgrade(['name_field' => 'optional', 'phone_field' => 'required', 'subject_field' => 'optional', 'send_email' => false]);
        $this->assertSame(
            [['name', false, 'half'], ['email', true, 'half'], ['phone', true, 'half'], ['text', false, 'half'], ['textarea', true, 'full']],
            array_map(fn (array $field): array => [$field['type'], $field['required'], $field['width']], $upgraded['fields']),
        );
        $this->assertSame('Subject', $upgraded['fields'][3]['label']);
        $this->assertArrayNotHasKey('phone_field', $upgraded);
        $this->assertFalse($upgraded['send_email'], 'Its other settings are kept.');

        $odd = ContactFormBlock::upgrade(['name_field' => 'hidden', 'phone_field' => 'optional']);
        $this->assertSame([['email', 'half'], ['phone', 'half'], ['textarea', 'full']], array_map(fn (array $field): array => [$field['type'], $field['width']], $odd['fields']));
        $three = ContactFormBlock::upgrade(['phone_field' => 'optional']);
        $this->assertSame(['half', 'half', 'full', 'full'], array_column($three['fields'], 'width'), 'An odd one out takes the whole row, as before.');

        $this->assertSame(['fields' => []], ContactFormBlock::upgrade(['fields' => []]), 'A list already saved is left as it is.');

        // Stored blocks are upgraded as they're read.
        $page = $this->page(['blocks' => [self::block('contact_form', ['heading' => 'Get in touch', 'subject_field' => 'required'])]]);
        $this->assertSame(['name', 'email', 'text', 'textarea'], array_column($page->fresh()->blocks[0]['data']['fields'], 'type'));
        $this->get('/about-us')->assertOk()->assertSee('wire:model="answers.2"', false)->assertSee('Subject');
    }

    public function test_its_panel_lists_the_fields_and_needs_one_email_field(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        $page = $this->page(['blocks' => [self::block('contact_form', ['heading' => 'Get in touch'])]]);
        $uid = $page->blocks[0]['uid'];

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->mountAction(TestAction::make('editBlock')->arguments(['uid' => $uid]))
            ->assertSet('mountedActions.0.data.fields', fn (array $fields): bool => array_column($fields, 'type') === ['name', 'email', 'textarea'])
            ->set('mountedActions.0.data.fields', [
                ['type' => 'name', 'label' => 'Name', 'required' => true, 'width' => 'half'],
                ['type' => 'textarea', 'label' => 'Message', 'required' => true, 'width' => 'full'],
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['fields']);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editBlock')->arguments(['uid' => $uid]), ['fields' => [
                ['type' => 'email', 'label' => 'Your email', 'width' => 'full'],
                ['type' => 'name', 'label' => 'Name', 'required' => true, 'width' => 'half'],
                ['type' => 'name', 'label' => 'Partner\'s name', 'required' => false, 'width' => 'half'],
            ]])
            ->assertHasFormErrors(['fields']);

        Livewire::test(VisualEditor::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make('editBlock')->arguments(['uid' => $uid]), ['fields' => [
                ['type' => 'email', 'label' => 'Your email', 'width' => 'full'],
                ['type' => 'radio', 'label' => 'Room', 'required' => true, 'width' => 'full', 'options' => "Main hall\nSmall hall"],
            ]])
            ->assertHasNoFormErrors()
            ->assertDispatched('site-builder-canvas-updated', fn (string $name, array $params): bool => str_contains($params['html'], 'Your email')
                && str_contains($params['html'], 'value="Small hall"'));

        $this->assertSame('Room · Radio buttons', ContactFormBlock::fieldTitle(['type' => 'radio', 'label' => 'Room']));
    }

    public function test_fields_can_be_filled_in_from_the_link(): void
    {
        $fields = [
            ['type' => 'email', 'label' => 'Email'],
            ['type' => 'text', 'label' => 'Company size', 'fill_from' => 'size'],
            ['type' => 'select', 'label' => 'Billing', 'options' => "Monthly\nAnnually", 'fill_from' => 'billing'],
            ['type' => 'radio', 'label' => 'Plan', 'options' => "Essentials\nProfessional", 'fill_from' => 'plan'],
            ['type' => 'checkboxes', 'label' => 'Interests', 'options' => "Hire\nEvents", 'fill_from' => 'interest'],
            ['type' => 'select', 'label' => 'Team', 'options' => "Small\nLarge", 'fill_from' => 'size'],
            ['type' => 'text', 'label' => 'Notes'],
        ];

        Livewire::withQueryParams(['size' => ' 21–50 employees ', 'billing' => 'annual', 'plan' => 'PROFESSIONAL', 'interest' => 'events', 'notes' => 'Hello'])
            ->test(ContactForm::class, ['fields' => $fields])
            ->assertSet('answers', ['', '21–50 employees', 'Annually', 'Professional', ['Events'], '', '']);

        Livewire::withQueryParams(['size' => ['a', 'b']])
            ->test(ContactForm::class, ['fields' => $fields])
            ->assertSet('answers.1', '');
    }

    public function test_a_link_value_only_picks_a_choice_it_clearly_means(): void
    {
        $this->assertSame('Annual', ContactForm::matchingChoice(['Annual', 'Annually'], 'ANNUAL'), 'The same text first.');
        $this->assertSame('Annually', ContactForm::matchingChoice(['Monthly', 'Annually'], 'annual'), 'The one starting with it.');
        $this->assertNull(ContactForm::matchingChoice(['Annual plan', 'Annually'], 'annual'), 'Two start with it.');
        $this->assertNull(ContactForm::matchingChoice(['Monthly'], 'weekly'));
    }

    public function test_only_a_link_name_is_kept_as_filled_in_from_the_link(): void
    {
        $fields = ContactFormBlock::formFields(['fields' => [
            ['type' => 'email', 'label' => 'Email', 'fill_from' => 'email'],
            ['type' => 'text', 'label' => 'Size', 'fill_from' => 'size here!'],
            ['type' => 'text', 'label' => 'Notes'],
        ]]);

        $this->assertSame(['email', null, null], array_column($fields, 'fill_from'));
    }

    public function test_bots_get_a_thank_you_but_nothing_is_kept(): void
    {
        $this->fill()->set('website', 'https://spam.example')->call('send')->assertSet('isSent', true);

        // Too quick to be a person.
        Livewire::test(ContactForm::class)
            ->set('answers.0', 'Sam Jones')
            ->set('answers.1', 'sam@example.com')
            ->set('answers.2', 'Hello')
            ->call('send')
            ->assertSet('isSent', true);

        $this->assertSame(0, ContactMessage::query()->count());
        Mail::assertNothingSent();
    }

    public function test_messages_are_limited_per_hour(): void
    {
        foreach (range(1, ContactForm::MAX_MESSAGES_PER_HOUR) as $attempt) {
            $this->fill()->call('send')->assertSet('isSent', true);
        }

        $this->fill()->call('send')->assertHasErrors(['form'])->assertSet('isSent', false)->assertSee('You have sent several messages recently.');

        $this->assertSame(ContactForm::MAX_MESSAGES_PER_HOUR, ContactMessage::query()->count());
    }

    public function test_it_can_go_to_a_page_after_sending(): void
    {
        $this->fill(['redirectUrl' => '/thank-you'])->call('send')->assertRedirect('/thank-you');
    }

    public function test_admins_read_and_delete_messages(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        Gate::define('update settings', fn ($user): bool => $user->getAttribute('email') === 'admin@example.com');
        $message = ContactMessage::query()->create(['name' => 'Sam Jones', 'email' => 'sam@example.com', 'message' => 'Is the hall free on 12 June?', 'form_name' => 'Hire enquiries']);
        ContactMessage::query()->create(['email' => 'alex@example.com', 'subject' => 'Parking', 'message' => 'Where do we park?']);

        $this->assertSame('2', ContactMessageResource::getNavigationBadge());

        Livewire::test(ListContactMessages::class)
            ->assertCanSeeTableRecords(ContactMessage::all())
            ->assertSee('Is the hall free on 12 June?')
            ->assertSee('Parking')
            ->assertSee('What visitors sent through your contact forms. Messages are deleted after 365 days.');

        Livewire::test(ViewContactMessage::class, ['record' => $message->getRouteKey()])
            ->assertSee('Is the hall free on 12 June?')
            ->assertSee('Hire enquiries');

        $this->assertNotNull($message->fresh()->read_at, 'Opening it marks it read.');
        $this->assertSame('1', ContactMessageResource::getNavigationBadge());

        Livewire::test(ListContactMessages::class)
            ->callTableAction('markUnread', $message)
            ->callTableAction('delete', ContactMessage::query()->where('email', 'alex@example.com')->sole());

        $this->assertNull($message->fresh()->read_at);
        $this->assertSame(1, ContactMessage::query()->count());
        $this->assertFalse(ContactMessageResource::canCreate());

        $this->actingAs($this->user('someone@example.com'));
        $this->withExceptionHandling()->get(ContactMessageResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }

    public function test_old_messages_are_pruned(): void
    {
        $old = ContactMessage::query()->create(['email' => 'sam@example.com', 'message' => 'Old']);
        $old->forceFill(['created_at' => now()->subDays(400)])->save();
        ContactMessage::query()->create(['email' => 'alex@example.com', 'message' => 'New']);

        $this->artisan('model:prune', ['--model' => [ContactMessage::class]])->assertSuccessful();
        $this->assertSame(['New'], ContactMessage::query()->pluck('message')->all());

        config(['site-builder.contact.keep_days' => 0]);
        ContactMessage::query()->first()->forceFill(['created_at' => now()->subYears(5)])->save();
        $this->artisan('model:prune', ['--model' => [ContactMessage::class]])->assertSuccessful();
        $this->assertSame(1, ContactMessage::query()->count(), '0 keeps them.');
    }

    public function test_it_can_be_imported(): void
    {
        $result = app(PageImport::class)->import(['title' => 'Contact', 'slug' => 'contact-us', 'blocks' => [
            ['type' => 'contact_form', 'data' => ['heading' => 'Get in touch', 'fields' => ContactFormBlock::standardFields(), 'after' => 'redirect']],
        ]]);

        $this->assertSame(['blocks.0.data.redirect_url: The page to go to field is required.'], $result->errors);

        $export = PageImport::readExport((string) json_encode(['site_builder' => 1, 'kind' => 'block', 'schema' => 3, 'blocks' => [
            ['type' => 'contact_form', 'data' => ['heading' => 'Get in touch', 'phone_field' => 'optional']],
        ]]));
        $this->assertSame(['name', 'email', 'phone', 'textarea'], array_column($export['blocks'][0]['data']['fields'], 'type'), 'A form exported before 1.7.2 comes in with its fields as a list.');

        $result = app(PageImport::class)->blocks($export['blocks']);
        $this->assertSame([], $result->errors);
    }
}
