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
        // Faza 1 - Tabele główne
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('instructors', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('form_orders', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('form_order_participants', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Tabele powiązane z courses
        Schema::table('course_locations', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('course_online_details', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('instructors', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('form_order_participants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('course_locations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('course_online_details', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};