<?php

namespace Tests\Unit;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseAdminSelectSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_prefix_searches_exact_internal_id_only(): void
    {
        $targetId = $this->insertCourse('Docelowy kurs', null);
        $this->insertCourse('Kurs z 50 w tytule 2020-09-50', null);
        $this->insertCourse('Inny kurs', '150');

        $ids = Course::query()->whereMatchesAdminSelectSearch('#'.$targetId)->pluck('id')->all();

        $this->assertSame([$targetId], $ids);
    }

    public function test_numeric_query_matches_exact_id_without_title_fuzzy(): void
    {
        $noiseId = $this->insertCourse('Szkolenie z 50 uczestnikami', null);
        $targetId = $this->insertCourse('Docelowy kurs', null);

        $ids = Course::query()->whereMatchesAdminSelectSearch((string) $targetId)->pluck('id')->all();

        $this->assertSame([$targetId], $ids);
        $this->assertNotContains($noiseId, $ids);
    }

    public function test_numeric_query_matches_exact_id_old(): void
    {
        $targetId = $this->insertCourse('Kurs Publigo', '998877');

        $ids = Course::query()->whereMatchesAdminSelectSearch('998877')->pluck('id')->all();

        $this->assertSame([$targetId], $ids);
    }

    public function test_text_query_uses_title_and_id_old_like(): void
    {
        $matchId = $this->insertCourse('Office 365 – FORMS', null);
        $this->insertCourse('Coś innego', null);

        $ids = Course::query()->whereMatchesAdminSelectSearch('FORMS')->pluck('id')->all();

        $this->assertSame([$matchId], $ids);
    }

    private function insertCourse(string $title, ?string $idOld): int
    {
        return (int) DB::table('courses')->insertGetId([
            'title' => $title,
            'description' => 'Test',
            'start_date' => now()->subDays(30),
            'end_date' => now()->subDays(1),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'id_old' => $idOld,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
