<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->timestamp('invoice_exempt_at')->nullable()->after('invoice_payment_delay');
            $table->string('invoice_exempt_reason', 255)->nullable()->after('invoice_exempt_at');
            $table->unsignedBigInteger('invoice_exempt_by')->nullable()->after('invoice_exempt_reason');
        });
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_exempt_at', 'invoice_exempt_reason', 'invoice_exempt_by']);
        });
    }
};
