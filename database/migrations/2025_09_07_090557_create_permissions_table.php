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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // users.create, users.edit, courses.manage, etc.
            $table->string('display_name'); // Tworzenie użytkowników, Edycja użytkowników, Zarządzanie szkoleniami
            $table->text('description')->nullable();
            $table->string('category')->default('general'); // users, courses, orders, admin, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
