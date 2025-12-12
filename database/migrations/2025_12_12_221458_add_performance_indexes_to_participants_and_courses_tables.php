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
        // Dodaj indeksy dla tabeli participants - poprawiają wydajność zapytań z email i deleted_at
        Schema::table('participants', function (Blueprint $table) {
            // Indeks na email - używany w subquery do liczenia kursów
            if (!$this->indexExists('participants', 'participants_email_index')) {
                $table->index('email', 'participants_email_index');
            }
            
            // Indeks złożony na email i deleted_at - optymalizuje zapytania WHERE email = ? AND deleted_at IS NULL
            if (!$this->indexExists('participants', 'participants_email_deleted_index')) {
                $table->index(['email', 'deleted_at'], 'participants_email_deleted_index');
            }
            
            // Indeks na course_id i deleted_at - dla zapytań z JOIN
            if (!$this->indexExists('participants', 'participants_course_deleted_index')) {
                $table->index(['course_id', 'deleted_at'], 'participants_course_deleted_index');
            }
        });

        // Dodaj indeksy dla tabeli courses - poprawiają wydajność zapytań z is_paid
        Schema::table('courses', function (Blueprint $table) {
            // Indeks na is_paid - używany w subquery do liczenia płatnych/bezpłatnych kursów
            if (!$this->indexExists('courses', 'courses_is_paid_index')) {
                $table->index('is_paid', 'courses_is_paid_index');
            }
            
            // Indeks złożony na is_paid i deleted_at
            if (!$this->indexExists('courses', 'courses_is_paid_deleted_index')) {
                $table->index(['is_paid', 'deleted_at'], 'courses_is_paid_deleted_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex('participants_email_index');
            $table->dropIndex('participants_email_deleted_index');
            $table->dropIndex('participants_course_deleted_index');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('courses_is_paid_index');
            $table->dropIndex('courses_is_paid_deleted_index');
        });
    }

    /**
     * Sprawdź czy indeks już istnieje
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND index_name = ?",
            [$databaseName, $table, $index]
        );
        
        return $result[0]->count > 0;
    }
};
