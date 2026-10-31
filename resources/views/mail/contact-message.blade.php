{{-- Plain text (Content text:), so nothing is escaped: an apostrophe stays an apostrophe. --}}
{!! __('A message from the contact form:form', ['form' => filled($contactMessage->form_name) ? ' "'.$contactMessage->form_name.'"' : '']) !!}

{!! __('Name: :name', ['name' => $contactMessage->name ?: '-']) !!}
{!! __('Email: :email', ['email' => $contactMessage->email]) !!}
@if (filled($contactMessage->phone))
{!! __('Phone: :phone', ['phone' => $contactMessage->phone]) !!}
@endif
@if (filled($contactMessage->subject))
{!! __('Subject: :subject', ['subject' => $contactMessage->subject]) !!}
@endif

{!! $contactMessage->message !!}

{!! __('Sent from: :url', ['url' => $contactMessage->page_url ?: '-']) !!}
{!! __('Reply to this email to answer them. It\'s also kept in Website › Contact messages.') !!}
