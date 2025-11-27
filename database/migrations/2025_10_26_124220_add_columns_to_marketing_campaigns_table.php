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
            $table->string('campaign_code', 50)->unique()->after('id');
            $table->string('name')->after('campaign_code');
            $table->text('description')->nullable()->after('name');
            $table->string('source_type', 50)->default('facebook')->after('description');
            $table->boolean('is_active')->default(true)->after('source_type');
            
            $table->index('campaign_code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropIndex(['campaign_code']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['campaign_code', 'name', 'description', 'source_type', 'is_active']);
        });
    }
};