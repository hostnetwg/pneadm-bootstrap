<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('instructor_invoices', 'settlement_type')) {
            Schema::table('instructor_invoices', function (Blueprint $table) {
                $table->string('settlement_type', 16)->default('invoice')->after('instructor_id');
                $table->index(['settlement_type', 'payment_status']);
            });
        }

        if (! Schema::hasColumn('instructors', 'default_settlement_type')) {
            Schema::table('instructors', function (Blueprint $table) {
                $table->string('default_settlement_type', 16)->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('instructor_invoices', 'settlement_type')) {
            Schema::table('instructor_invoices', function (Blueprint $table) {
                $table->dropIndex(['settlement_type', 'payment_status']);
                $table->dropColumn('settlement_type');
            });
        }

        if (Schema::hasColumn('instructors', 'default_settlement_type')) {
            Schema::table('instructors', function (Blueprint $table) {
                $table->dropColumn('default_settlement_type');
            });
        }
    }
};
