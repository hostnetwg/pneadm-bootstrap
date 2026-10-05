<?php

namespace App\Support\GrowthOS;

use App\Models\Instructor;
use App\Services\GrowthOS\AI\Support\AddressFormPolicy;
use Illuminate\Validation\Rule;

/**
 * Presenter and communication voice of a webinar (DEC-035). Neither depends on the logged-in user:
 * the presenter is an Instructor or a typed-in name, the voice is an Instructor or the neutral PNE voice.
 */
final class GrowthPeople
{
    public const HOST_SOURCE_INSTRUCTOR = 'instructor';

    public const HOST_SOURCE_MANUAL = 'manual';

    public const VOICE_NEUTRAL = 'neutral';

    public const VOICE_PERSONAL = 'personal';

    public const VOICE_NO_PROFILE = 'no_profile';

    public const VOICE_UNAVAILABLE = 'unavailable';

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $instructor = Rule::exists('instructors', 'id')->whereNull('deleted_at');

        return [
            'host_source' => ['nullable', Rule::in([self::HOST_SOURCE_INSTRUCTOR, self::HOST_SOURCE_MANUAL])],
            'host_instructor_id' => ['nullable', 'required_if:host_source,'.self::HOST_SOURCE_INSTRUCTOR, 'integer', $instructor],
            'host' => ['nullable', 'required_unless:host_source,'.self::HOST_SOURCE_INSTRUCTOR, 'string', 'max:120'],
            'voice_instructor_id' => ['nullable', 'integer', $instructor],
            'address_form' => ['nullable', Rule::in(AddressFormPolicy::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'host_instructor_id.required_if' => 'Wybierz prowadzącego z listy.',
            'host.required_unless' => 'Wpisz imię i nazwisko prowadzącego.',
        ];
    }

    /**
     * Validated form data to the stored shape. A presenter from the base gets the current full name as a snapshot.
     *
     * @param  array<string, mixed>  $data
     * @return array{host: string, host_instructor_id: int|null, voice_instructor_id: int|null, address_form: string}
     */
    public static function fromInput(array $data): array
    {
        $hostInstructor = ($data['host_source'] ?? self::HOST_SOURCE_MANUAL) === self::HOST_SOURCE_INSTRUCTOR
            ? Instructor::query()->find((int) ($data['host_instructor_id'] ?? 0))
            : null;
        $voiceId = $data['voice_instructor_id'] ?? null;

        return [
            'host' => $hostInstructor instanceof Instructor
                ? $hostInstructor->full_name
                : trim((string) ($data['host'] ?? '')),
            'host_instructor_id' => $hostInstructor instanceof Instructor ? (int) $hostInstructor->id : null,
            'voice_instructor_id' => is_numeric($voiceId) ? (int) $voiceId : null,
            'address_form' => AddressFormPolicy::normalize($data['address_form'] ?? null),
        ];
    }

    /**
     * Active instructors plus the ones already chosen in this project, so an inactive choice can still be shown.
     *
     * @param  list<int|null>  $selectedIds
     * @return list<array{id: int, name: string, active: bool, has_profile: bool}>
     */
    public static function instructorOptions(array $selectedIds = []): array
    {
        $selectedIds = array_values(array_filter($selectedIds, 'is_int'));

        return Instructor::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $selectedIds))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'is_active', 'ai_voice_profile'])
            ->map(fn (Instructor $instructor): array => [
                'id' => (int) $instructor->id,
                'name' => $instructor->full_name,
                'active' => (bool) $instructor->is_active,
                'has_profile' => trim((string) $instructor->ai_voice_profile) !== '',
            ])
            ->all();
    }

    /**
     * Communication voice for AI. Only the full name and the voice profile ever leave the Instructor record.
     * A deleted or inactive instructor falls back to the neutral PNE voice.
     *
     * @param  array<string, mixed>  $project
     * @return array{status: string, instructor_id: int|null, name: string, profile: string}
     */
    public static function voice(array $project): array
    {
        $id = $project['voice_instructor_id'] ?? null;
        if (! is_int($id)) {
            return ['status' => self::VOICE_NEUTRAL, 'instructor_id' => null, 'name' => '', 'profile' => ''];
        }

        $instructor = Instructor::query()->find($id, ['id', 'first_name', 'last_name', 'is_active', 'ai_voice_profile']);
        if (! $instructor instanceof Instructor || ! $instructor->is_active) {
            return ['status' => self::VOICE_UNAVAILABLE, 'instructor_id' => $id, 'name' => '', 'profile' => ''];
        }

        $profile = trim((string) $instructor->ai_voice_profile);

        return [
            'status' => $profile !== '' ? self::VOICE_PERSONAL : self::VOICE_NO_PROFILE,
            'instructor_id' => $id,
            'name' => $instructor->full_name,
            'profile' => $profile,
        ];
    }
}
