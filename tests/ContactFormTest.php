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
     * The form as a visitor fills it in, a few seconds after the page loaded.
     *
     * @param  array<string, mixed>  $choices
     */
    private function fill(array $choices = [], array $answers = []): Testable
    {
        $form = Livewire::test(ContactForm::class, ['formName' => 'Hire enquiries', 'deliverTo' => 'bookings@sometown-hall.test', ...$choices]);
        $this->travel(5)->seconds();

        foreach (['name' => 'Sam Jones', 'email' => 'sam@example.com', 'message' => "Is the hall free on 12 June?\nIt's for a party.", ...$answers] as $field => $value) {
            $form->set($field, $value);
        }

        return $form;
    }

    public function test_the_block_shows_the_form_it_chose(): void
    {
        $this->page(['blocks' => [self::block('contact_form', ['heading' => 'Get in touch', 'phone_field' => 'optional', 'subject_field' => 'required', 'require_consent' => true])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        foreach (['Get in touch', 'wire:model="name"', 'wire:model="email"', 'wire:model="phone"', 'wire:model="subject"', 'wire:model="message"', 'wire:model="consent"', 'Send message'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertMatchesRegularExpression('/Phone number(?:<!--.*?-->)?\s*<span class="font-normal opacity-70">\(optional\)<\/span>/', $html);
        $this->assertFalse(ContactFormBlock::isCacheable([]), 'A form for each visitor.');

        $data = ContactFormBlock::viewData(['heading' => 'Get in touch', 'phone_field' => 'everything'], $this->page(['slug' => 'contact']))['form'];
        $this->assertSame(['hidden', 'Get in touch'], [$data['phoneField'], $data['formName']], 'Unknown choices fall back; the heading names the form.');
    }

    public function test_a_message_is_kept_and_emailed_with_reply_to_the_visitor(): void
    {
        $page = $this->page(['slug' => 'contact']);

        $this->fill(['pageId' => $page->id, 'phoneField' => 'optional'], ['phone' => '01234 567890'])
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
                && str_contains($text, 'Phone: 01234 567890');
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
        $this->fill(['subjectField' => 'required', 'requiresConsent' => true], ['name' => '', 'email' => 'not-an-email', 'message' => ''])
            ->call('send')
            ->assertHasErrors(['name' => 'required', 'email' => 'email', 'subject' => 'required', 'message' => 'required', 'consent' => 'accepted'])
            ->assertSee('[[data-text-scheme=light]_&amp;]:text-red-300', false);

        $this->fill(['nameField' => 'optional', 'phoneField' => 'optional'], ['name' => '', 'phone' => 'call me'])
            ->call('send')
            ->assertHasErrors(['phone' => 'regex'])
            ->assertHasNoErrors(['name']);

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_a_field_the_block_doesnt_ask_for_isnt_kept(): void
    {
        $this->fill([], ['phone' => '01234 567890', 'subject' => 'Sneaky'])->call('send');

        $message = ContactMessage::query()->sole();
        $this->assertNull($message->phone);
        $this->assertNull($message->subject);
    }

    public function test_the_blocks_choices_cant_be_changed_by_a_visitor(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->fill()->set('formName', 'Something else');
    }

    public function test_bots_get_a_thank_you_but_nothing_is_kept(): void
    {
        $this->fill([], ['website' => 'https://spam.example'])->call('send')->assertSet('isSent', true);

        // Too quick to be a person.
        Livewire::test(ContactForm::class)
            ->set('name', 'Sam Jones')
            ->set('email', 'sam@example.com')
            ->set('message', 'Hello')
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

        $this->fill()->call('send')->assertHasErrors(['message'])->assertSet('isSent', false);

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
            ['type' => 'contact_form', 'data' => ['heading' => 'Get in touch', 'phone_field' => 'optional', 'after' => 'redirect']],
        ]]);

        $this->assertSame(['blocks.0.data.redirect_url: The page to go to field is required.'], $result->errors);
    }
}
