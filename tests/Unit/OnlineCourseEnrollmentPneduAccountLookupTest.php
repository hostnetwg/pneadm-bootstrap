<?php

namespace Tests\Unit;

use App\Models\PneduUser;
use App\Services\OnlineCourseEnrollmentPneduAccountLookup;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnlineCourseEnrollmentPneduAccountLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (! Schema::connection('pnedu')->hasTable('users')) {
                $this->markTestSkipped('Brak tabeli users w bazie pnedu.');
            }
        } catch (\Throwable) {
            $this->markTestSkipped('Brak połączenia z testową bazą pnedu.');
        }
    }

    public function test_maps_existing_pnedu_user_by_normalized_email(): void
    {
        $withAccount = 'z.kontem.'.uniqid().'@example.test';
        $withoutAccount = 'bez.konta.'.uniqid().'@example.test';

        $pneduUser = PneduUser::query()->create([
            'first_name' => 'Anna',
            'last_name' => 'Konto',
            'email' => strtoupper($withAccount),
            'password' => 'HasloTestowe1!',
        ]);

        try {
            $map = app(OnlineCourseEnrollmentPneduAccountLookup::class)->byEmails([
                $withAccount,
                $withoutAccount,
            ]);

            $this->assertArrayHasKey($withAccount, $map);
            $this->assertSame($pneduUser->id, $map[$withAccount]->id);
            $this->assertArrayNotHasKey($withoutAccount, $map);
        } finally {
            $pneduUser->forceDelete();
        }
    }
}
