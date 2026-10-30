<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\MailingListBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\MailingLists\MailingListProviderResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\MailingLists\Pages\EditMailingListProvider;
use ScBudgetWeb\SiteBuilder\Livewire\MailingListSignup;
use ScBudgetWeb\SiteBuilder\Mail\MailingListSubscribed;
use ScBudgetWeb\SiteBuilder\MailingLists\BrevoClient;
use ScBudgetWeb\SiteBuilder\MailingLists\MailingListException;
use ScBudgetWeb\SiteBuilder\Models\MailingListProvider;

/**
 * Mailing lists (Website › Mailing lists, the sign-up form and the Mailing list block). Brevo is faked throughout.
 */
class MailingListsTest extends PageTestCase
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

        RateLimiter::clear('site-builder.mailing-list-signup:127.0.0.1');
    }

    private function brevo(): MailingListProvider
    {
        return MailingListProvider::query()->where('slug', MailingListProvider::BREVO)->sole();
    }

    /**
     * Brevo set up and in use, with the "Village news" list (7) as the default and "Hall hire" (9) on the account.
     */
    private function connected(array $attributes = [], array $fakes = []): MailingListProvider
    {
        // A test's own responses replace the standard ones.
        Http::fake($fakes + [
            '*/contacts/lists*' => Http::response(['lists' => [['id' => 7, 'name' => 'Village news'], ['id' => 9, 'name' => 'Hall hire']], 'count' => 2]),
            '*/contacts/doubleOptinConfirmation' => Http::response(null, 204),
            '*/contacts' => Http::response(['id' => 1], 201),
            '*/account' => Http::response(['email' => 'admin@example.com']),
        ]);

        $provider = $this->brevo();
        $provider->update(['api_key' => 'xkeysib-test', 'list_id' => '7', 'list_name' => 'Village news', 'is_active' => true, ...$attributes]);

        return $provider->fresh();
    }

    /**
     * The sign-up form, already on the page long enough to not look like a bot.
     */
    private function form(array $props = []): Testable
    {
        $component = Livewire::test(MailingListSignup::class, $props);
        Carbon::setTestNow(now()->addSeconds(MailingListSignup::MINIMUM_SECONDS_ON_PAGE + 1));

        return $component;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_one_service_at_a_time_and_only_once_it_has_a_key_and_a_list(): void
    {
        $this->assertNull(MailingListProvider::active(), 'Brevo is there from the migration, not set up.');

        $other = (new MailingListProvider)->forceFill(['name' => 'Other', 'slug' => 'other', 'is_active' => true, 'api_key' => 'k', 'list_id' => '1']);
        $other->save();
        $this->assertSame($other->id, MailingListProvider::active()?->id);

        $brevo = $this->connected();
        $this->assertSame($brevo->id, MailingListProvider::active()?->id);
        $this->assertFalse($other->fresh()->is_active, 'Turning one on turns the others off.');
        $this->assertNotSame('xkeysib-test', DB::table('site_builder_mailing_list_providers')->where('id', $brevo->id)->value('api_key'), 'The key is encrypted.');
    }

    public function test_brevo_signs_people_up_with_their_name_or_asks_them_to_confirm_first(): void
    {
        Http::fake([
            '*/contacts/lists*' => Http::sequence()
                ->push(['lists' => array_map(fn (int $id): array => ['id' => $id, 'name' => "List {$id}"], range(1, 50)), 'count' => 51])
                ->push(['lists' => [['id' => 51, 'name' => 'List 51']], 'count' => 51]),
            '*/contacts/doubleOptinConfirmation' => Http::response(null, 204),
            '*/contacts' => Http::sequence()->push(['id' => 1], 201)->push(null, 204)->push(['message' => 'Invalid email'], 400),
        ]);
        $client = new BrevoClient('xkeysib-test');

        $this->assertCount(51, $client->lists(), 'Every page of lists.');

        $client->subscribe('sam@example.com', '7', ['first_name' => 'Sam', 'last_name' => '']);
        $client->subscribe('sam@example.com', '7');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/contacts')
            && $request['listIds'] === [7] && $request['updateEnabled'] === true && $request['emailBlacklisted'] === false
            && ($request->data()['attributes'] ?? null) === ['FIRSTNAME' => 'Sam']
            && $request->header('api-key') === ['xkeysib-test']);

        try {
            $client->subscribe('bad', '7');
            $this->fail('A 400 throws.');
        } catch (MailingListException $exception) {
            $this->assertStringContainsString('Invalid email', $exception->getMessage());
        }

        $client->subscribe('sam@example.com', '9', [], ['templateId' => '12', 'redirectUrl' => 'https://example.com/confirmed']);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/contacts/doubleOptinConfirmation')
            && $request['includeListIds'] === [9] && $request['templateId'] === 12 && $request['redirectionUrl'] === 'https://example.com/confirmed');
    }

    public function test_the_form_signs_people_up_and_tells_the_admin(): void
    {
        Mail::fake();
        config(['site-builder.mailing_lists.notify_email' => 'office@example.com']);
        $this->connected();

        $this->form()
            ->assertSee('Subscribe to our newsletter')
            ->set('email', 'sam@example.com')
            ->call('subscribe')
            ->assertHasNoErrors()
            ->assertSet('isSubscribed', true)
            ->assertSee('Thanks for subscribing!');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/contacts') && $request['listIds'] === [7]);
        Mail::assertSent(MailingListSubscribed::class, fn (MailingListSubscribed $mail): bool => $mail->hasTo('office@example.com')
            && $mail->subscriberEmail === 'sam@example.com' && $mail->listName === 'Village news');
    }

    public function test_bots_bad_emails_and_too_many_sign_ups_are_turned_away_and_failures_are_kind(): void
    {
        $this->connected();

        $this->form()->set('email', 'not an email')->call('subscribe')->assertHasErrors(['email' => 'email']);
        $this->form()->set('email', 'bot@example.com')->set('website', 'spam')->call('subscribe')->assertSet('isSubscribed', true);
        Livewire::test(MailingListSignup::class)->set('email', 'fast@example.com')->call('subscribe')->assertSet('isSubscribed', true);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/contacts'));

        foreach (range(1, MailingListSignup::MAX_SIGNUPS_PER_HOUR) as $attempt) {
            RateLimiter::hit('site-builder.mailing-list-signup:127.0.0.1', 3600);
        }
        $this->form()->set('email', 'sam@example.com')->call('subscribe')->assertHasErrors('email')->assertSee('several times recently');
    }

    public function test_a_failure_shows_a_kind_message_and_is_logged(): void
    {
        $this->connected(fakes: ['*/contacts' => Http::response(['message' => 'Down'], 500)]);

        $this->form()->set('email', 'sam@example.com')->call('subscribe')->assertHasErrors('email')->assertSee('couldn&#039;t sign you up', false)->assertSet('isSubscribed', false);
    }

    public function test_a_blocks_choices_are_locked_and_its_list_must_be_on_the_account(): void
    {
        $this->connected();

        $this->form(['listId' => '9', 'asksName' => true, 'requiresConsent' => true])
            ->assertSee('First name')
            ->set('email', 'sam@example.com')
            ->set('firstName', 'Sam')
            ->call('subscribe')
            ->assertHasErrors(['consent' => 'accepted'])
            ->set('consent', true)
            ->call('subscribe')
            ->assertSet('isSubscribed', true);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/contacts') && $request['listIds'] === [9] && ($request->data()['attributes'] ?? null) === ['FIRSTNAME' => 'Sam']);

        $this->form(['listId' => '404'])->set('email', 'ali@example.com')->call('subscribe');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/contacts') && $request['email'] === 'ali@example.com' && $request['listIds'] === [7]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(MailingListSignup::class)->set('listId', '9');
    }

    public function test_double_opt_in_and_going_to_a_page_afterwards(): void
    {
        $this->connected(['double_opt_in' => true, 'double_opt_in_template_id' => '12', 'double_opt_in_redirect_url' => 'https://example.com/confirmed']);

        $this->form()->set('email', 'sam@example.com')->call('subscribe')->assertSee('please check your inbox and click the link');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/contacts/doubleOptinConfirmation'));

        $this->form(['redirectUrl' => '/thank-you'])->set('email', 'ali@example.com')->call('subscribe')->assertRedirect('/thank-you');
    }

    public function test_the_block_shows_the_form_only_once_a_list_is_in_use(): void
    {
        $this->page(['blocks' => [self::block('mailing_list', ['heading' => 'Hear about hall events', 'list' => '9', 'ask_name' => true])]]);

        $this->get('/about-us')->assertSuccessful()->assertDontSee('Hear about hall events');

        $this->connected();

        $this->get('/about-us')->assertSee('Hear about hall events')->assertSee('First name')->assertSee('wire:snapshot', false);
        $this->assertArrayHasKey('mailing_list', app(BlockRegistry::class)->offered());
        $this->assertFalse(MailingListBlock::isCacheable([]), 'A form for each visitor.');
    }

    public function test_the_small_print_links_take_its_colour(): void
    {
        $this->app['router']->get('/privacy-policy', fn () => 'Privacy')->name('privacy-policy');
        $this->app['router']->get('/terms-of-service', fn () => 'Terms')->name('terms-of-service');
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->connected();

        $this->form()
            ->assertSeeHtml('class="underline hover:no-underline" style="color: inherit;">Privacy Policy</a>')
            ->assertSeeHtml('style="color: inherit;">Terms of Service</a>');
    }

    public function test_a_service_shows_its_logo_from_the_package(): void
    {
        $brevo = $this->brevo();

        $this->assertStringStartsWith('data:image/svg+xml;base64,', (string) $brevo->iconUrl());
        $this->assertStringContainsString('<svg', base64_decode(substr((string) $brevo->iconUrl(), strlen('data:image/svg+xml;base64,'))));

        $brevo->slug = 'unknown';
        $this->assertNull($brevo->iconUrl(), 'No logo shipped: none shown.');

        $brevo->slug = '../../composer';
        $this->assertNull($brevo->iconUrl(), 'Only a plain name is looked up.');
    }

    public function test_admins_connect_brevo_and_choose_the_default_list(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user());
        Gate::define('update settings', fn ($user): bool => $user->getAttribute('email') === 'admin@example.com');
        $brevo = $this->brevo();

        $this->get(MailingListProviderResource::getUrl('index', panel: 'admin'))->assertSuccessful()->assertSee('Not connected')
            ->assertSee('<img src="data:image/svg+xml;base64,', false);

        Http::fake([
            '*/account' => Http::sequence()->push(['message' => 'Key not found'], 401)->push(['email' => 'admin@example.com']),
            '*/contacts/lists*' => Http::response(['lists' => [['id' => 7, 'name' => 'Village news']], 'count' => 1]),
        ]);
        Livewire::test(EditMailingListProvider::class, ['record' => $brevo->getRouteKey()])
            ->fillForm(['api_key' => 'wrong'])
            ->call('save')
            ->assertHasFormErrors(['api_key']);
        $this->assertNull($brevo->fresh()->api_key);

        Livewire::test(EditMailingListProvider::class, ['record' => $brevo->getRouteKey()])
            ->fillForm(['api_key' => 'xkeysib-good'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditMailingListProvider::class, ['record' => $brevo->getRouteKey()])
            ->assertFormFieldExists('list_id', fn ($field): bool => $field->getOptions() === ['7' => 'Village news'])
            ->fillForm(['list_id' => '7', 'is_active' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $brevo->refresh();
        $this->assertSame(['xkeysib-good', '7', 'Village news', true], [$brevo->api_key, $brevo->list_id, $brevo->list_name, $brevo->is_active], 'A blank key keeps the saved one.');

        $this->actingAs($this->user('someone@example.com'));
        $this->withExceptionHandling()->get(MailingListProviderResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }
}
