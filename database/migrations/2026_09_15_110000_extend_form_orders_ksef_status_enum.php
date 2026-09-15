<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE form_orders MODIFY COLUMN ksef_status ENUM('queued', 'pending', 'sent', 'failed') NULL COMMENT 'Status KSeF: queued=w kolejce, pending=wysłano czeka na MF, sent=numer, failed=błąd'");
    }

    public function down(): void
    {
        DB::statement("UPDATE form_orders SET ksef_status = 'pending' WHERE ksef_status = 'queued'");
        DB::statement("ALTER TABLE form_orders MODIFY COLUMN ksef_status ENUM('pending', 'sent', 'failed') NULL COMMENT 'Status przesłania do KSeF'");
    }
};
