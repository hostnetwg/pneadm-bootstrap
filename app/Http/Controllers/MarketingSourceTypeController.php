<?php

namespace App\Http\Controllers;

use App\Models\MarketingSourceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketingSourceTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sourceTypes = MarketingSourceType::query()
            ->withCount(['marketingCampaigns', 'formOrders'])
            ->ordered()
            ->get();

        $utmMediumOptions = config('marketing.utm_medium_options', []);

        return view('marketing-source-types.index', compact('sourceTypes', 'utmMediumOptions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('marketing-source-types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:marketing_source_types,slug',
            'utm_source' => 'nullable|string|max:100',
            'default_utm_medium' => 'required|string|max:50',
            'default_utm_content' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (! isset($validated['sort_order']) || (int) $validated['sort_order'] === 0) {
            $validated['sort_order'] = (int) MarketingSourceType::max('sort_order') + 1;
        }

        MarketingSourceType::create($validated);

        return redirect()->route('marketing-source-types.index')
            ->with('success', 'Typ źródła został dodany.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MarketingSourceType $marketingSourceType)
    {
        $marketingSourceType->load([
            'marketingCampaigns' => fn ($q) => $q->withCount('formOrders')->orderByDesc('created_at'),
        ])->loadCount('formOrders');

        $utmMediumOptions = config('marketing.utm_medium_options', []);

        return view('marketing-source-types.show', compact('marketingSourceType', 'utmMediumOptions'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MarketingSourceType $marketingSourceType)
    {
        return view('marketing-source-types.edit', compact('marketingSourceType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MarketingSourceType $marketingSourceType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('marketing_source_types', 'slug')->ignore($marketingSourceType->id),
            ],
            'utm_source' => 'nullable|string|max:100',
            'default_utm_medium' => 'required|string|max:50',
            'default_utm_content' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $marketingSourceType->update($validated);

        return redirect()->route('marketing-source-types.index')
            ->with('success', 'Typ źródła został zaktualizowany.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MarketingSourceType $marketingSourceType)
    {
        // Sprawdź czy typ jest używany przez kampanie
        if ($marketingSourceType->marketingCampaigns()->count() > 0) {
            return redirect()->route('marketing-source-types.index')
                ->with('error', 'Nie można usunąć typu źródła, który jest używany przez kampanie marketingowe.');
        }

        $marketingSourceType->delete();

        return redirect()->route('marketing-source-types.index')
            ->with('success', 'Typ źródła został usunięty.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:marketing_source_types,id'],
        ]);

        $order = array_values(array_unique(array_map('intval', $validated['order'])));
        $expectedIds = MarketingSourceType::query()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
        $sortedOrder = collect($order)->sort()->values()->all();

        if ($sortedOrder !== $expectedIds) {
            return response()->json([
                'message' => 'Lista musi zawierać wszystkie typy źródeł.',
            ], 422);
        }

        DB::transaction(function () use ($order): void {
            foreach ($order as $position => $id) {
                MarketingSourceType::query()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return response()->json(['message' => 'Kolejność zapisana.']);
    }
}
