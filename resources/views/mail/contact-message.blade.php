{{-- Plain text (Content text:), so nothing is escaped: an apostrophe stays an apostrophe. Each answer in the form's order; a message from before 1.7.2 has the standard ones. --}}
{!! __('A message from the contact form:form', ['form' => filled($contactMessage->form_name) ? ' "'.$contactMessage->form_name.'"' : '']) !!}

@if (is_array($contactMessage->fields))
@foreach ($contactMessage->answers(withEmpty: false) as $answer)
@if (str_contains($answer['value'], "\n"))

{!! $answer['label'] !!}:
{!! $answer['value'] !!}

@else
{!! $answer['label'] !!}: {!! $answer['value'] !!}
@endif
@endforeach

@else
{!! __('Name: :name', ['name' => $contactMessage->name ?: '-']) !!}
{!! __('Email: :email', ['email' => $contactMessage->email]) !!}
@if (filled($contactMessage->phone))
{!! __('Phone: :phone', ['phone' => $contactMessage->phone]) !!}
@endif
@if (filled($contactMessage->subject))
{!! __('Subject: :subject', ['subject' => $contactMessage->subject]) !!}
@endif

{!! $contactMessage->message !!}

@endif
{!! __('Sent from: :url', ['url' => $contactMessage->page_url ?: '-']) !!}
{!! __('Reply to this email to answer them. It\'s also kept in Website › Contact messages.') !!}
