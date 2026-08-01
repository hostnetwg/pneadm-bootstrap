<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename', 255);
            $table->string('stored_path', 500)->nullable();
            $table->string('file_hash', 64)->index();
            $table->string('source', 32)->default('mbank');
            $table->string('status', 32)->default('parsed');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_incoming')->default(0);
            $table->unsignedInteger('rows_matched')->default(0);
            $table->unsignedInteger('rows_duplicate')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained('bank_statement_imports')->cascadeOnDelete();
            $table->date('operation_date');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PLN');
            $table->text('description');
            $table->string('account_label', 255)->nullable();
            $table->string('category', 120)->nullable();
            $table->string('counterparty_account', 64)->nullable();
            $table->string('fingerprint', 64);
            $table->boolean('is_incoming')->default(true);
            $table->timestamps();

            $table->unique('fingerprint');
            $table->index(['operation_date', 'amount']);
            $table->index(['bank_statement_import_id', 'is_incoming']);
        });

        Schema::create('bank_transaction_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignId('form_order_id')->nullable()->constrained('form_orders')->nullOnDelete();
            $table->foreignId('debt_case_id')->nullable()->constrained('debt_cases')->nullOnDelete();
            $table->string('confidence', 16);
            $table->json('match_reasons')->nullable();
            $table->string('status', 16)->default('suggested');
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'confidence']);
            $table->index(['bank_transaction_id', 'status']);
            $table->index('form_order_id');
            $table->index('debt_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_matches');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_statement_imports');
    }
};
