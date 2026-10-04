<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_artifact_images', function (Blueprint $table) {
            $table->string('base_path')->nullable()->after('path');
            $table->boolean('include_pne_logo')->default(false)->after('overlay_date');
            $table->boolean('include_sponsor_logo')->default(false)->after('include_pne_logo');
        });
    }

    public function down(): void
    {
        Schema::table('growth_artifact_images', function (Blueprint $table) {
            $table->dropColumn(['base_path', 'include_pne_logo', 'include_sponsor_logo']);
        });
    }
};
