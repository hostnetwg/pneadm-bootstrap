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
        // Najpierw dodaj kolumny bez klucza obcego (sprawdź czy już nie istnieją)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->unsignedBigInteger('role_id')->default(4); // Domyślnie rola "user"
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip')->nullable();
            }
        });

        // Uruchom seeder z rolami
        $seeder = new \Database\Seeders\RolePermissionSeeder();
        $seeder->run();

        // Teraz dodaj klucz obcy (sprawdź czy już nie istnieje)
        if (!Schema::hasColumn('users', 'role_id') || !$this->foreignKeyExists('users', 'users_role_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('restrict');
            });
        }
    }

    private function foreignKeyExists($table, $keyName)
    {
        $foreignKeys = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableForeignKeys($table);
        
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->getName() === $keyName) {
                return true;
            }
        }
        return false;
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
