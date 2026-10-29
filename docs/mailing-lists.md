# Mailing lists

**Where:** Admin › Website › Mailing lists (and Branding › Mailing list for the look)
**Who can use it:** administrators with the *update settings* permission (`site-builder.permissions.mailing_lists`)

Visitors can join your mailing list from a sign-up form: the **Mailing list sign-up** block on any page, in a [Theme builder](theme-builder.md) template, or anywhere your app places it. The form adds them to a list at your mailing list service. **Brevo** is supported; others can be added by a developer.

## Connecting Brevo

1. In Brevo, go to **SMTP & API › API keys** and generate an **API key** (not the SMTP key).
2. In *Website › Mailing lists*, click **Set up** on Brevo and paste the key. It's checked with Brevo when you save, and stored encrypted. It's only ever kept here, never in a page or block.
3. Choose the **Default list** people join, and turn on **Use for sign-up forms**. (Click **Refresh lists** after making a new list in Brevo.)

Until a service is in use with a list chosen, sign-up forms don't show at all.

### Double opt-in

Turn on **Ask new subscribers to confirm** to have Brevo email them a confirmation link first, and only add them once they click it. You need:

- **Confirmation email template ID:** a Brevo template with the `{{ doubleoptin }}` link (Brevo has a ready-made *Default template - Double opt-in*); the ID is the number in its row under *Campaigns › Templates*.
- **Page after confirming:** where the link takes them, such as a "You're subscribed" page.

The form then says to check their inbox, instead of the thank-you message.

## The Mailing list sign-up block

| Setting | What it does |
|---|---|
| **Heading** / **Text** | Its own wording. Blank uses Branding › Mailing list |
| **Send sign-ups to** | The default list, or another list on the same account (e.g. one for this page's topic) |
| **Ask for their name** | Adds optional first and last name fields, sent to Brevo as FIRSTNAME and LASTNAME |
| **Ask them to tick the small print** | A tick box with the small print (and its Privacy Policy and Terms links) that must be ticked |
| **After signing up** | Show the thank-you message, or go to a page (e.g. `/thank-you`) |
| **More options** | Its own button text and thank-you message |

What a block chooses can't be changed from the visitor's browser: the form only ever signs people up to the list the block was set up with.

## Branding › Mailing list

The form's standard wording (small heading, heading, text, email placeholder, button text, thank-you message, small print) and look (button style, small print colour, background and text colours). In the small print, `:privacy` and `:terms` become links to your Privacy Policy and Terms of Service.

## Spam and notifications

The form ignores bots (a hidden field, and a minimum time on the page) and limits each visitor to five sign-ups an hour. If something goes wrong at Brevo, the visitor sees a polite apology and the reason goes to the log.

To be emailed each time someone signs up, set `SITE_BUILDER_MAILING_LIST_NOTIFY_EMAIL` (config `site-builder.mailing_lists.notify_email`) to an address.

## For developers

- Place the form anywhere with `<livewire:site-builder.mailing-list-signup />` (optionally `:heading`, `:intro`, `:list-id`, `:asks-name`, `:requires-consent`, `:redirect-url`, `:button-text`, `:success-message`; all locked once rendered).
- Another service: a class implementing `MailingLists\MailingListClient`, a match arm in `MailingListService::client()`, and a row in `site_builder_mailing_list_providers` (via a migration).
