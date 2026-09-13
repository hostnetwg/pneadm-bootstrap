@php
    $activeTab = $activeTab ?? 'course';
@endphp

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'course' ? 'active' : '' }}"
           href="{{ route('online-courses.edit', $onlineCourse) }}"
           @if($activeTab === 'course') aria-current="page" @endif>
            Dane kursu
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'sales' ? 'active' : '' }}"
           href="{{ route('online-courses.sales.edit', $onlineCourse) }}"
           @if($activeTab === 'sales') aria-current="page" @endif>
            Sprzedaż
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'enrollments' ? 'active' : '' }}"
           href="{{ route('online-courses.enrollments.index', $onlineCourse) }}"
           @if($activeTab === 'enrollments') aria-current="page" @endif>
            Dostępy
        </a>
    </li>
</ul>
