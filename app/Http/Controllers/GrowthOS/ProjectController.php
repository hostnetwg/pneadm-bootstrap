<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use App\Models\GrowthOS\GrowthArtifactImage;
use App\Models\GrowthOS\GrowthTask;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\GrowthAiService;
use App\Services\GrowthOS\AI\GrowthImageService;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Services\GrowthOS\GrowthOperationalTasks;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public const MATERIAL_AI_PRECONDITION_MESSAGE = 'Najpierw zatwierdź kierunek i koncepcję webinaru.';

    public const MATERIAL_SKIPPED_MESSAGE = 'Ten materiał jest wyłączony (status „Nie dotyczy”). Zmień status, żeby z nim pracować.';

    public const MATERIAL_AI_STALE_MESSAGE = 'Kierunek, koncepcja lub materiał zmieniły się od czasu wygenerowania szkicu. Wygeneruj nową propozycję.';

    public const IMAGE_NOT_PERSISTED_MESSAGE = 'Projekt nie jest jeszcze zapisany w bazie, więc obrazu nie da się przechować.';

    public function index(): View
    {
        return view('growth-os.projects.index', [
            'project' => DemoTikWebinarProject::project(),
            'nextAction' => DemoTikWebinarProject::nextAction(DemoTikWebinarProject::project()),
            'projectStatusLabels' => DemoTikWebinarProject::projectStatusLabels(),
        ]);
    }

    public function create(): View
    {
        return view('growth-os.projects.create', [
            'goals' => DemoTikWebinarProject::goals(),
            'ideas' => DemoTikWebinarProject::ideas(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $goalValues = collect(DemoTikWebinarProject::goals())->pluck('value')->all();

        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'live_date' => ['required', 'date'],
            'live_time' => ['required', 'date_format:H:i'],
            'host' => ['required', 'string', 'max:120'],
            'goal' => ['required', Rule::in($goalValues)],
            'topic' => ['nullable', 'string', 'max:180'],
        ]);

        $project = DemoTikWebinarProject::createProject($data);

        return redirect()
            ->route('growth.projects.show', $project['id'])
            ->with('success', 'Utworzono projekt webinaru. Kampania i prowadzący są zapisane.');
    }

    public function updateHost(Request $request, string $project): RedirectResponse
    {
        $host = $request->validate([
            'host' => ['required', 'string', 'max:120'],
        ])['host'];

        DemoTikWebinarProject::updateHost($project, $host);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zapisano prowadzącego.')
            ->withFragment('project-host');
    }

    public function show(string $project): View
    {
        $item = DemoTikWebinarProject::requireProject($project);

        return view('growth-os.projects.show', [
            'project' => $item,
            'health' => DemoTikWebinarProject::projectHealth($item),
            'nextAction' => DemoTikWebinarProject::nextAction($item),
            'stages' => DemoTikWebinarProject::stages(),
            'timeline' => DemoTikWebinarProject::timeline(),
            'projectStatusLabels' => DemoTikWebinarProject::projectStatusLabels(),
            'materialStatusLabels' => DemoTikWebinarProject::materialStatusLabels(),
            'conceptAiIntents' => DemoTikWebinarProject::conceptAiIntents(),
            'growthAiEnabled' => config('growth_ai.enabled') === true,
            'growthAiProvider' => (string) config('growth_ai.provider'),
            'growthAiModel' => (string) config('growth_ai.model'),
            'conceptDecisions' => DemoTikWebinarProject::conceptDecisions($item),
            'operationalTasks' => DemoTikWebinarProject::operationalTasks($item),
        ]);
    }

    public function completeStep(string $project, string $step): RedirectResponse
    {
        DemoTikWebinarProject::completeStep($project, $step);

        $message = $step === 'direction'
            ? 'Kierunek zatwierdzony. Decyzja została zapisana.'
            : 'Koncepcja gotowa. Decyzja została zapisana.';

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', $message)
            ->withFragment($step === 'direction' ? 'direction' : 'concept');
    }

    public function reopenStep(string $project, string $step): RedirectResponse
    {
        DemoTikWebinarProject::reopenStep($project, $step);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', $step === 'concept'
                ? 'Cofnięto zatwierdzenie koncepcji. Decyzja została zapisana.'
                : 'Cofnięto zatwierdzenie kierunku. Decyzja została zapisana.')
            ->withFragment($step);
    }

    public function updateDirection(Request $request, string $project): RedirectResponse
    {
        $data = $request->validate([
            'why_now' => ['required', 'string', 'max:1000'],
            'audience' => ['required', 'string', 'max:500'],
            'problem' => ['required', 'string', 'max:1000'],
            'takeaway' => ['required', 'string', 'max:1000'],
            'sell_later' => ['required', Rule::in(['nie', 'być może', 'tak'])],
        ]);

        DemoTikWebinarProject::updateDirection($project, $data);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zapisano kierunek.')
            ->withFragment('direction');
    }

    public function updateConcept(Request $request, string $project): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['required', 'string', 'max:220'],
            'promise' => ['required', 'string', 'max:500'],
            'points' => ['required', 'string', 'max:2000'],
            'plan' => ['required', 'string', 'max:1000'],
            'cta' => ['required', 'string', 'max:400'],
            'lead_magnet' => ['required', 'string', 'max:300'],
            'next_product' => ['required', Rule::in(['nie', 'być może', 'tak'])],
        ]);

        DemoTikWebinarProject::updateConcept($project, $data);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zapisano koncepcję. Pozostałe etapy zostają w tej sesji.')
            ->withFragment('concept');
    }

    public function requestConceptAi(
        Request $request,
        string $project,
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
            'intent' => ['required', Rule::in(collect(DemoTikWebinarProject::conceptAiIntents())->pluck('value')->all())],
            'instruction' => ['nullable', 'string', 'max:'.config('growth_ai.limits.max_instruction_chars')],
        ]);
        $intent = (string) $data['intent'];
        $intentLabel = (string) (
            collect(DemoTikWebinarProject::conceptAiIntents())->firstWhere('value', $intent)['label']
            ?? $intent
        );
        $wantsJson = $request->expectsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if (config('growth_ai.enabled') !== true) {
            $updatedProject = DemoTikWebinarProject::requestConceptAiProposal($project, $intent);

            if ($wantsJson) {
                return response()->json([
                    'ok' => true,
                    'message' => 'AI przygotowało propozycję (symulacja). Obecna koncepcja nie została nadpisana.',
                    'proposal_html' => $this->renderConceptAiProposal($updatedProject, $project),
                ]);
            }

            return redirect()
                ->route('growth.projects.show', $project)
                ->with('success', 'AI przygotowało propozycję (symulacja). Obecna koncepcja nie została nadpisana.')
                ->with('growth_ai_completed', true)
                ->withFragment('concept');
        }

        $growthAiService = app(GrowthAiService::class);
        $item = DemoTikWebinarProject::requireProject($project);
        $instruction = $intentLabel;
        if (filled($data['instruction'] ?? null)) {
            $instruction .= '. Dodatkowa instrukcja użytkownika: '.trim((string) $data['instruction']);
        }

        try {
            $result = $growthAiService->reviseConcept(
                user: $request->user(),
                concept: is_array($item['concept'] ?? null) ? $item['concept'] : [],
                audience: (string) data_get($item, 'direction.audience', ''),
                instruction: $instruction,
            );

            $updatedProject = DemoTikWebinarProject::storeConceptAiProposal(
                projectId: $project,
                intent: $intent,
                intentLabel: $intentLabel,
                result: $result,
            );
        } catch (GrowthAiException $exception) {
            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => $exception->userMessage,
                ], 422);
            }

            return redirect()
                ->route('growth.projects.show', $project)
                ->with('error', $exception->userMessage)
                ->withFragment('concept');
        }

        if ($wantsJson) {
            return response()->json([
                'ok' => true,
                'message' => 'AI przygotowało propozycję. Obecna koncepcja nie została nadpisana.',
                'proposal_html' => $this->renderConceptAiProposal($updatedProject, $project),
            ]);
        }

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'AI przygotowało propozycję. Obecna koncepcja nie została nadpisana.')
            ->with('growth_ai_completed', true)
            ->withFragment('concept');
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private function renderConceptAiProposal(array $project, string $projectId): string
    {
        return view('growth-os.projects.partials.concept-ai-proposal', [
            'proposal' => $project['concept_ai_proposal'] ?? null,
            'projectId' => $projectId,
        ])->render();
    }

    public function applyConceptAi(string $project): RedirectResponse
    {
        DemoTikWebinarProject::applyConceptAiProposal($project);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zastosowano propozycję AI i zapisano koncepcję oraz decyzję. Sprawdź ją i zatwierdź, gdy będzie gotowa.')
            ->withFragment('concept');
    }

    public function rejectConceptAi(string $project): RedirectResponse
    {
        DemoTikWebinarProject::rejectConceptAiProposal($project);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Odrzucono propozycję AI. Decyzja została zapisana. Została poprzednia koncepcja.')
            ->withFragment('concept');
    }

    public function updateOperationalTask(Request $request, string $project, string $taskKey): RedirectResponse
    {
        abort_unless(in_array($taskKey, GrowthOperationalTasks::keys(), true), 404);

        $done = $request->validate([
            'done' => ['required', 'boolean'],
        ])['done'];

        $item = DemoTikWebinarProject::requireProject($project);
        $campaignId = $item['growth_campaign_id'] ?? null;
        abort_unless(is_numeric($campaignId), 404);

        $task = GrowthTask::query()
            ->where('growth_campaign_id', (int) $campaignId)
            ->where('key', $taskKey)
            ->firstOrFail();

        $task->status = $done ? GrowthTask::STATUS_DONE : GrowthTask::STATUS_TODO;
        $task->completed_at = $done ? now() : null;
        $task->save();

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', $done ? 'Zadanie oznaczono jako gotowe.' : 'Zadanie wróciło do zrobienia.')
            ->withFragment('timeline');
    }

    public function material(string $project, string $material): View
    {
        $item = DemoTikWebinarProject::material($project, $material);
        $projectItem = DemoTikWebinarProject::requireProject($project);

        $aiDraftSupported = MaterialDraftTask::supports($material);
        $isFacebookPost = $material === MaterialDraftTask::FACEBOOK_MATERIAL_KEY;
        $skipped = DemoTikWebinarProject::isMaterialSkipped($item);

        return view('growth-os.projects.material', [
            'project' => $projectItem,
            'material' => $item,
            'mailFields' => $material === MaterialDraftTask::MAIL_MATERIAL_KEY
                ? MaterialDraftTask::parseMainMail((string) ($item['draft'] ?? ''))
                : null,
            'materialStatusLabels' => DemoTikWebinarProject::materialStatusLabels(),
            'aiDraftSupported' => $aiDraftSupported,
            'aiDraftIsFacebookPost' => $isFacebookPost,
            'aiDraftIsGraphic' => $material === MaterialDraftTask::GRAPHIC_MATERIAL_KEY,
            'aiDraftIsMail' => $material === MaterialDraftTask::MAIL_MATERIAL_KEY,
            'aiDraftLiveLabel' => DemoTikWebinarProject::liveLabel($projectItem),
            'aiDraftUsesYoutubeSource' => MaterialDraftTask::usesYoutubeSource($material),
            'aiDraftUsesYoutubeDescription' => MaterialDraftTask::usesYoutubeSource($material)
                && DemoTikWebinarProject::approvedYoutubeDescription($projectItem) !== '',
            'materialSkipped' => $skipped,
            'aiDraftAllowed' => $aiDraftSupported && ! $skipped && DemoTikWebinarProject::canDraftMaterialWithAi($projectItem),
            'aiDraftProposal' => $aiDraftSupported ? DemoTikWebinarProject::materialAiProposal($projectItem, $material) : null,
            'aiRealEnabled' => config('growth_ai.enabled') === true,
            'aiModel' => (string) config('growth_ai.model'),
            'materialVersions' => DemoTikWebinarProject::materialVersions($projectItem, $material),
            'imageGeneratorEnabled' => $material === GraphicImageTask::MATERIAL_KEY,
            'imageGeneratorReady' => is_numeric($projectItem['growth_campaign_id'] ?? null),
            'imageDescription' => $material === GraphicImageTask::MATERIAL_KEY
                ? DemoTikWebinarProject::graphicImageDescription($projectItem)
                : '',
            'imageHeadline' => $material === GraphicImageTask::MATERIAL_KEY
                ? DemoTikWebinarProject::graphicHeadline($projectItem)
                : '',
            'imageModel' => (string) config('growth_ai.images.model'),
            'imageQuality' => (string) config('growth_ai.images.quality'),
            'imageDailyUsed' => $material === GraphicImageTask::MATERIAL_KEY
                ? app(GrowthImageService::class)->dailyUsage(auth()->user())
                : 0,
            'materialImages' => $material === GraphicImageTask::MATERIAL_KEY
                ? DemoTikWebinarProject::materialImages($projectItem, $material)
                : new EloquentCollection,
        ]);
    }

    public function generateMaterialImage(Request $request, string $project, string $material): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        $item = DemoTikWebinarProject::requireProject($project);
        $data = $request->validate([
            'format' => ['required', Rule::in(array_keys(GraphicImageTask::FORMATS))],
            'image_prompt' => ['required', 'string', 'max:'.GraphicImageTask::MAX_PROMPT_CHARS],
            'include_headline' => ['nullable', 'boolean'],
        ]);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);

        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->withInput()->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        if (! DemoTikWebinarProject::canDraftMaterialWithAi($item)) {
            return $back->withInput()->with('error', self::MATERIAL_AI_PRECONDITION_MESSAGE);
        }

        $artifact = DemoTikWebinarProject::materialImageArtifact($item, $material);
        if ($artifact === null) {
            return $back->withInput()->with('error', self::IMAGE_NOT_PERSISTED_MESSAGE);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit((int) config('growth_ai.images.timeout_seconds') + 60);
        }

        try {
            $image = app(GrowthImageService::class)->generate(
                user: $request->user(),
                artifact: $artifact,
                format: (string) $data['format'],
                description: (string) $data['image_prompt'],
                includeHeadline: $request->boolean('include_headline'),
                headline: DemoTikWebinarProject::graphicHeadline($item),
                liveLabel: DemoTikWebinarProject::liveLabel($item),
            );
        } catch (GrowthAiException $exception) {
            return $back->withInput()->with('error', $exception->userMessage);
        }

        return $back->with('success', $image->source === GrowthArtifactImage::SOURCE_SIMULATION
            ? 'Przygotowano obraz zastępczy (symulacja lokalna, bez wywołania OpenAI).'
            : 'Wygenerowano obraz. Sprawdź go w galerii. Nic nie opublikowano.');
    }

    public function adaptMaterialImageToSquare(string $project, string $material, int $image): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        $item = DemoTikWebinarProject::requireProject($project);
        $source = DemoTikWebinarProject::requireMaterialImage($item, $material, $image);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);

        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        if (! DemoTikWebinarProject::canDraftMaterialWithAi($item)) {
            return $back->with('error', self::MATERIAL_AI_PRECONDITION_MESSAGE);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit((int) config('growth_ai.images.timeout_seconds') + 60);
        }

        try {
            $square = app(GrowthImageService::class)->adaptToSquare(auth()->user(), $source);
        } catch (GrowthAiException $exception) {
            return $back->with('error', $exception->userMessage);
        }

        return $back->with('success', $square->source === GrowthArtifactImage::SOURCE_SIMULATION
            ? 'Przygotowano kwadratowy obraz zastępczy (symulacja lokalna, bez wywołania OpenAI).'
            : 'Utworzono wersję kwadratową z obrazu poziomego. Sprawdź, czy wszystkie elementy się zmieściły.');
    }

    public function resetMaterialImageLimit(Request $request, string $project, string $material): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        DemoTikWebinarProject::requireProject($project);
        app(GrowthImageService::class)->resetDailyLimit($request->user());

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Zresetowano dzienny limit obrazów AI. Możesz znów generować obrazy.');
    }

    public function showMaterialImage(Request $request, string $project, string $material, int $image): StreamedResponse
    {
        $row = DemoTikWebinarProject::requireMaterialImage(DemoTikWebinarProject::requireProject($project), $material, $image);
        $disk = Storage::disk($row->disk);
        abort_unless($disk->exists($row->path), 404);
        $headers = ['Content-Type' => $row->mime, 'Cache-Control' => 'private, max-age=3600'];

        return $request->boolean('download')
            ? $disk->download($row->path, $row->downloadName(), $headers)
            : $disk->response($row->path, $row->downloadName(), $headers);
    }

    public function selectMaterialImage(string $project, string $material, int $image): RedirectResponse
    {
        $row = DemoTikWebinarProject::requireMaterialImage(DemoTikWebinarProject::requireProject($project), $material, $image);
        app(GrowthImageService::class)->select($row);

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Wybrano obraz jako grafikę główną. Nic nie opublikowano.');
    }

    public function deleteMaterialImage(string $project, string $material, int $image): RedirectResponse
    {
        $row = DemoTikWebinarProject::requireMaterialImage(DemoTikWebinarProject::requireProject($project), $material, $image);
        app(GrowthImageService::class)->delete($row);

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Usunięto obraz z galerii.');
    }

    public function restoreMaterialVersion(string $project, string $material, int $version): RedirectResponse
    {
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);
        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        $outcome = DemoTikWebinarProject::restoreMaterialVersion($project, $material, $version);

        if ($outcome['unchanged']) {
            return $back->with('success', 'Ta wersja jest taka sama jak obecny szkic. Nic nie zmieniono.');
        }

        return $back->with('success', 'Przywrócono wersję '.$version.' jako nową wersję. Materiał ma status Draft — sprawdź go przed dalszą pracą.');
    }

    public function requestMaterialAi(Request $request, string $project, string $material): RedirectResponse
    {
        abort_unless(MaterialDraftTask::supports($material), 404);
        $item = DemoTikWebinarProject::requireProject($project);
        $data = $request->validate([
            'instruction' => ['nullable', 'string', 'max:'.config('growth_ai.limits.max_instruction_chars')],
            'length' => ['nullable', Rule::in(array_keys(MaterialDraftTask::MAIL_LENGTHS))],
        ]);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);
        $style = [
            'emojis' => $request->boolean('emojis', $material !== MaterialDraftTask::MAIL_MATERIAL_KEY),
            'hashtags' => $request->boolean('hashtags', true),
            'elements' => collect(MaterialDraftTask::GRAPHIC_OPTIONAL_ELEMENTS)
                ->mapWithKeys(fn (string $label, string $key): array => [$key => $request->boolean('elements.'.$key, true)])
                ->all(),
            'length' => MaterialDraftTask::mailLength($data['length'] ?? null),
        ];
        $instruction = trim((string) ($data['instruction'] ?? ''));

        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->withInput()->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        if (! DemoTikWebinarProject::canDraftMaterialWithAi($item)) {
            return $back->withInput()->with('error', self::MATERIAL_AI_PRECONDITION_MESSAGE);
        }

        if (config('growth_ai.enabled') !== true) {
            DemoTikWebinarProject::requestMaterialAiProposal($project, $material, $style, $instruction);

            return $back->with('success', 'AI przygotowało szkic (symulacja lokalna). Obecny szkic nie został nadpisany.');
        }

        try {
            $result = app(GrowthAiService::class)->draftMaterial(
                $request->user(),
                $material,
                DemoTikWebinarProject::materialAiContext($item, $material, $style, $instruction),
            );
        } catch (GrowthAiException $exception) {
            $message = $exception->userMessage === GrowthAiException::INVALID_RESPONSE_MESSAGE
                ? 'Nie udało się przygotować poprawnej propozycji AI. Obecny szkic nie został zmieniony.'
                : $exception->userMessage;

            return $back->withInput()->with('error', $message);
        }

        DemoTikWebinarProject::storeMaterialAiProposal($project, $material, $result, $instruction);

        return $back->with('success', 'AI przygotowało szkic. Obecny szkic nie został nadpisany.');
    }

    public function applyMaterialAi(string $project, string $material): RedirectResponse
    {
        abort_unless(MaterialDraftTask::supports($material), 404);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);
        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        $outcome = DemoTikWebinarProject::applyMaterialAiProposal($project, $material);

        if (! $outcome['ok']) {
            return $back->with('error', self::MATERIAL_AI_STALE_MESSAGE);
        }

        return $back->with('success', 'Zastosowano szkic AI. Materiał ma status Draft — sprawdź go przed dalszą pracą. Nic nie opublikowano.');
    }

    public function rejectMaterialAi(string $project, string $material): RedirectResponse
    {
        abort_unless(MaterialDraftTask::supports($material), 404);
        DemoTikWebinarProject::rejectMaterialAiProposal($project, $material);

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Odrzucono szkic AI. Obecny szkic pozostał bez zmian.');
    }

    public function updateMaterialStatus(Request $request, string $project, string $material): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(DemoTikWebinarProject::materialStatusLabels()))],
            'draft' => ['nullable', 'string', 'max:20000'],
            'mail_subject' => ['nullable', 'string', 'max:200'],
            'mail_preheader' => ['nullable', 'string', 'max:200'],
            'mail_body' => ['nullable', 'string', 'max:20000'],
        ]);

        if ($material === MaterialDraftTask::MAIL_MATERIAL_KEY && $request->has('mail_body')) {
            $subject = trim(preg_replace('/\s+/u', ' ', (string) ($data['mail_subject'] ?? '')));
            $data['draft'] = MaterialDraftTask::composeMainMail(
                $subject !== '' ? [$subject] : [],
                preg_replace('/\s+/u', ' ', (string) ($data['mail_preheader'] ?? '')),
                (string) ($data['mail_body'] ?? ''),
            );
        }

        $wasSkipped = DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material));
        $saved = DemoTikWebinarProject::updateMaterialStatus(
            $project,
            $material,
            $data['status'],
            array_key_exists('draft', $data) ? (string) $data['draft'] : null,
        );

        $message = match (true) {
            DemoTikWebinarProject::isMaterialSkipped($saved) => 'Materiał wyłączony (Nie dotyczy). Nie liczy się do następnego kroku ani elementów krytycznych. Szkic został zachowany.',
            $wasSkipped => 'Materiał jest znowu aktywny. Szkic jest taki jak przed wyłączeniem.',
            default => 'Status i szkic materiału zostały zapisane. Nic nie opublikowano.',
        };

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', $message);
    }
}
