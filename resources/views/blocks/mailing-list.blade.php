{{-- The Mailing list block: the sign-up form, with the block's own choices (MailingListBlock::viewData()), locked in the form. --}}
@livewire('site-builder.mailing-list-signup', $signup, key('mailing-list-'.($uid ?? md5(json_encode($signup)))))
