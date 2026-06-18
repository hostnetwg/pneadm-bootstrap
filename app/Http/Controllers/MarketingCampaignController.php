<?php

namespace App\Http\Controllers;

use App\Models\MarketingCampaign;
use App\Models\MarketingSourceType;
use App\Models\Course;
use App\Services\MarketingCampaignUrlBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MarketingCampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, MarketingCampaignUrlBuilder $urlBuilder)
    {
        $query = MarketingCampaign::with(['sourceType', 'course'])
            ->withCount('formOrders')
            ->withSum('statsDaily as link_entries_total', 'link_entries');

        // Wyszukiwanie
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('campaign_code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filtr według typu źródła
        if ($request->filled('source_type_id')) {
            $query->where('source_type_id', $request->get('source_type_id'));
        }

        // Filtr według szkolenia
        $filteredCourse = null;
        if ($request->filled('course_id')) {
            $courseId = $request->integer('course_id');
            $query->where('course_id', $courseId);
            $filteredCourse = Course::query()->find($courseId);
        }

        // Filtr według statusu
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->get('is_active'));
        }

        // Sortowanie
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = strtolower((string) $request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Obsługa sortowania według relacji
        if ($sortBy === 'source_type') {
            $query->join('marketing_source_types', 'marketing_campaigns.source_type_id', '=', 'marketing_source_types.id')
                ->orderBy('marketing_source_types.name', $sortOrder)
                ->select('marketing_campaigns.*');
        } elseif ($sortBy === 'orders_count') {
            $query->orderBy('form_orders_count', $sortOrder);
        } elseif ($sortBy === 'link_entries_count') {
            $query->orderByRaw(
                '(SELECT COALESCE(SUM(link_entries), 0) FROM marketing_campaign_stats_daily WHERE campaign_code = marketing_campaigns.campaign_code) '.$sortOrder
            )->orderBy('marketing_campaigns.created_at', 'desc');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginacja
        $perPage = $request->get('per_page', 20);
        $campaigns = $query->paginate($perPage)->withQueryString();

        // Pobierz typy źródeł dla filtra (wszystkie — także wyłączone, żeby link z typów źródeł działał)
        $sourceTypes = MarketingSourceType::ordered()->get();

        $campaignUrlsById = [];
        foreach ($campaigns as $campaign) {
            $campaignUrlsById[$campaign->id] = $urlBuilder->buildForCampaign($campaign);
        }

        return view('marketing-campaigns.index', compact('campaigns', 'sourceTypes', 'campaignUrlsById', 'filteredCourse'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $sourceTypes = $this->sourceTypesForCampaignForm(old('source_type_id'));
        $nextCampaignCode = $this->getNextCampaignCode();
        $selectedCourse = $this->selectedCourseForForm(old('course_id'));
        $utmMediumOptions = config('marketing.utm_medium_options', []);

        return view('marketing-campaigns.create', compact('sourceTypes', 'nextCampaignCode', 'selectedCourse', 'utmMediumOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'campaign_code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source_type_id' => 'required|exists:marketing_source_types,id',
            'utm_medium_custom' => 'sometimes|boolean',
            'utm_medium' => 'nullable|string|max:50',
            'utm_content' => 'nullable|string|max:100',
            'course_id' => 'nullable|integer|exists:courses,id',
            'landing_target' => 'nullable|in:course_show,order_form',
            'is_active' => 'boolean',
        ]);

        $this->validateCampaignUtmMedium($request);

        // Sprawdź czy kod kampanii już istnieje (także wśród soft-deleted — unikalny indeks w BD obejmuje wszystkie wiersze)
        $campaignCode = $request->get('campaign_code');

        // Jeśli kod jest numeryczny i już istnieje, znajdź następny wolny numer
        if (is_numeric($campaignCode) && MarketingCampaign::withTrashed()->where('campaign_code', $campaignCode)->exists()) {
            $campaignCode = $this->findNextAvailableNumericCode($campaignCode);
        } else {
            // Sprawdź unikalność dla kodów nie-numerycznych
            if (MarketingCampaign::withTrashed()->where('campaign_code', $campaignCode)->exists()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['campaign_code' => 'Kod kampanii już istnieje.']);
            }
        }

        $data = $request->only([
            'name', 'description', 'source_type_id',
            'course_id', 'landing_target', 'is_active',
        ]);
        $data['campaign_code'] = $campaignCode;
        $data['utm_medium'] = $this->resolveCampaignUtmMedium($request);
        $data['utm_content'] = $this->normalizeCampaignUtmContent($request);
        if (blank($data['landing_target'] ?? null)) {
            $data['landing_target'] = 'order_form';
        }

        $campaign = MarketingCampaign::create($data);

        return redirect()->route('marketing-campaigns.show', $campaign)
            ->with('success', 'Kampania marketingowa została utworzona. Skopiuj linki poniżej.');
    }

    /**
     * Wstępia formularz create danymi z kampanii źródłowej — bez zapisu do bazy.
     */
    public function duplicate(MarketingCampaign $marketingCampaign)
    {
        $marketingCampaign->load('sourceType');

        $defaultMedium = $marketingCampaign->sourceType?->default_utm_medium ?? 'paid';
        $hasCustomMedium = filled($marketingCampaign->utm_medium)
            && $marketingCampaign->utm_medium !== $defaultMedium;

        $input = array_filter([
            'campaign_code' => $this->suggestDuplicateCampaignCode($marketingCampaign->campaign_code),
            'name' => $this->suggestDuplicateName($marketingCampaign->name),
            'description' => $marketingCampaign->description,
            'source_type_id' => $marketingCampaign->source_type_id,
            'course_id' => $marketingCampaign->course_id,
            'landing_target' => $marketingCampaign->landing_target ?: 'order_form',
            'utm_content' => $marketingCampaign->utm_content
                ?: $marketingCampaign->sourceType?->default_utm_content,
            'utm_medium_custom' => $hasCustomMedium ? '1' : null,
            'utm_medium' => $hasCustomMedium ? $marketingCampaign->utm_medium : null,
            'is_active' => '1',
        ], fn ($value) => $value !== null && $value !== '');

        return redirect()
            ->route('marketing-campaigns.create')
            ->withInput($input)
            ->with('duplicate_from', [
                'id' => $marketingCampaign->id,
                'campaign_code' => $marketingCampaign->campaign_code,
                'name' => $marketingCampaign->name,
            ]);
    }

    /**
     * Znajdź następny wolny kod kampanii (największy numeryczny + 1)
     */
    private function getNextCampaignCode(): string
    {
        // Pobierz wszystkie kody kampanii (w tym kosz — zbiór kodów musi być spójny z indeksem unique w BD)
        $allCodes = MarketingCampaign::withTrashed()->pluck('campaign_code')->toArray();

        // Filtruj tylko numeryczne kody
        $numericCodes = array_filter($allCodes, function ($code) {
            return is_numeric($code) && ctype_digit($code);
        });

        if (empty($numericCodes)) {
            // Jeśli nie ma numerycznych kodów, zacznij od 1
            return '1';
        }

        // Znajdź największy numeryczny kod
        $maxCode = max(array_map('intval', $numericCodes));

        return (string) ($maxCode + 1);
    }

    /**
     * Znajdź następny dostępny numeryczny kod kampanii
     */
    private function findNextAvailableNumericCode(string $startCode): string
    {
        $code = (int) $startCode;

        // Szukaj wolnego numeru (maksymalnie 100 prób)
        for ($i = 0; $i < 100; $i++) {
            $testCode = (string) $code;
            if (! MarketingCampaign::withTrashed()->where('campaign_code', $testCode)->exists()) {
                return $testCode;
            }
            $code++;
        }

        // Jeśli nie znaleziono w zakresie, użyj metody getNextCampaignCode
        return $this->getNextCampaignCode();
    }

    /**
     * Display the specified resource.
     */
    public function show(MarketingCampaign $marketingCampaign, MarketingCampaignUrlBuilder $urlBuilder)
    {
        $marketingCampaign->load(['sourceType', 'course']);
        $formOrders = $marketingCampaign->formOrders()->with('primaryParticipant')->paginate(20);
        $campaignUrls = $urlBuilder->buildForCampaign($marketingCampaign);

        return view('marketing-campaigns.show', compact('marketingCampaign', 'formOrders', 'campaignUrls'));
    }

    public function edit(MarketingCampaign $marketingCampaign, MarketingCampaignUrlBuilder $urlBuilder)
    {
        $marketingCampaign->load(['course.instructor:id,title,first_name,last_name', 'sourceType']);
        $sourceTypes = $this->sourceTypesForCampaignForm(old('source_type_id', $marketingCampaign->source_type_id));
        $selectedCourse = $this->selectedCourseForForm(
            old('course_id', $marketingCampaign->course_id),
            $marketingCampaign->course,
        );
        $utmMediumOptions = config('marketing.utm_medium_options', []);
        $campaignUrls = $urlBuilder->buildForCampaign($marketingCampaign);

        return view('marketing-campaigns.edit', compact(
            'marketingCampaign',
            'sourceTypes',
            'selectedCourse',
            'utmMediumOptions',
            'campaignUrls',
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MarketingCampaign $marketingCampaign)
    {
        $request->validate([
            'campaign_code' => [
                'required',
                'string',
                'max:50',
                function (string $attribute, mixed $value, \Closure $fail) use ($marketingCampaign): void {
                    if (MarketingCampaign::withTrashed()
                        ->where('campaign_code', (string) $value)
                        ->whereKeyNot($marketingCampaign->id)
                        ->exists()
                    ) {
                        $fail('Ten kod kampanii jest już zajęty (również w rekordzie usuniętym miękkim). Wybierz inny kod.');
                    }
                },
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source_type_id' => 'required|exists:marketing_source_types,id',
            'utm_medium_custom' => 'sometimes|boolean',
            'utm_medium' => 'nullable|string|max:50',
            'utm_content' => 'nullable|string|max:100',
            'course_id' => 'nullable|integer|exists:courses,id',
            'landing_target' => 'nullable|in:course_show,order_form',
            'is_active' => 'boolean',
        ]);

        $this->validateCampaignUtmMedium($request);

        $marketingCampaign->update([
            ...$request->only([
                'campaign_code', 'name', 'description', 'source_type_id',
                'course_id', 'landing_target', 'is_active',
            ]),
            'utm_medium' => $this->resolveCampaignUtmMedium($request),
            'utm_content' => $this->normalizeCampaignUtmContent($request),
        ]);

        return redirect()->route('marketing-campaigns.show', $marketingCampaign)
            ->with('success', 'Kampania marketingowa została zaktualizowana. Skopiuj linki poniżej.');
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

    public function verifyShortLink(MarketingCampaign $marketingCampaign, MarketingCampaignUrlBuilder $urlBuilder)
    {
        $urls = $urlBuilder->buildForCampaign($marketingCampaign);

        if (empty($urls['short']) || empty($urls['utm'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Brak powiązanego szkolenia — ustaw je w kampanii, aby wygenerować link krótki.',
            ], 422);
        }

        try {
            $shortUrlForVerify = $this->shortUrlForInternalVerify($urls['short'], $urlBuilder);

            $response = Http::timeout(15)
                ->withOptions(['allow_redirects' => false])
                ->get($shortUrlForVerify);

            $status = $response->status();
            $location = $response->header('Location');

            if (is_string($location) && $location !== '' && ! str_starts_with($location, 'http')) {
                $location = rtrim($urlBuilder->pneduBaseUrl(), '/').'/'.ltrim($location, '/');
            }

            if ($status === 404) {
                return response()->json([
                    'ok' => false,
                    'short_url' => $urls['short'],
                    'expected_url' => $urls['utm'],
                    'redirect_status' => $status,
                    'message' => 'pnedu zwróciło 404 — na dev upewnij się, że PNEDU_PUBLIC_URL=http://localhost:8081 i kontener pnedu działa. Na produkcji wdróż kod /l/ (git pull + route:clear).',
                ], 502);
            }

            $ok = in_array($status, [301, 302, 303, 307, 308], true)
                && $this->redirectMatchesUtmUrl($location, $urls['utm']);

            return response()->json([
                'ok' => $ok,
                'short_url' => $urls['short'],
                'expected_url' => $urls['utm'],
                'redirect_status' => $status,
                'redirect_to' => $location,
                'message' => $ok
                    ? 'Przekierowanie działa — link krótki prowadzi do właściwego adresu z parametrami UTM.'
                    : 'Przekierowanie nie zgadza się z linkiem UTM (status '.$status.'). Sprawdź ustawienia kampanii lub logi pnedu.',
            ], $ok ? 200 : 502);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'short_url' => $urls['short'],
                'expected_url' => $urls['utm'],
                'message' => 'Nie udało się połączyć z pnedu.pl: '.$exception->getMessage(),
            ], 502);
        }
    }

    private function redirectMatchesUtmUrl(?string $redirectUrl, string $expectedUrl): bool
    {
        if (! is_string($redirectUrl) || $redirectUrl === '') {
            return false;
        }

        $redirectParts = parse_url($redirectUrl);
        $expectedParts = parse_url($expectedUrl);

        if (($redirectParts['path'] ?? '') !== ($expectedParts['path'] ?? '')) {
            return false;
        }

        parse_str($redirectParts['query'] ?? '', $redirectQuery);
        parse_str($expectedParts['query'] ?? '', $expectedQuery);

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'] as $key) {
            $expected = $expectedQuery[$key] ?? null;
            $actual = $redirectQuery[$key] ?? null;

            if ($expected === null || $expected === '') {
                continue;
            }

            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }

    private function shortUrlForInternalVerify(string $publicShortUrl, MarketingCampaignUrlBuilder $urlBuilder): string
    {
        $publicBase = rtrim($urlBuilder->pneduBaseUrl(), '/');
        $internalBase = rtrim((string) config('marketing.pnedu_internal_url', $publicBase), '/');

        if ($publicBase === $internalBase) {
            return $publicShortUrl;
        }

        return $internalBase.substr($publicShortUrl, strlen($publicBase));
    }

    private function selectedCourseForForm(mixed $courseId, ?Course $loaded = null): ?Course
    {
        if ($courseId === null || $courseId === '') {
            return null;
        }

        $id = (int) $courseId;
        if ($id <= 0) {
            return null;
        }

        if ($loaded && (int) $loaded->id === $id) {
            return $loaded;
        }

        return Course::query()
            ->with('instructor:id,title,first_name,last_name')
            ->find($id);
    }

    /**
     * Aktywne typy źródeł + opcjonalnie aktualnie wybrany (np. wyłączony) przy edycji.
     *
     * @return \Illuminate\Support\Collection<int, MarketingSourceType>
     */
    private function sourceTypesForCampaignForm(mixed $selectedId = null)
    {
        $types = MarketingSourceType::active()->ordered()->get();

        if ($selectedId === null || $selectedId === '') {
            return $types;
        }

        $selectedId = (int) $selectedId;
        if ($selectedId > 0 && ! $types->contains('id', $selectedId)) {
            $extra = MarketingSourceType::query()->find($selectedId);
            if ($extra) {
                $types = $types->push($extra)->sortBy([
                    ['sort_order', 'asc'],
                    ['name', 'asc'],
                ])->values();
            }
        }

        return $types;
    }

    private function validateCampaignUtmMedium(Request $request): void
    {
        if (! $request->boolean('utm_medium_custom')) {
            return;
        }

        $allowed = array_keys(config('marketing.utm_medium_options', []));
        $medium = (string) $request->input('utm_medium', '');

        if ($medium === '' || ! in_array($medium, $allowed, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'utm_medium' => 'Wybierz dozwoloną wartość medium lub odznacz „Niestandardowe utm_medium”.',
            ]);
        }
    }

    private function resolveCampaignUtmMedium(Request $request): ?string
    {
        if (! $request->boolean('utm_medium_custom')) {
            return null;
        }

        $medium = (string) $request->input('utm_medium', '');
        $sourceType = MarketingSourceType::query()->find($request->input('source_type_id'));

        if ($medium === '' || ($sourceType && $medium === $sourceType->default_utm_medium)) {
            return null;
        }

        return $medium;
    }

    private function normalizeCampaignUtmContent(Request $request): ?string
    {
        $content = trim((string) $request->input('utm_content', ''));

        if ($content !== '') {
            return $content;
        }

        $sourceType = MarketingSourceType::query()->find($request->input('source_type_id'));
        $default = trim((string) ($sourceType?->default_utm_content ?? ''));

        return $default !== '' ? $default : null;
    }

    private function suggestDuplicateCampaignCode(string $baseCode): string
    {
        $baseCode = trim($baseCode);
        if ($baseCode === '') {
            return '';
        }

        $suffixes = ['-2', '-3', '-4', '-5', '-kopia'];
        for ($i = 6; $i <= 20; $i++) {
            $suffixes[] = '-'.$i;
        }

        foreach ($suffixes as $suffix) {
            $candidate = $this->appendSuffixToCampaignCode($baseCode, $suffix);
            if ($candidate !== '' && ! $this->campaignCodeExists($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function suggestDuplicateName(string $name): string
    {
        $name = trim($name);
        $suffix = ' (kopia)';

        if ($name === '') {
            return 'Kampania (kopia)';
        }

        if (str_ends_with($name, '(kopia)')) {
            return strlen($name) + 2 <= 255 ? $name.' 2' : substr($name, 0, 252).' 2';
        }

        if (strlen($name) + strlen($suffix) <= 255) {
            return $name.$suffix;
        }

        return substr($name, 0, 255 - strlen($suffix)).$suffix;
    }

    private function appendSuffixToCampaignCode(string $baseCode, string $suffix): string
    {
        $max = 50;
        if (strlen($suffix) >= $max) {
            return substr($suffix, 0, $max);
        }

        $maxBaseLen = $max - strlen($suffix);
        $base = strlen($baseCode) > $maxBaseLen
            ? rtrim(substr($baseCode, 0, $maxBaseLen), '-_')
            : $baseCode;

        return $base.$suffix;
    }

    private function campaignCodeExists(string $code): bool
    {
        return MarketingCampaign::withTrashed()
            ->where('campaign_code', $code)
            ->exists();
    }
}
