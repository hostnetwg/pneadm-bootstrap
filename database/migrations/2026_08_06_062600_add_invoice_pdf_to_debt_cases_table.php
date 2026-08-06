<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_cases', function (Blueprint $table) {
            $table->string('invoice_pdf_path')->nullable()->after('closure_reason');
            $table->string('invoice_pdf_original_name')->nullable()->after('invoice_pdf_path');
            $table->timestamp('invoice_pdf_uploaded_at')->nullable()->after('invoice_pdf_original_name');
            $table->unsignedBigInteger('invoice_pdf_uploaded_by')->nullable()->after('invoice_pdf_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('debt_cases', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_pdf_path',
                'invoice_pdf_original_name',
                'invoice_pdf_uploaded_at',
                'invoice_pdf_uploaded_by',
            ]);
        });
    }
};
