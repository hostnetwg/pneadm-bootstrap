<?php

namespace App\Http\Controllers;

use App\Models\ClosedTrainingCase;
use App\Models\User;
use App\Services\ClosedTrainingCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClosedTrainingCaseController extends Controller
{
    public function __construct(private readonly ClosedTrainingCaseService $cases) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'open');
        $search = trim((string) $request->query('q', ''));

        $cases = ClosedTrainingCase::query()
            ->with('assignee')
            ->when($status === 'open', fn ($query) => $query->where('status', '!=', ClosedTrainingCase::STATUS_CLOSED))
            ->when(array_key_exists($status, ClosedTrainingCase::statusOptions()), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('topic', 'like', "%{$search}%")
                        ->orWhere('institution', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [ClosedTrainingCase::STATUS_CLOSED])
            ->orderByRaw('next_action_at IS NULL')
            ->orderBy('next_action_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('closed-training-cases.index', [
            'cases' => $cases,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('closed-training-cases.create', [
            'users' => $this->activeUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'topic' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'institution' => 'nullable|string|max:255',
            'institution_type' => ['required', Rule::in(array_keys(ClosedTrainingCase::institutionTypeOptions()))],
            'institution_type_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(
                fn () => $request->input('institution_type') === ClosedTrainingCase::INSTITUTION_OTHER
            )],
            'preferred_format' => ['nullable', Rule::in(array_keys(ClosedTrainingCase::formatOptions()))],
            'message' => 'required|string|max:3000',
            'assignee_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'next_action' => 'nullable|string|max:1000',
            'next_action_at' => 'nullable|date',
        ], [
            'topic.required' => 'Podaj temat szkolenia.',
            'contact_name.required' => 'Podaj osobę kontaktową.',
            'institution_type.required' => 'Wybierz rodzaj placówki.',
            'institution_type.in' => 'Wybierz rodzaj placówki.',
            'institution_type_other.required' => 'Podaj rodzaj placówki.',
            'message.required' => 'Opisz, czego dotyczy sprawa.',
        ]);

        $data = $this->normalizeInstitutionType($data);

        $case = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_MANUAL,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => $data['topic'],
            'preferred_format' => $data['preferred_format'] ?? null,
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'institution' => $data['institution'] ?? null,
            'institution_type' => $data['institution_type'],
            'institution_type_other' => $data['institution_type_other'] ?? null,
            'message' => $data['message'],
            'assignee_user_id' => $data['assignee_user_id'] ?? null,
            'next_action' => $this->blankToNull($data['next_action'] ?? null),
            'next_action_at' => $data['next_action_at'] ?? null,
        ]);

        $this->cases->recordCreated($case, $request->user(), 'Sprawa wpisana ręcznie w panelu.');

        return redirect()
            ->route('closed-training-cases.show', $case)
            ->with('success', 'Sprawa została zapisana.');
    }

    public function show(ClosedTrainingCase $closedTrainingCase): View
    {
        $closedTrainingCase->load(['assignee', 'trainingOffer', 'events.user']);

        return view('closed-training-cases.show', [
            'case' => $closedTrainingCase,
            'users' => $this->activeUsers(),
        ]);
    }

    public function update(Request $request, ClosedTrainingCase $closedTrainingCase): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ClosedTrainingCase::statusOptions()))],
            'assignee_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'next_action' => 'nullable|string|max:1000',
            'next_action_at' => 'nullable|date',
        ]);

        $this->cases->updateHandling($closedTrainingCase, [
            'status' => $data['status'],
            'assignee_user_id' => $data['assignee_user_id'] ?? null,
            'next_action' => $this->blankToNull($data['next_action'] ?? null),
            'next_action_at' => $data['next_action_at'] ?? null,
        ], $request->user());

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', 'Zapisano obsługę sprawy.');
    }

    public function storeNote(Request $request, ClosedTrainingCase $closedTrainingCase): RedirectResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:3000',
        ], [
            'body.required' => 'Wpisz treść notatki.',
        ]);

        $this->cases->addNote($closedTrainingCase, $request->user(), $data['body']);

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', 'Notatka została dodana.');
    }

    private function activeUsers()
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function normalizeInstitutionType(array $data): array
    {
        if (($data['institution_type'] ?? null) === ClosedTrainingCase::INSTITUTION_OTHER) {
            $detail = trim((string) ($data['institution_type_other'] ?? ''));
            if ($detail === '') {
                throw ValidationException::withMessages([
                    'institution_type_other' => 'Podaj rodzaj placówki.',
                ]);
            }
            $data['institution_type_other'] = $detail;
        } else {
            $data['institution_type_other'] = null;
        }

        return $data;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
