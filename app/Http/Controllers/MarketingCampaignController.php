<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MarketingCampaign;
use App\Models\MarketingSourceType;

class MarketingCampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = MarketingCampaign::with('sourceType')->withCount('formOrders');
        
        // Wyszukiwanie
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('campaign_code', 'LIKE', "%{$search}%")
                  ->orWhere('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }
        
        // Filtr według typu źródła
        if ($request->filled('source_type_id')) {
            $query->where('source_type_id', $request->get('source_type_id'));
        }
        
        // Filtr według statusu
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->get('is_active'));
        }
        
        // Sortowanie
        $sortBy = $request->get('sort_by', 'campaign_code');
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Obsługa sortowania według relacji
        if ($sortBy === 'source_type') {
            $query->join('marketing_source_types', 'marketing_campaigns.source_type_id', '=', 'marketing_source_types.id')
                  ->orderBy('marketing_source_types.name', $sortOrder)
                  ->select('marketing_campaigns.*');
        } elseif ($sortBy === 'orders_count') {
            $query->orderBy('form_orders_count', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }
        
        // Paginacja
        $perPage = $request->get('per_page', 20);
        $campaigns = $query->paginate($perPage)->withQueryString();
        
        // Pobierz typy źródeł dla filtra
        $sourceTypes = MarketingSourceType::active()->ordered()->get();
        
        return view('marketing-campaigns.index', compact('campaigns', 'sourceTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $sourceTypes = MarketingSourceType::active()->ordered()->get();
        return view('marketing-campaigns.create', compact('sourceTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'campaign_code' => 'required|string|max:50|unique:marketing_campaigns',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source_type_id' => 'required|exists:marketing_source_types,id',
            'is_active' => 'boolean',
        ]);

        MarketingCampaign::create($request->all());

        return redirect()->route('marketing-campaigns.index')
            ->with('success', 'Kampania marketingowa została utworzona.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MarketingCampaign $marketingCampaign)
    {
        $marketingCampaign->load('sourceType');
        $formOrders = $marketingCampaign->formOrders()->with('primaryParticipant')->paginate(20);
        
        return view('marketing-campaigns.show', compact('marketingCampaign', 'formOrders'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MarketingCampaign $marketingCampaign)
    {
        $sourceTypes = MarketingSourceType::active()->ordered()->get();
        return view('marketing-campaigns.edit', compact('marketingCampaign', 'sourceTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MarketingCampaign $marketingCampaign)
    {
        $request->validate([
            'campaign_code' => 'required|string|max:50|unique:marketing_campaigns,campaign_code,' . $marketingCampaign->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source_type_id' => 'required|exists:marketing_source_types,id',
            'is_active' => 'boolean',
        ]);

        $marketingCampaign->update($request->all());

        return redirect()->route('marketing-campaigns.index')
            ->with('success', 'Kampania marketingowa została zaktualizowana.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MarketingCampaign $marketingCampaign)
    {
        $marketingCampaign->delete();

        return redirect()->route('marketing-campaigns.index')
            ->with('success', 'Kampania marketingowa została usunięta.');
    }
}