{{-- A star rating, 1 to 5 ($stars), with its count for screen readers. --}}
@php($stars = max(0, min(5, (int) ($stars ?? 0))))
@if ($stars > 0)
    <div class="flex items-center gap-0.5 text-amber-400" role="img" aria-label="{{ trans_choice(':count star out of 5|:count stars out of 5', $stars, ['count' => $stars]) }}">
        @for ($star = 0; $star < $stars; $star++)
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.07 3.29a1 1 0 0 0 .95.69h3.46c.97 0 1.37 1.24.59 1.81l-2.8 2.03a1 1 0 0 0-.36 1.12l1.07 3.29c.3.92-.76 1.69-1.54 1.12l-2.8-2.03a1 1 0 0 0-1.18 0l-2.8 2.03c-.78.57-1.84-.2-1.54-1.12l1.07-3.29a1 1 0 0 0-.36-1.12L2.98 8.72c-.78-.57-.38-1.81.59-1.81h3.46a1 1 0 0 0 .95-.69l1.07-3.29z"/></svg>
        @endfor
    </div>
@endif
