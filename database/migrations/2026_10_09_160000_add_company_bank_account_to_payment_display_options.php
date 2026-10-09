<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_display_options', 'company_bank_account')) {
                $table->string('company_bank_account', 64)->nullable()->after('default_post_end_access_duration_unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (Schema::hasColumn('payment_display_options', 'company_bank_account')) {
                $table->dropColumn('company_bank_account');
            }
        });
    }
};
