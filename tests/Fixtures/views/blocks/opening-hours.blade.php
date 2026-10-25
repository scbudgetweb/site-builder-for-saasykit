{{-- The example host block's view: in the app's own views, using the package's heading partial. --}}
@include('site-builder::blocks.partials.heading')

<dl class="opening-hours mx-auto grid max-w-md grid-cols-2 gap-y-2">
    @foreach ($hours as $row)
        <dt class="font-semibold">{{ $row['day'] }}</dt>
        <dd>{{ $row['times'] }}</dd>
    @endforeach
</dl>
