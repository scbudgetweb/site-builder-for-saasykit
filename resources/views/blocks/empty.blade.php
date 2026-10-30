{{-- A block whose content pulled in is empty (PageBlock::whenEmpty()): its heading, and a message when it has one. --}}
@include('site-builder::blocks.partials.heading')

@if ($emptyMessage)
    <p class="block-text sb-empty-message text-center opacity-75">{{ $emptyMessage }}</p>
@endif
