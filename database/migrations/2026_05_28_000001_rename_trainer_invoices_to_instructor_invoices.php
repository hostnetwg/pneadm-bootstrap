<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trainer_invoices')) {
            return;
        }

        if (Schema::hasTable('instructor_invoices') && DB::table('instructor_invoices')->count() === 0) {
            Schema::dropIfExists('instructor_invoice_items');
            Schema::dropIfExists('instructor_invoices');
        }

        if ($this->foreignKeyExists('trainer_invoice_items', 'trainer_invoice_id')) {
            Schema::table('trainer_invoice_items', function (Blueprint $table) {
                $table->dropForeign(['trainer_invoice_id']);
            });
        }

        Schema::rename('trainer_invoices', 'instructor_invoices');
        Schema::rename('trainer_invoice_items', 'instructor_invoice_items');

        DB::statement('ALTER TABLE instructor_invoice_items CHANGE trainer_invoice_id instructor_invoice_id BIGINT UNSIGNED NOT NULL');

        if (! $this->foreignKeyExists('instructor_invoice_items', 'instructor_invoice_id')) {
            Schema::table('instructor_invoice_items', function (Blueprint $table) {
                $table->foreign('instructor_invoice_id')
                    ->references('id')
                    ->on('instructor_invoices')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('instructor_invoices')) {
            return;
        }

        if ($this->foreignKeyExists('instructor_invoice_items', 'instructor_invoice_id')) {
            Schema::table('instructor_invoice_items', function (Blueprint $table) {
                $table->dropForeign(['instructor_invoice_id']);
            });
        }

        DB::statement('ALTER TABLE instructor_invoice_items CHANGE instructor_invoice_id trainer_invoice_id BIGINT UNSIGNED NOT NULL');

        Schema::rename('instructor_invoices', 'trainer_invoices');
        Schema::rename('instructor_invoice_items', 'trainer_invoice_items');

        if (! $this->foreignKeyExists('trainer_invoice_items', 'trainer_invoice_id')) {
            Schema::table('trainer_invoice_items', function (Blueprint $table) {
                $table->foreign('trainer_invoice_id')
                    ->references('id')
                    ->on('trainer_invoices')
                    ->onDelete('cascade');
            });
        }
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
