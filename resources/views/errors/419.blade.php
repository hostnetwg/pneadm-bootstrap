@extends('errors::minimal')

@section('title', 'Sesja wygasła')
@section('code', '419')
@section('message')
    Sesja wygasła. Odśwież stronę i zapisz ponownie.
    <div class="mt-4">
        <a href="{{ url('/settings/pnedu-zakupy') }}" class="underline text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">Wróć do ustawień Zakupy pnedu.pl</a>
    </div>
@endsection
