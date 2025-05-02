<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class ClickMeetingTrainingController extends Controller
{
    /**
     * Wyświetla listę szkoleń ClickMeeting.
     */
    public function index(): View
    {
        /* -----------------------------------------------------------------
         | 1.  Konfiguracja API
         |----------------------------------------------------------------- */
        $baseUrl = config('services.clickmeeting.url', 'https://api.clickmeeting.com/v1/');
        $apiKey  = config('services.clickmeeting.token');

        /* -----------------------------------------------------------------
         | 2.  Pobranie listy aktywnych i zaplanowanych konferencji
         |----------------------------------------------------------------- */
        $roomsResp = Http::baseUrl($baseUrl)
            ->withHeaders(['X-Api-Key' => $apiKey])
            ->get('conferences');

        abort_if($roomsResp->failed(), 502, 'Błąd pobierania listy konferencji');

        $trainings = collect($roomsResp->json()['active_conferences'] ?? [])
            ->merge($roomsResp->json()['scheduled_conferences'] ?? [])
            ->map(function (array $room) {
                // Przyjazne daty
                $raw = $room['starts_at'] ?? $room['start_time'] ?? null;
                $room['pretty_date'] = $raw
                    ? Carbon::parse($raw)->tz('Europe/Warsaw')->format('d.m.Y H:i')
                    : '—';

                return $room;
            })
            ->sortBy(fn ($t) => $t['starts_at'] ?? $t['start_time'] ?? null) // rosnąco
            ->values();

        /* -----------------------------------------------------------------
         | 3.  Widok
         |----------------------------------------------------------------- */
        return view('clickmeeting.trainings.index', ['trainings' => $trainings]);
    }
}
