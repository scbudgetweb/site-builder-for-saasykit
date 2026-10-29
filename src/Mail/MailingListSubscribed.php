<?php

namespace ScBudgetWeb\SiteBuilder\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells an admin (site-builder.mailing_lists.notify_email) that someone signed up, so it can be checked by hand.
 */
class MailingListSubscribed extends Mailable
{
    public function __construct(
        public string $subscriberEmail,
        public string $providerName,
        public string $listName,
        public string $pageUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':email just subscribed to the mailing list', ['email' => $this->subscriberEmail]));
    }

    public function content(): Content
    {
        return new Content(text: 'site-builder::mail.mailing-list-subscribed');
    }
}
