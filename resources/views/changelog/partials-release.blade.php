@foreach($release['summary'] as $line)
    <p class="mb-2">{{ $line }}</p>
@endforeach

@if(count($release['bullets']) > 0)
    <ul class="mb-0">
        @foreach($release['bullets'] as $bullet)
            <li>{!! \App\Services\ReleaseChangelogService::formatBulletHtml($bullet) !!}</li>
        @endforeach
    </ul>
@endif
