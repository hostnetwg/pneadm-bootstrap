<?php

namespace App\Http\Controllers;

use App\Mail\ClosedTrainingCaseSchoolMail;
use App\Models\ClosedTrainingCase;
use App\Models\ClosedTrainingCaseEvent;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\TrainingOffer;
use App\Models\User;
use App\Services\ClosedTrainingCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClosedTrainingCaseController extends Controller
{
    public const LIST_SORT_PREFERENCE_KEY = 'closed_training_cases_list';

    /** @var list<string> */
    private const LIST_SORT_COLUMNS = ['id', 'last_activity'];

    public function __construct(private readonly ClosedTrainingCaseService $cases) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'open');
        $search = trim((string) $request->query('q', ''));
        [$sort, $dir] = $this->resolveListSort($request);

        $query = ClosedTrainingCase::query()
            ->with(['assignee', 'instructor', 'course.instructor', 'trainingOffer'])
            ->withCount('events')
            ->withMax('events as last_event_at', 'happened_at')
            ->when($status === 'open', fn ($query) => $query->whereNotIn('status', ClosedTrainingCase::terminalStatuses()))
            ->when(array_key_exists($status, ClosedTrainingCase::statusOptions()), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('topic', 'like', "%{$search}%")
                        ->orWhere('institution', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%");

                    if (preg_match('/^\[?\s*SZ-(\d+)\s*\]?$/iu', $search, $matches)) {
                        $inner->orWhere('id', (int) $matches[1]);
                    } elseif (ctype_digit($search)) {
                        $inner->orWhere('id', (int) $search);
                    }
                });
            });

        if ($sort === 'id') {
            $query->orderBy('closed_training_cases.id', $dir);
        } elseif ($sort === 'last_activity') {
            $query->orderByRaw(
                'COALESCE(last_event_at, closed_training_cases.updated_at) '.$dir
            )->orderBy('closed_training_cases.id', $dir);
        } else {
            $query->orderByRaw(
                'CASE WHEN status IN ('.implode(',', array_fill(0, count(ClosedTrainingCase::terminalStatuses()), '?')).') THEN 1 ELSE 0 END',
                ClosedTrainingCase::terminalStatuses()
            )
                ->orderByRaw('next_action_at IS NULL')
                ->orderBy('next_action_at')
                ->orderByDesc('id');
        }

        $cases = $query->paginate(25)->withQueryString();

        return view('closed-training-cases.index', [
            'cases' => $cases,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
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
        $request->merge([
            'institution_www' => $this->normalizeWww($request->input('institution_www')),
        ]);

        $data = $request->validate([
            'topic' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'institution' => 'nullable|string|max:255',
            'pedagogical_council_size' => 'nullable|integer|min:1|max:9999',
            'institution_www' => 'nullable|url|max:2048',
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
            'institution_www.url' => 'Podaj poprawny adres strony WWW.',
            'institution_type.required' => 'Wybierz rodzaj placówki.',
            'institution_type.in' => 'Wybierz rodzaj placówki.',
            'institution_type_other.required' => 'Podaj rodzaj placówki.',
            'pedagogical_council_size.integer' => 'Podaj liczbę osób w radzie pedagogicznej.',
            'pedagogical_council_size.min' => 'Liczba osób w radzie musi być co najmniej 1.',
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
            'pedagogical_council_size' => isset($data['pedagogical_council_size']) ? (int) $data['pedagogical_council_size'] : null,
            'institution_www' => $data['institution_www'] ?? null,
            'institution_type' => $data['institution_type'],
            'institution_type_other' => $data['institution_type_other'] ?? null,
            'message' => $data['message'],
            'submitted_payload' => ClosedTrainingCase::submittedSnapshot([
                'contact_name' => $data['contact_name'],
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'institution' => $data['institution'] ?? null,
                'institution_www' => $data['institution_www'] ?? null,
                'institution_type' => $data['institution_type'],
                'institution_type_other' => $data['institution_type_other'] ?? null,
                'preferred_format' => $data['preferred_format'] ?? null,
                'topic' => $data['topic'],
                'message' => $data['message'],
            ]),
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
        $closedTrainingCase->load([
            'assignee',
            'instructor',
            'trainingOffer',
            'events.user',
            'course.instructor',
        ]);

        $this->cases->syncFeeConfirmationWithLinkedCourse($closedTrainingCase);
        $closedTrainingCase->refresh();
        $closedTrainingCase->load([
            'assignee',
            'instructor',
            'trainingOffer',
            'events.user',
            'course.instructor',
        ]);

        return view('closed-training-cases.show', [
            'case' => $closedTrainingCase,
            'users' => $this->activeUsers(),
            'offers' => $this->offersForCase($closedTrainingCase),
            'instructors' => $this->instructorsForSelect($closedTrainingCase),
            'negotiationLocked' => $closedTrainingCase->isLinkedToCourse(),
        ]);
    }

    public function bindCourse(Request $request, ClosedTrainingCase $closedTrainingCase): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'confirm_mismatch' => ['nullable', 'boolean'],
        ], [
            'course_id.required' => 'Podaj ID szkolenia.',
            'course_id.exists' => 'Nie znaleziono szkolenia o podanym ID.',
        ]);

        $course = Course::query()->with('instructor')->findOrFail($data['course_id']);
        $via = $closedTrainingCase->course()->exists() ? 'replace' : 'bind';
        $diffs = $this->cases->courseLinkDiffLines($closedTrainingCase, $course);

        if ($diffs !== [] && ! $request->boolean('confirm_mismatch')) {
            return redirect()
                ->route('closed-training-cases.show', $closedTrainingCase)
                ->withInput()
                ->with('course_bind_mismatch', $diffs)
                ->with('course_bind_id', $course->id);
        }

        $this->cases->linkCourse($closedTrainingCase, $course, $request->user(), $via);

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', $via === 'replace'
                ? 'Zmieniono powiązanie ze szkoleniem. Aktualny termin i trener pochodzą ze szkolenia.'
                : 'Powiązano sprawę ze szkoleniem. Aktualny termin i trener pochodzą ze szkolenia.');
    }

    public function unbindCourse(Request $request, ClosedTrainingCase $closedTrainingCase): RedirectResponse
    {
        $course = $this->cases->unlinkCourse($closedTrainingCase, $request->user());

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', $course
                ? 'Odłączono szkolenie od sprawy. Sprawdź ponownie trenera, termin i wynagrodzenie.'
                : 'Sprawa nie miała powiązanego szkolenia.');
    }

    public function syncNegotiationFromCourse(Request $request, ClosedTrainingCase $closedTrainingCase): RedirectResponse
    {
        $this->cases->syncNegotiationFromLinkedCourse($closedTrainingCase, $request->user());

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', 'Zsynchronizowano trenera i termin ze szkolenia na sprawę.');
    }

    public function updateNegotiation(Request $request, ClosedTrainingCase $closedTrainingCase): RedirectResponse
    {
        if ($request->input('client_price') === '') {
            $request->merge(['client_price' => null]);
        }
        if ($request->input('instructor_fee') === '') {
            $request->merge(['instructor_fee' => null]);
        }
        if ($request->input('instructor_id') === '') {
            $request->merge(['instructor_id' => null]);
        }

        $data = $request->validate([
            'instructor_id' => ['nullable', 'integer', Rule::exists('instructors', 'id')],
            'client_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'client_price_confirmed' => ['nullable', 'boolean'],
            'instructor_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'instructor_fee_basis' => ['required', Rule::in(array_keys(ClosedTrainingCase::instructorFeeBasisOptions()))],
            'instructor_fee_confirmed' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'string', 'max:32'],
            'end_date' => ['nullable', 'string', 'max:32'],
            'schedule_time_known' => ['nullable', 'boolean'],
        ], [
            'instructor_id.exists' => 'Wybierz instruktora z listy.',
            'client_price.numeric' => 'Cena dla szkoły musi być liczbą.',
            'instructor_fee.numeric' => 'Wynagrodzenie musi być liczbą.',
            'instructor_fee_basis.required' => 'Wybierz brutto albo netto.',
            'instructor_fee_basis.in' => 'Wybierz brutto albo netto.',
        ]);

        $timeKnown = $request->boolean('schedule_time_known');
        $start = $this->parseNegotiationDate($data['start_date'] ?? null, $timeKnown, 'start_date');
        $end = $this->parseNegotiationDate($data['end_date'] ?? null, $timeKnown, 'end_date');

        if ($end && ! $start) {
            throw ValidationException::withMessages([
                'end_date' => 'Koniec szkolenia wymaga podania początku.',
            ]);
        }
        if ($start && $end) {
            $invalid = $timeKnown
                ? $end->lessThanOrEqualTo($start)
                : $end->toDateString() < $start->toDateString();
            if ($invalid) {
                throw ValidationException::withMessages([
                    'end_date' => $timeKnown
                        ? 'Koniec musi być późniejszy niż początek.'
                        : 'Data końca nie może być wcześniejsza niż początek.',
                ]);
            }
        }
        if (! $start) {
            $timeKnown = false;
        }

        $this->cases->updateNegotiation($closedTrainingCase, [
            'instructor_id' => isset($data['instructor_id']) ? (int) $data['instructor_id'] : null,
            'client_price' => $data['client_price'] ?? null,
            'client_price_confirmed' => $request->boolean('client_price_confirmed'),
            'instructor_fee' => $data['instructor_fee'] ?? null,
            'instructor_fee_basis' => $data['instructor_fee_basis'],
            'instructor_fee_confirmed' => $request->boolean('instructor_fee_confirmed'),
            'start_date' => $start,
            'end_date' => $end,
            'schedule_time_known' => $timeKnown,
        ], $request->user());

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', 'Zapisano ustalenia negocjacyjne.');
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

    public function updateContact(Request $request, ClosedTrainingCase $closedTrainingCase): RedirectResponse
    {
        if ($request->input('pedagogical_council_size') === '') {
            $request->merge(['pedagogical_council_size' => null]);
        }

        $request->merge([
            'contact_name' => trim((string) $request->input('contact_name')),
            'contact_email' => $this->blankToNull($request->input('contact_email')),
            'contact_phone' => $this->blankToNull($request->input('contact_phone')),
            'institution' => $this->blankToNull($request->input('institution')),
            'institution_www' => $this->normalizeWww($request->input('institution_www')),
        ]);

        if ($request->exists('institution_type')) {
            $request->merge([
                'institution_type' => $this->blankToNull($request->input('institution_type')),
            ]);
        }

        if ($request->exists('preferred_format')) {
            $request->merge([
                'preferred_format' => $this->blankToNull($request->input('preferred_format')),
            ]);
        }

        $data = $request->validate([
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'institution' => 'nullable|string|max:255',
            'pedagogical_council_size' => 'nullable|integer|min:1|max:9999',
            'institution_www' => 'nullable|url|max:2048',
            'institution_type' => ['nullable', Rule::in(array_keys(ClosedTrainingCase::institutionTypeOptions()))],
            'institution_type_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(
                fn () => $request->input('institution_type') === ClosedTrainingCase::INSTITUTION_OTHER
            )],
            'preferred_format' => ['nullable', Rule::in(array_keys(ClosedTrainingCase::formatOptions()))],
            'training_offer_id' => ['nullable', 'integer', Rule::exists('training_offers', 'id')],
        ], [
            'contact_name.required' => 'Podaj osobę kontaktową.',
            'contact_email.email' => 'Podaj poprawny adres e-mail.',
            'institution_www.url' => 'Podaj poprawny adres strony WWW.',
            'institution_type.in' => 'Wybierz rodzaj placówki.',
            'institution_type_other.required' => 'Podaj rodzaj placówki.',
            'pedagogical_council_size.integer' => 'Podaj liczbę osób w radzie pedagogicznej.',
            'pedagogical_council_size.min' => 'Liczba osób w radzie musi być co najmniej 1.',
            'preferred_format.in' => 'Wybierz formę szkolenia.',
            'training_offer_id.exists' => 'Wybierz ofertę z katalogu.',
        ]);

        if ($request->exists('institution_type')) {
            $typeData = $this->normalizeInstitutionType([
                'institution_type' => $data['institution_type'] ?? null,
                'institution_type_other' => $data['institution_type_other'] ?? null,
            ]);
        } else {
            $typeData = [
                'institution_type' => $closedTrainingCase->institution_type,
                'institution_type_other' => $closedTrainingCase->institution_type_other,
            ];
        }

        $offerData = $this->inquiryOfferAttributes($request, $closedTrainingCase);

        $this->cases->updateContact($closedTrainingCase, [
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'institution' => $data['institution'] ?? null,
            'pedagogical_council_size' => isset($data['pedagogical_council_size']) ? (int) $data['pedagogical_council_size'] : null,
            'institution_www' => $data['institution_www'] ?? null,
            'institution_type' => $typeData['institution_type'],
            'institution_type_other' => $typeData['institution_type_other'],
            'preferred_format' => $request->exists('preferred_format')
                ? ($data['preferred_format'] ?? null)
                : $closedTrainingCase->preferred_format,
            'training_offer_id' => $offerData['training_offer_id'],
            'offer_url' => $offerData['offer_url'],
        ], $request->user());

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', 'Zapisano dane zapytania.');
    }

    public function storeNote(Request $request, ClosedTrainingCase $closedTrainingCase): RedirectResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:3000',
            'note_channel' => ['nullable', 'string', Rule::in(array_keys(ClosedTrainingCaseEvent::noteChannelOptions()))],
            'note_channel_other' => 'nullable|string|max:100',
            'note_party' => ['nullable', 'string', Rule::in(array_keys(ClosedTrainingCaseEvent::notePartyOptions()))],
        ], [
            'body.required' => 'Wpisz treść notatki.',
        ]);

        $this->cases->addNote($closedTrainingCase, $request->user(), [
            'body' => $data['body'],
            'note_channel' => $data['note_channel'] ?? null,
            'note_channel_other' => $data['note_channel_other'] ?? null,
            'note_party' => $data['note_party'] ?? null,
        ]);

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', 'Notatka została dodana.');
    }

    public function updateEvent(
        Request $request,
        ClosedTrainingCase $closedTrainingCase,
        ClosedTrainingCaseEvent $event,
    ): RedirectResponse {
        $this->ensureEventBelongsToCase($closedTrainingCase, $event);

        $max = $event->event_type === ClosedTrainingCaseEvent::TYPE_EMAIL_SENT ? 20000 : 3000;

        $rules = [
            'body' => "nullable|string|max:{$max}",
        ];

        if ($event->event_type === ClosedTrainingCaseEvent::TYPE_NOTE) {
            $rules['note_channel'] = ['nullable', 'string', Rule::in(array_keys(ClosedTrainingCaseEvent::noteChannelOptions()))];
            $rules['note_channel_other'] = 'nullable|string|max:100';
            $rules['note_party'] = ['nullable', 'string', Rule::in(array_keys(ClosedTrainingCaseEvent::notePartyOptions()))];
        }

        $data = $request->validate($rules);

        $this->cases->updateEvent($event, [
            'body' => trim((string) ($data['body'] ?? '')),
            'note_channel' => $data['note_channel'] ?? null,
            'note_channel_other' => $data['note_channel_other'] ?? null,
            'note_party' => $data['note_party'] ?? null,
        ]);

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', 'Wpis historii został zapisany.');
    }

    public function destroyEvent(
        ClosedTrainingCase $closedTrainingCase,
        ClosedTrainingCaseEvent $event,
    ): RedirectResponse {
        $this->ensureEventBelongsToCase($closedTrainingCase, $event);

        $this->cases->deleteEvent($event);

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', 'Wpis historii został usunięty.');
    }

    public function sendEmail(Request $request, ClosedTrainingCase $closedTrainingCase): RedirectResponse
    {
        if (! $closedTrainingCase->hasContactEmail()) {
            return redirect()
                ->route('closed-training-cases.show', $closedTrainingCase)
                ->with('error', 'Sprawa nie ma poprawnego adresu e-mail. Uzupełnij go w danych zapytania.');
        }

        $data = $request->validate([
            'email_subject' => 'required|string|max:255',
            'email_body' => 'required|string|max:20000',
        ], [
            'email_subject.required' => 'Podaj temat wiadomości.',
            'email_body.required' => 'Wpisz treść wiadomości.',
        ]);

        $toEmail = trim((string) $closedTrainingCase->contact_email);
        $subject = $closedTrainingCase->withCaseReferenceInSubject($data['email_subject']);
        $body = ClosedTrainingCase::sanitizeSchoolEmailHtml($data['email_body']);

        if ($body === '' || trim(strip_tags($body)) === '') {
            throw ValidationException::withMessages([
                'email_body' => 'Wpisz treść wiadomości.',
            ]);
        }

        try {
            Mail::to($toEmail)->send(new ClosedTrainingCaseSchoolMail($body, $subject));
        } catch (\Throwable $exception) {
            Log::warning('Closed training case school email failed', [
                'case_id' => $closedTrainingCase->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('closed-training-cases.show', $closedTrainingCase)
                ->withInput()
                ->with('error', 'Nie udało się wysłać e-maila. Sprawa i historia nie zostały zmienione.');
        }

        $this->cases->recordSchoolEmailSent(
            $closedTrainingCase,
            $request->user(),
            $toEmail,
            $subject,
            $body,
        );

        return redirect()
            ->route('closed-training-cases.show', $closedTrainingCase)
            ->with('success', 'E-mail został wysłany do '.$toEmail.'.');
    }

    /**
     * @return array{training_offer_id: ?int, offer_url: ?string}
     */
    private function inquiryOfferAttributes(Request $request, ClosedTrainingCase $case): array
    {
        if (! $request->exists('training_offer_id')) {
            return [
                'training_offer_id' => $case->training_offer_id,
                'offer_url' => $case->offer_url,
            ];
        }

        $offerId = $this->blankToNull($request->input('training_offer_id'));
        if ($offerId === null) {
            return [
                'training_offer_id' => null,
                'offer_url' => null,
            ];
        }

        if ((int) $offerId === (int) $case->training_offer_id) {
            return [
                'training_offer_id' => $case->training_offer_id,
                'offer_url' => $case->offer_url,
            ];
        }

        $offer = TrainingOffer::query()->findOrFail((int) $offerId);
        $base = rtrim((string) config('services.pnedu_frontend_url', 'https://pnedu.pl'), '/');

        return [
            'training_offer_id' => $offer->id,
            'offer_url' => $base.'/szkolenia-rad-pedagogicznych/'.$offer->slug,
        ];
    }

    private function offersForCase(ClosedTrainingCase $case)
    {
        $offers = TrainingOffer::query()->orderBy('title')->get(['id', 'title']);

        if ($case->training_offer_id && ! $offers->contains('id', $case->training_offer_id)) {
            $current = TrainingOffer::withTrashed()->find($case->training_offer_id, ['id', 'title']);
            if ($current) {
                $offers->prepend($current);
            }
        }

        return $offers;
    }

    private function activeUsers()
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function instructorsForSelect(ClosedTrainingCase $case)
    {
        $instructors = Instructor::query()
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($case->instructor_id && ! $instructors->contains('id', $case->instructor_id)) {
            $current = Instructor::withTrashed()->find($case->instructor_id);
            if ($current) {
                $instructors->prepend($current);
            }
        }

        return $instructors;
    }

    private function parseNegotiationDate(?string $value, bool $timeKnown, string $field): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);

        try {
            if ($timeKnown) {
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
                    $value .= ':00';
                }

                return Carbon::createFromFormat('Y-m-d H:i:s', $value, 'Europe/Warsaw');
            }

            $date = substr($value, 0, 10);

            return Carbon::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', 'Europe/Warsaw');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => $timeKnown
                    ? 'Podaj poprawną datę i godzinę.'
                    : 'Podaj poprawną datę.',
            ]);
        }
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

    private function ensureEventBelongsToCase(ClosedTrainingCase $case, ClosedTrainingCaseEvent $event): void
    {
        if ((int) $event->closed_training_case_id !== (int) $case->id) {
            abort(404);
        }
    }

    /**
     * @return array{0: ?string, 1: string} [sort column or null for default, direction]
     */
    private function resolveListSort(Request $request): array
    {
        $user = $request->user();
        $preferences = is_array($user?->preferences) ? $user->preferences : [];
        $saved = is_array($preferences[self::LIST_SORT_PREFERENCE_KEY] ?? null)
            ? $preferences[self::LIST_SORT_PREFERENCE_KEY]
            : [];

        $requestedSort = $request->query('sort');
        $hasExplicitSort = is_string($requestedSort) && in_array($requestedSort, self::LIST_SORT_COLUMNS, true);

        if ($hasExplicitSort) {
            $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            if ($user) {
                $preferences[self::LIST_SORT_PREFERENCE_KEY] = [
                    'sort' => $requestedSort,
                    'dir' => $dir,
                ];
                $user->preferences = $preferences;
                $user->save();
            }

            return [$requestedSort, $dir];
        }

        $savedSort = $saved['sort'] ?? null;
        if (is_string($savedSort) && in_array($savedSort, self::LIST_SORT_COLUMNS, true)) {
            $dir = ($saved['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

            return [$savedSort, $dir];
        }

        return [null, 'desc'];
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeWww(mixed $value): ?string
    {
        $value = $this->blankToNull(is_string($value) ? $value : null);
        if ($value === null) {
            return null;
        }

        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }

        return $value;
    }
}
