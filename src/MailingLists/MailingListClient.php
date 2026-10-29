<?php

namespace ScBudgetWeb\SiteBuilder\MailingLists;

/**
 * One mailing list service's API. To add a service: a class implementing this, a match arm in
 * MailingListService::client() and a row in site_builder_mailing_list_providers (via a migration).
 */
interface MailingListClient
{
    /**
     * @throws MailingListException when the API key is wrong or the service can't be reached
     */
    public function checkConnection(): void;

    /**
     * Every list in the account.
     *
     * @return array<string, string> list ID => list name
     *
     * @throws MailingListException
     */
    public function lists(): array;

    /**
     * Adds the email to the list (someone already in the account, even unsubscribed, is subscribed again), with
     * their name when given. With $doubleOptIn ({templateId, redirectUrl}), the service emails them a confirmation
     * link first.
     *
     * @param  array{first_name?: string, last_name?: string}  $name
     * @param  array{templateId: string, redirectUrl: string}|null  $doubleOptIn
     *
     * @throws MailingListException
     */
    public function subscribe(string $email, string $listId, array $name = [], ?array $doubleOptIn = null): void;
}
