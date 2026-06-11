<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (! Schema::hasIndex('participants', 'participants_email_normalized_index')) {
                $table->index('email_normalized', 'participants_email_normalized_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (Schema::hasIndex('participants', 'participants_email_normalized_index')) {
                $table->dropIndex('participants_email_normalized_index');
            }
        });
    }
};
