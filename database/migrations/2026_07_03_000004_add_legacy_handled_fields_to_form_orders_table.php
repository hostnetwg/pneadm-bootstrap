<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->timestamp('legacy_handled_at')->nullable()->after('invoice_exempt_by');
            $table->string('legacy_handled_reason', 255)->nullable()->after('legacy_handled_at');
            $table->unsignedBigInteger('legacy_handled_by')->nullable()->after('legacy_handled_reason');
        });
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn(['legacy_handled_at', 'legacy_handled_reason', 'legacy_handled_by']);
        });
    }
};
