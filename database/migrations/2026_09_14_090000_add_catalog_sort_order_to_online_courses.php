<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_courses', function (Blueprint $table) {
            $table->unsignedInteger('catalog_sort_order')->default(0)->after('visible_in_dashboard');
            $table->index('catalog_sort_order', 'idx_online_courses_catalog_sort');
        });

        $ids = DB::table('online_courses')
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $position => $id) {
            DB::table('online_courses')->where('id', $id)->update([
                'catalog_sort_order' => $position,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('online_courses', function (Blueprint $table) {
            $table->dropIndex('idx_online_courses_catalog_sort');
            $table->dropColumn('catalog_sort_order');
        });
    }
};
