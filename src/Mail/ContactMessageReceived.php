<?php

namespace ScBudgetWeb\SiteBuilder\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use ScBudgetWeb\SiteBuilder\Models\ContactMessage;

/**
 * A Contact form message, sent to the block's Deliver to address or the Support Email. Replying goes straight to the
 * visitor.
 */
class ContactMessageReceived extends Mailable
{
    public function __construct(public ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        $from = $this->contactMessage->name ?: $this->contactMessage->email;

        return new Envelope(
            replyTo: [new Address($this->contactMessage->email, $this->contactMessage->name)],
            subject: filled($this->contactMessage->subject)
                ? __('Contact form: :subject', ['subject' => $this->contactMessage->subject])
                : __('Contact form: a message from :name', ['name' => $from]),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'site-builder::mail.contact-message');
    }
}
