<?php

namespace Tests\Unit;

use App\Models\Participant;
use Tests\TestCase;

class ParticipantEmailNormalizationTest extends TestCase
{
    public function test_normalize_email_trims_and_lowercases(): void
    {
        $this->assertSame('jan@example.com', Participant::normalizeEmail('  JAN@Example.com  '));
    }

    public function test_normalize_email_returns_null_for_empty(): void
    {
        $this->assertNull(Participant::normalizeEmail('   '));
        $this->assertNull(Participant::normalizeEmail(null));
    }
}
