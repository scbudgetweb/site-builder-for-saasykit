{{-- The visual builder's canvas only: a block that has nothing to show yet, so it can be selected and filled in. --}}
<div data-sb-node="block" data-sb-uid="{{ $uid }}" data-sb-type="{{ $type }}" class="sb-canvas-empty" style="margin: 1rem auto; max-width: 64rem;">
    {{ __(':block: nothing to show yet. Click to fill it in.', ['block' => $label]) }}
</div>
