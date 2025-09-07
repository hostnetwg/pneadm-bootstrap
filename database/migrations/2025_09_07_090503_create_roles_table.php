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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // super_admin, admin, manager, user
            $table->string('display_name'); // Super Administrator, Administrator, Manager, User
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // Czy to rola systemowa (nie można usunąć)
            $table->integer('level')->default(1); // Poziom uprawnień (1-4)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
