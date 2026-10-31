{{-- The Contact form block: its heading, then the form (ContactForm) with the block's choices and button look locked in. --}}
@include('site-builder::blocks.partials.heading')

@php($look = \ScBudgetWeb\SiteBuilder\Blocks\PageBlock::buttonLook(\ScBudgetWeb\SiteBuilder\Blocks\PageBlock::withLook([], $block['button_design'] ?? null), $isDark ?? false))
@livewire('site-builder.contact-form', [...$form, 'buttonClass' => $look['class'], 'buttonStyle' => $look['style']], key('contact-form-'.($uid ?? md5(json_encode($form)))))
