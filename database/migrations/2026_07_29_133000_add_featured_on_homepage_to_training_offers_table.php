<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_offers', function (Blueprint $table) {
            $table->boolean('featured_on_homepage')
                ->default(false)
                ->after('show_on_pnedu')
                ->index('idx_training_offers_featured_homepage');
        });
    }

    public function down(): void
    {
        Schema::table('training_offers', function (Blueprint $table) {
            $table->dropIndex('idx_training_offers_featured_homepage');
            $table->dropColumn('featured_on_homepage');
        });
    }
};
