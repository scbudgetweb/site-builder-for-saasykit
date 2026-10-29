<?php

namespace ScBudgetWeb\SiteBuilder\MailingLists;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Brevo's contacts API (https://developers.brevo.com). Uses an API key (Brevo › SMTP & API › API keys), not the SMTP
 * key.
 */
class BrevoClient implements MailingListClient
{
    public const BASE_URL = 'https://api.brevo.com/v3';

    /** Brevo's largest page of lists. */
    private const LISTS_PAGE_SIZE = 50;

    public function __construct(private readonly string $apiKey) {}

    public function checkConnection(): void
    {
        $this->send(fn (PendingRequest $request): Response => $request->get('/account'));
    }

    public function lists(): array
    {
        $lists = [];
        $offset = 0;

        do {
            $page = $this->send(fn (PendingRequest $request): Response => $request->get('/contacts/lists', [
                'limit' => self::LISTS_PAGE_SIZE,
                'offset' => $offset,
            ]))->json();

            foreach ($page['lists'] ?? [] as $list) {
                $lists[(string) $list['id']] = (string) $list['name'];
            }

            $offset += self::LISTS_PAGE_SIZE;
        } while (($page['lists'] ?? []) !== [] && $offset < (int) ($page['count'] ?? 0));

        return $lists;
    }

    public function subscribe(string $email, string $listId, array $name = [], ?array $doubleOptIn = null): void
    {
        $attributes = array_filter([
            'FIRSTNAME' => $name['first_name'] ?? null,
            'LASTNAME' => $name['last_name'] ?? null,
        ], fn (?string $value): bool => filled($value));

        if ($doubleOptIn !== null) {
            // Brevo emails a confirmation link (its own template), and adds them to the list once they click it.
            $this->send(fn (PendingRequest $request): Response => $request->post('/contacts/doubleOptinConfirmation', array_filter([
                'email' => $email,
                'includeListIds' => [(int) $listId],
                'templateId' => (int) $doubleOptIn['templateId'],
                'redirectionUrl' => $doubleOptIn['redirectUrl'],
                'attributes' => $attributes ?: null,
            ])));

            return;
        }

        // 201 for a new contact, 204 for an existing one. emailBlacklisted: false subscribes someone who had
        // unsubscribed again, since signing up again is fresh consent.
        $this->send(fn (PendingRequest $request): Response => $request->post('/contacts', array_filter([
            'email' => $email,
            'listIds' => [(int) $listId],
            'updateEnabled' => true,
            'emailBlacklisted' => false,
            'attributes' => $attributes ?: null,
        ], fn (mixed $value): bool => $value !== null)));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     *
     * @throws MailingListException
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call(Http::baseUrl(self::BASE_URL)
                ->withHeaders(['api-key' => $this->apiKey])
                ->acceptJson()
                ->asJson()
                ->timeout(5));
        } catch (ConnectionException $exception) {
            throw new MailingListException(__('Brevo could not be reached: :error', ['error' => $exception->getMessage()]), previous: $exception);
        }

        if ($response->failed()) {
            throw new MailingListException(__('Brevo said: :message', [
                'message' => $response->json('message') ?? $response->reason(),
            ]));
        }

        return $response;
    }
}
