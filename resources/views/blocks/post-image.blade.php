{{-- The post's image: the first one, eager (it's usually near the top), at its own shape or trimmed to one. --}}
<img src="{{ $imageUrl }}" alt="{{ $alt }}" decoding="async" fetchpriority="high"
     class="w-full rounded-sb-image object-cover {{ $shape }}"
     @if ($imageStyle !== '') style="{{ $imageStyle }}" @endif>
