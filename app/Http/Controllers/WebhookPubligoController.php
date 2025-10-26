<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WebhookPubligo;
use Illuminate\Support\Facades\DB;

class WebhookPubligoController extends Controller
{
    public function index(Request $request)
    {
        // Pobranie parametrów sortowania - używamy tylko kolumn, które istnieją
        $sortBy = $request->query('sort', 'data');
        $order = $request->query('order', 'desc');

        try {
            // Używamy modelu Eloquent zamiast DB::table()
            $webhookData = WebhookPubligo::orderBy($sortBy, $order)->paginate(15);
        } catch (\Exception $e) {
            // Jeśli sortowanie nie działa, używamy domyślnego sortowania po ID
            try {
                $webhookData = WebhookPubligo::orderBy('data', 'desc')->paginate(15);
            } catch (\Exception $e2) {
                // Jeśli nawet to nie działa, zwracamy pustą kolekcję
                $webhookData = collect([])->paginate(15);
                session()->flash('error', 'Nie można połączyć się z bazą danych Certgen: ' . $e2->getMessage());
            }
        }

        return view('certgen.webhook_data.index', compact('webhookData'));
    }

    public function create()
    {
        $instructors = \App\Models\Instructor::where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
        
        return view('certgen.webhook_data.create', compact('instructors'));
    }

    public function store(Request $request)
    {
        try {
            // Sprawdź wartość clickmeeting przed walidacją
            if ($request->has('clickmeeting') && $request->clickmeeting !== null && $request->clickmeeting !== '') {
                $clickmeetingValue = (int) $request->clickmeeting;
                if ($clickmeetingValue < 0 || $clickmeetingValue > 2147483647) {
                    return redirect()->route('certgen.webhook_data.create')
                        ->with('error', 'Wartość ClickMeeting musi być dodatnią liczbą całkowitą.')
                        ->withInput();
                }
            }

            $validated = $request->validate([
                'id_produktu' => 'nullable|integer',
                'data' => 'nullable|date',
                'id_sendy' => 'nullable|string|max:255',
                'clickmeeting' => 'nullable|integer',
                'temat' => 'nullable|string',
                'instruktor' => 'nullable|string|max:255',
            ]);

            // Ustaw domyślną wartość dla temat jeśli jest null
            if (empty($validated['temat'])) {
                $validated['temat'] = '';
            }

            WebhookPubligo::create($validated);

            return redirect()->route('certgen.webhook_data.index')
                ->with('success', 'Nowy rekord został dodany.');
        } catch (\Exception $e) {
            return redirect()->route('certgen.webhook_data.create')
                ->with('error', 'Wystąpił błąd podczas dodawania: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $webhookRecord = WebhookPubligo::findOrFail($id);
            return view('certgen.webhook_data.show', compact('webhookRecord'));
        } catch (\Exception $e) {
            return redirect()->route('certgen.webhook_data.index')
                ->with('error', 'Rekord nie został znaleziony lub wystąpił błąd: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $webhookRecord = WebhookPubligo::findOrFail($id);
            $instructors = \App\Models\Instructor::where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
            
            return view('certgen.webhook_data.edit', compact('webhookRecord', 'instructors'));
        } catch (\Exception $e) {
            return redirect()->route('certgen.webhook_data.index')
                ->with('error', 'Rekord nie został znaleziony lub wystąpił błąd: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $webhookRecord = WebhookPubligo::findOrFail($id);
            
            // Sprawdź wartość clickmeeting przed walidacją
            if ($request->has('clickmeeting') && $request->clickmeeting !== null && $request->clickmeeting !== '') {
                $clickmeetingValue = (int) $request->clickmeeting;
                if ($clickmeetingValue < 0 || $clickmeetingValue > 2147483647) {
                    return redirect()->route('certgen.webhook_data.edit', $id)
                        ->with('error', 'Wartość ClickMeeting musi być dodatnią liczbą całkowitą.')
                        ->withInput();
                }
            }
            
            $validated = $request->validate([
                'id_produktu' => 'nullable|integer',
                'data' => 'nullable|date',
                'id_sendy' => 'nullable|string|max:255',
                'clickmeeting' => 'nullable|integer',
                'temat' => 'nullable|string',
                'instruktor' => 'nullable|string|max:255',
            ]);

            // Ustaw domyślną wartość dla temat jeśli jest null
            if (empty($validated['temat'])) {
                $validated['temat'] = '';
            }

            $webhookRecord->update($validated);

            return redirect()->route('certgen.webhook_data.index')
                ->with('success', 'Rekord został zaktualizowany.');
        } catch (\Exception $e) {
            return redirect()->route('certgen.webhook_data.edit', $id)
                ->with('error', 'Wystąpił błąd podczas aktualizacji: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $webhookRecord = WebhookPubligo::findOrFail($id);
            $webhookRecord->delete();

            return redirect()->route('certgen.webhook_data.index')
                ->with('success', 'Rekord został usunięty.');
        } catch (\Exception $e) {
            return redirect()->route('certgen.webhook_data.index')
                ->with('error', 'Wystąpił błąd podczas usuwania: ' . $e->getMessage());
        }
    }


}
