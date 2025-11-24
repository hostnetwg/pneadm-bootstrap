<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MarketingSourceType;
use Illuminate\Validation\Rule;

class MarketingSourceTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sourceTypes = MarketingSourceType::with('marketingCampaigns')
            ->withCount('formOrders')
            ->ordered()
            ->paginate(20);
        
        return view('marketing-source-types.index', compact('sourceTypes'));
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
            'description' => 'nullable|string',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        MarketingSourceType::create($validated);

        return redirect()->route('marketing-source-types.index')
            ->with('success', 'Typ źródła został dodany.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MarketingSourceType $marketingSourceType)
    {
        $marketingSourceType->load('marketingCampaigns');
        
        return view('marketing-source-types.show', compact('marketingSourceType'));
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
                Rule::unique('marketing_source_types', 'slug')->ignore($marketingSourceType->id)
            ],
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
}
