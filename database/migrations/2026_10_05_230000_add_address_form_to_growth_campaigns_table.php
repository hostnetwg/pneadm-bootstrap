<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_campaigns', function (Blueprint $table) {
            $table->string('address_form', 16)->default('ty')->after('communication_voice_instructor_id');
        });
    }

    public function down(): void
    {
        Schema::table('growth_campaigns', function (Blueprint $table) {
            $table->dropColumn('address_form');
        });
    }
};
