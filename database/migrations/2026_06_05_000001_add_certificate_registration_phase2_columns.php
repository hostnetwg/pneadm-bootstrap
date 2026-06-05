<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (! Schema::hasColumn('participants', 'email_normalized')) {
                $table->string('email_normalized', 191)->nullable()->after('email');
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'next_participant_order')) {
                $table->unsignedInteger('next_participant_order')->default(1);
            }
        });

        DB::table('participants')->whereNotNull('deleted_at')->update(['email_normalized' => null]);

        DB::statement("
            UPDATE participants
            SET email_normalized = LOWER(TRIM(email))
            WHERE deleted_at IS NULL
              AND email IS NOT NULL
              AND TRIM(email) != ''
        ");

        DB::statement('
            UPDATE participants p
            INNER JOIN (
                SELECT course_id, email_normalized, MIN(id) AS keep_id
                FROM participants
                WHERE email_normalized IS NOT NULL
                GROUP BY course_id, email_normalized
                HAVING COUNT(*) > 1
            ) d ON p.course_id = d.course_id
               AND p.email_normalized = d.email_normalized
               AND p.id != d.keep_id
            SET p.email_normalized = NULL
        ');

        Schema::table('participants', function (Blueprint $table) {
            if (! $this->indexExists('participants', 'participants_course_email_normalized_unique')) {
                $table->unique(
                    ['course_id', 'email_normalized'],
                    'participants_course_email_normalized_unique'
                );
            }
        });

        DB::statement('
            UPDATE courses c
            INNER JOIN (
                SELECT course_id, COALESCE(MAX(`order`), 0) + 1 AS next_ord
                FROM participants
                GROUP BY course_id
            ) p ON p.course_id = c.id
            SET c.next_participant_order = GREATEST(1, p.next_ord)
        ');
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if ($this->indexExists('participants', 'participants_course_email_normalized_unique')) {
                $table->dropUnique('participants_course_email_normalized_unique');
            }
            if (Schema::hasColumn('participants', 'email_normalized')) {
                $table->dropColumn('email_normalized');
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'next_participant_order')) {
                $table->dropColumn('next_participant_order');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(*) as count
             FROM information_schema.statistics
             WHERE table_schema = ?
             AND table_name = ?
             AND index_name = ?',
            [$databaseName, $table, $index]
        );

        return $result[0]->count > 0;
    }
};
