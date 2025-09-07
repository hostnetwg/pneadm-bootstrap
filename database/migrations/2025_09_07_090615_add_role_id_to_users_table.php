<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Database\Seeders\RolePermissionSeeder;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Najpierw dodaj kolumny bez klucza obcego
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->default(4); // Domyślnie rola "user"
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
        });

        // Uruchom seeder z rolami
        $this->call(RolePermissionSeeder::class);

        // Teraz dodaj klucz obcy
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'is_active', 'last_login_at', 'last_login_ip']);
        });
    }
};
