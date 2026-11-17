<?php

namespace Tests\Feature;

use App\Jobs\SendOnlineCourseMigrationNoticeJob;
use App\Mail\OnlineCourseMigrationNoticeMail;
use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use App\Models\OnlineCourseMigrationNotice;
use App\Models\PneduUser;
use App\Models\User;
use App\Services\OnlineCourseEnrollmentPneduAccountLookup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnlineCourseMigrationNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        config([
            'mail.system.mailer' => 'array',
            'services.pnedu_frontend_url' => 'https://pnedu.pl',
            'services.pneadm.api_token' => 'test-token',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_page_lists_one_row_per_person_and_skips_other_sources(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $canva = $this->course('Canva dla testu');
        $tik = $this->course('TIK dla testu');

        $this->enroll($canva, 'anna.dwa@example.test', 'Anna', 'Dwa');
        $this->enroll($tik, 'anna.dwa@example.test', 'Anna', 'Dwa');
        $this->enroll($canva, 'bartek.jeden@example.test', 'Bartek', 'Jeden');
        $this->enroll($tik, 'celina.wygasla@example.test', 'Celina', 'Wygasła', Carbon::parse('2024-01-01', 'UTC'));
        $this->enroll($canva, 'dawid.zamowienie@example.test', 'Dawid', 'Zamówienie', null, 'product_order');
        $this->enroll($tik, 'ewa.reczna@example.test', 'Ewa', 'Ręczna', null, 'manual');

        $this->actingAs($admin)
            ->get(route('online-courses.migration-notice.index'))
            ->assertOk()
            ->assertSee('anna.dwa@example.test')
            ->assertSee('bartek.jeden@example.test')
            ->assertSee('Przerwij wysyłkę')
            ->assertSee('Ma konto')
            ->assertSee('Brak konta')
            ->assertSee('nie zakłada konta')
            ->assertSee('Załóż konto na pnedu.pl')
            ->assertSee('Nie pamiętam hasła')
            ->assertDontSee('celina.wygasla@example.test')
            ->assertDontSee('dawid.zamowienie@example.test')
            ->assertDontSee('ewa.reczna@example.test');

        $filtered = $this->actingAs($admin)
            ->get(route('online-courses.migration-notice.index', ['course_id' => $tik->id]));
        $filtered->assertOk()
            ->assertSee('anna.dwa@example.test')
            ->assertDontSee('bartek.jeden@example.test');
        $this->assertMatchesRegularExpression(
            '/anna\.dwa@example\.test<\/td>\s*<td>2<\/td>/',
            $filtered->getContent()
        );

        $this->app->instance(OnlineCourseEnrollmentPneduAccountLookup::class, $this->accountLookup(['bartek.jeden@example.test']));

        $withAccount = $this->actingAs($admin)
            ->get(route('online-courses.migration-notice.index', ['account' => 'yes']));
        $withAccount->assertOk()
            ->assertSee('bartek.jeden@example.test')
            ->assertDontSee('anna.dwa@example.test');

        $withoutAccount = $this->actingAs($admin)
            ->get(route('online-courses.migration-notice.index', ['account' => 'no']));
        $withoutAccount->assertOk()
            ->assertSee('anna.dwa@example.test')
            ->assertDontSee('bartek.jeden@example.test');

        $this->actingAs($admin)
            ->getJson(route('online-courses.migration-notice.unsent-count', ['account' => 'yes']))
            ->assertOk()
            ->assertJsonPath('unsent_count', 1);

        $byCount = $this->actingAs($admin)
            ->get(route('online-courses.migration-notice.index', ['course_count' => 1]));
        $byCount->assertOk()
            ->assertSee('bartek.jeden@example.test')
            ->assertDontSee('anna.dwa@example.test')
            ->assertSee('name="course_count"', false);

        $this->actingAs($admin)
            ->getJson(route('online-courses.migration-notice.unsent-count', ['course_count' => 2]))
            ->assertOk()
            ->assertJsonPath('unsent_count', 1);

        $this->get(route('online-courses.index'))
            ->assertOk()
            ->assertSee('Informacje o przeniesieniu kursów z nowoczesna-edukacja.pl');
    }

    public function test_preview_lists_all_courses_and_send_does_not_create_account(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $canva = $this->course('Canva dla testu');
        $tik = $this->course('TIK dla testu');
        $this->enroll($canva, 'anna.dwa@example.test', 'Anna', 'Dwa');
        $this->enroll($tik, 'anna.dwa@example.test', 'Anna', 'Dwa');

        $this->actingAs($admin);
        $preview = $this->get(route('online-courses.migration-notice.preview', ['email' => 'anna.dwa@example.test']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('has_pnedu_account', false)
            ->assertJsonPath('course_count', 2)
            ->assertJsonPath('to', 'anna.dwa@example.test');

        $html = (string) $preview->json('body_html');
        $this->assertStringContainsString('Canva dla testu', $html);
        $this->assertStringContainsString('TIK dla testu', $html);
        $this->assertStringContainsString('Załóż konto na pnedu.pl', $html);
        $this->assertStringContainsString('nie ma jeszcze konta', $html);
        $this->assertStringNotContainsString('Nie pamiętam hasła', $html);

        $accountsBefore = $this->pneduAccountCount('anna.dwa@example.test');

        Mail::fake();
        $this->from(route('online-courses.migration-notice.index'))
            ->post(route('online-courses.migration-notice.send'), ['email' => 'anna.dwa@example.test'])
            ->assertRedirect();

        Mail::assertSent(OnlineCourseMigrationNoticeMail::class, function (OnlineCourseMigrationNoticeMail $mail) {
            return $mail->hasTo('anna.dwa@example.test')
                && $mail->hasPneduAccount === false
                && $mail->courseTitles === ['Canva dla testu', 'TIK dla testu'];
        });
        $this->assertDatabaseHas('online_course_migration_notices', [
            'email' => 'anna.dwa@example.test',
            'status' => OnlineCourseMigrationNotice::STATUS_SENT,
        ]);
        if ($accountsBefore !== null) {
            $this->assertSame($accountsBefore, $this->pneduAccountCount('anna.dwa@example.test'));
        }

        $this->postJson(route('online-courses.migration-notice.send'), ['email' => 'anna.dwa@example.test'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('email', 'anna.dwa@example.test')
            ->assertJsonPath('sent_count', 2)
            ->assertJsonPath('unsent_count', 0);
    }

    public function test_bulk_sends_one_job_per_person_and_skips_already_sent(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $canva = $this->course('Canva dla testu');
        $tik = $this->course('TIK dla testu');
        $this->enroll($canva, 'anna.dwa@example.test', 'Anna', 'Dwa');
        $this->enroll($tik, 'anna.dwa@example.test', 'Anna', 'Dwa');
        $this->enroll($canva, 'bartek.jeden@example.test', 'Bartek', 'Jeden');
        $this->enroll($tik, 'celina.wygasla@example.test', 'Celina', 'Wygasła', Carbon::parse('2024-01-01', 'UTC'));

        OnlineCourseMigrationNotice::query()->create([
            'email' => 'anna.dwa@example.test',
            'status' => OnlineCourseMigrationNotice::STATUS_SENT,
            'sent_at' => now(),
        ]);

        Bus::fake();
        $this->actingAs($admin)
            ->from(route('online-courses.migration-notice.index'))
            ->post(route('online-courses.migration-notice.send-bulk'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $queued = OnlineCourseMigrationNotice::query()
            ->where('status', OnlineCourseMigrationNotice::STATUS_QUEUED)
            ->pluck('email')
            ->all();
        $this->assertSame(['bartek.jeden@example.test'], $queued);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 1
                && $batch->jobs->first() instanceof SendOnlineCourseMigrationNoticeJob;
        });

        $this->getJson(route('online-courses.migration-notice.batch-status'))
            ->assertOk()
            ->assertJsonPath('active', false);
    }

    /**
     * @param  list<string>  $emailsWithAccount
     */
    private function accountLookup(array $emailsWithAccount): OnlineCourseEnrollmentPneduAccountLookup
    {
        $lookup = $this->createMock(OnlineCourseEnrollmentPneduAccountLookup::class);
        $lookup->method('emailsHavingAccounts')->willReturnCallback(function ($emails) use ($emailsWithAccount) {
            $found = [];
            foreach ($emails as $email) {
                $normalized = strtolower(trim((string) $email));
                if (in_array($normalized, $emailsWithAccount, true)) {
                    $found[] = $normalized;
                }
            }

            return $found;
        });
        $lookup->method('byEmails')->willReturnCallback(function ($emails) use ($emailsWithAccount) {
            $found = [];
            foreach ($emails as $email) {
                $normalized = strtolower(trim((string) $email));
                if (! in_array($normalized, $emailsWithAccount, true)) {
                    continue;
                }
                $user = new PneduUser;
                $user->forceFill(['email' => $normalized]);
                $found[$normalized] = $user;
            }

            return $found;
        });

        return $lookup;
    }

    private function course(string $title): OnlineCourse
    {
        return OnlineCourse::query()->create([
            'slug' => 'kurs-'.uniqid(),
            'title' => $title,
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);
    }

    private function enroll(
        OnlineCourse $course,
        string $email,
        string $firstName,
        string $lastName,
        ?Carbon $expiresAt = null,
        string $source = 'publigo_migration',
    ): OnlineCourseEnrollment {
        return OnlineCourseEnrollment::query()->create([
            'online_course_id' => $course->id,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'access_source' => $source,
            'access_expires_at' => $expiresAt,
        ]);
    }

    private function pneduAccountCount(string $email): ?int
    {
        try {
            if (! Schema::connection('pnedu')->hasTable('users')) {
                return null;
            }

            return PneduUser::query()->where('email', $email)->count();
        } catch (\Throwable) {
            return null;
        }
    }
}
