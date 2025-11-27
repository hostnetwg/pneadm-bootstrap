<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            // Dodaj nową kolumnę source_type_id
            $table->unsignedBigInteger('source_type_id')->nullable()->after('description');
            $table->foreign('source_type_id')->references('id')->on('marketing_source_types')->onDelete('set null');
            $table->index('source_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropForeign(['source_type_id']);
            $table->dropIndex(['source_type_id']);
            $table->dropColumn('source_type_id');
        });
    }
};
