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
        // Sprawdź i dodaj kolumny do tabeli users
        Schema::table('users', function (Blueprint $table) {
            // Dodaj remember_token jeśli nie istnieje
            if (!Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
            
            // Dodaj role_id jeśli nie istnieje
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->unsignedBigInteger('role_id')->default(1);
            }
            
            // Dodaj is_active jeśli nie istnieje
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            
            // Dodaj last_login_at jeśli nie istnieje
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            
            // Dodaj last_login_ip jeśli nie istnieje
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'remember_token',
                'role_id', 
                'is_active', 
                'last_login_at', 
                'last_login_ip'
            ]);
        });
    }
};
