{{-- Theme Builder › Footer links: the footer's link columns (Website › Navigation › Footer). --}}
@if ($columns !== [])
    <div @class(['grid grid-cols-1 gap-8', $grid])>
        @foreach ($columns as $column)
            <nav aria-label="{{ $column['heading'] !== '' ? $column['heading'] : __('Footer') }}">
                @if ($column['heading'] !== '')
                    <h2 class="text-sm font-semibold uppercase tracking-wider">{{ $column['heading'] }}</h2>
                @endif
                <ul @class(['block-text space-y-2.5 text-sm', 'mt-4' => $column['heading'] !== ''])>
                    @foreach ($column['links'] as $link)
                        <li><a href="{{ $link['url'] }}" @if ($link['new_tab']) target="_blank" rel="noopener" @endif class="opacity-80 transition-opacity hover:opacity-100 focus-visible:opacity-100">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endforeach
    </div>
@endif
