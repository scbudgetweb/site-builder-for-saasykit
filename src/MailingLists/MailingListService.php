<?php

namespace ScBudgetWeb\SiteBuilder\MailingLists;

use InvalidArgumentException;
use ScBudgetWeb\SiteBuilder\Models\MailingListProvider;

/**
 * Talks to the mailing list service: checks API keys, lists the account's lists (cached, as the admin forms ask for
 * them on every render) and subscribes people from sign-up forms.
 */
class MailingListService
{
    private const LISTS_CACHE_SECONDS = 600;

    /**
     * The service's API client, with the given API key or the saved one.
     */
    public function client(MailingListProvider $provider, ?string $apiKey = null): MailingListClient
    {
        $apiKey ??= (string) $provider->api_key;

        return match ($provider->slug) {
            MailingListProvider::BREVO => new BrevoClient($apiKey),
            default => throw new InvalidArgumentException("No mailing list client for the {$provider->slug} provider."),
        };
    }

    /**
     * The account's lists, for the admin's list pickers. Empty with no key or when the service fails (a failure isn't
     * cached, so the next render tries again).
     *
     * @return array<string, string> list ID => list name
     */
    public function lists(MailingListProvider $provider): array
    {
        if (! $provider->isConnected()) {
            return [];
        }

        try {
            return cache()->remember($this->listsCacheKey($provider), self::LISTS_CACHE_SECONDS, fn (): array => $this->client($provider)->lists());
        } catch (MailingListException) {
            return [];
        }
    }

    public function forgetLists(MailingListProvider $provider): void
    {
        cache()->forget($this->listsCacheKey($provider));
    }

    /**
     * Signs someone up to a list (the default one, or another on the account that a block chose), with double opt-in
     * when it's on.
     *
     * @param  array{first_name?: string, last_name?: string}  $name
     *
     * @throws MailingListException
     */
    public function subscribe(MailingListProvider $provider, string $email, ?string $listId = null, array $name = []): void
    {
        $doubleOptIn = $provider->double_opt_in && filled($provider->double_opt_in_template_id) && filled($provider->double_opt_in_redirect_url)
            ? ['templateId' => (string) $provider->double_opt_in_template_id, 'redirectUrl' => (string) $provider->double_opt_in_redirect_url]
            : null;

        $this->client($provider)->subscribe($email, $listId ?? (string) $provider->list_id, $name, $doubleOptIn);
    }

    private function listsCacheKey(MailingListProvider $provider): string
    {
        return 'site-builder.mailing-list-lists:'.$provider->getKey();
    }
}
