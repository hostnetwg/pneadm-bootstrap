<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->timestamp('registration_closed_at')
                ->nullable()
                ->after('show_on_pnedu')
                ->comment('Ręczne zamknięcie publicznych zapisów na tę edycję; strona zostaje widoczna.');

            $table->foreignId('registration_successor_course_id')
                ->nullable()
                ->after('registration_closed_at')
                ->constrained('courses')
                ->nullOnDelete()
                ->comment('Kolejna edycja, na którą kierujemy formularze zapisu po zamknięciu zapisów.');

            $table->text('registration_closed_message')
                ->nullable()
                ->after('registration_successor_course_id')
                ->comment('Opcjonalny komunikat widoczny na starej stronie szkolenia po zamknięciu zapisów.');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['registration_successor_course_id']);
            $table->dropColumn([
                'registration_closed_at',
                'registration_successor_course_id',
                'registration_closed_message',
            ]);
        });
    }
};
