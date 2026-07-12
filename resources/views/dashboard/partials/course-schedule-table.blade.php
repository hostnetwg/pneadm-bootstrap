@php
    $scheduleItems = $courseSchedule ?? [];
    $scheduleCount = count($scheduleItems);
    $rangeLabel = $courseScheduleRangeLabel ?? '';
    $scheduleByDate = collect($scheduleItems)->groupBy('start_date')->sortKeys();
@endphp

<div class="card dashboard-refresh-surface {{ ($class ?? '') !== '' ? $class : 'mt-4' }}" id="dashboardCourseScheduleCard">
    <div class="card-header py-2">
        <h5 class="mb-0 fs-6">
            <i class="bi bi-calendar-event me-1"></i>
            Terminy szkoleń w zakresie
            (<span id="dashboardCourseScheduleCount">{{ $scheduleCount }}</span>)
        </h5>
        <div class="small text-muted" id="dashboardCourseScheduleRange">{{ $rangeLabel }}</div>
    </div>
    <div class="card-body p-0" id="dashboardCourseScheduleContainer">
        @if($scheduleCount === 0)
            <p class="text-muted small p-3 mb-0" id="dashboardCourseScheduleEmpty">Brak szkoleń w wybranym zakresie dat.</p>
        @else
            <div class="table-responsive" id="dashboardCourseScheduleTableWrap">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Godzina</th>
                            <th>Szkolenie</th>
                            <th>Instruktor</th>
                        </tr>
                    </thead>
                    <tbody id="dashboardCourseScheduleBody">
                        @foreach($scheduleByDate as $date => $items)
                            @foreach($items as $item)
                                <tr>
                                    <td class="text-nowrap">{{ $date }}</td>
                                    <td class="text-nowrap">{{ $item['start_time'] ?? '—' }}</td>
                                    <td>
                                        <a href="{{ route('courses.show', $item['course_id']) }}" class="text-decoration-none">
                                            {{ $item['title'] }}
                                        </a>
                                        <span class="text-muted small">· #{{ $item['course_id'] }}</span>
                                    </td>
                                    <td class="text-muted">{{ $item['instructor_label'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
