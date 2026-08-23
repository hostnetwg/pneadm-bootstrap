<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('status');
            $table->index(['status', 'sort_order'], 'articles_status_sort_order_index');
        });

        $articles = DB::table('articles')
            ->whereNull('deleted_at')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->pluck('id');

        foreach ($articles as $position => $id) {
            DB::table('articles')->where('id', $id)->update(['sort_order' => $position]);
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('articles_status_sort_order_index');
            $table->dropColumn('sort_order');
        });
    }
};
