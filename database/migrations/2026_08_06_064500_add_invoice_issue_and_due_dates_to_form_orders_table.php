<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->date('invoice_issue_date')->nullable()->after('invoice_number')
                ->comment('Data wystawienia FV (DataWystawienia z iFirma / przy wystawieniu)');
            $table->date('invoice_due_date')->nullable()->after('invoice_issue_date')
                ->comment('Termin płatności FV (TerminPlatnosci z iFirma / przy wystawieniu)');
        });
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_issue_date', 'invoice_due_date']);
        });
    }
};
