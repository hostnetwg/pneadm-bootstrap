<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_source_types', function (Blueprint $table) {
            $table->string('utm_source', 100)->nullable()->after('slug');
            $table->string('default_utm_medium', 50)->default('paid')->after('utm_source');
        });

        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->string('utm_medium', 50)->nullable()->after('source_type_id');
            $table->unsignedBigInteger('course_id')->nullable()->after('utm_medium');
            $table->string('landing_target', 20)->default('course_show')->after('course_id');

            $table->index('course_id');
        });

        $utmDefaults = [
            'facebook' => ['utm_source' => 'facebook', 'default_utm_medium' => 'paid'],
            'email' => ['utm_source' => 'newsletter', 'default_utm_medium' => 'email'],
            'website' => ['utm_source' => 'pnedu', 'default_utm_medium' => 'banner'],
            'remarketing' => ['utm_source' => 'facebook', 'default_utm_medium' => 'paid'],
            'training' => ['utm_source' => 'webinar', 'default_utm_medium' => 'webinar'],
            'organic' => ['utm_source' => 'facebook', 'default_utm_medium' => 'social'],
        ];

        foreach ($utmDefaults as $slug => $values) {
            DB::table('marketing_source_types')
                ->where('slug', $slug)
                ->update(array_merge($values, ['updated_at' => now()]));
        }
    }

    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
            $table->dropColumn(['utm_medium', 'course_id', 'landing_target']);
        });

        Schema::table('marketing_source_types', function (Blueprint $table) {
            $table->dropColumn(['utm_source', 'default_utm_medium']);
        });
    }
};
