<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->boolean('portal_access_enabled')->default(false)->after('is_active');
            $table->boolean('sms_notifications_enabled')->default(false)->after('portal_access_enabled');
            $table->unsignedBigInteger('pnedu_user_id')->nullable()->after('sms_notifications_enabled');
            $table->index('pnedu_user_id', 'instructors_pnedu_user_id_idx');
        });

        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->boolean('show_in_trainer_panel')->default(false)->after('instructor_id');
            $table->timestamp('trainer_panel_shared_at')->nullable()->after('show_in_trainer_panel');
        });

        Schema::create('trainer_portal_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 16);
            $table->unsignedBigInteger('pneadm_user_id')->nullable()->index();
            $table->unsignedBigInteger('pnedu_user_id')->nullable()->index();
            $table->unsignedBigInteger('closed_training_case_id')->nullable()->index();
            $table->string('kind', 32);
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_portal_notifications');

        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropColumn(['show_in_trainer_panel', 'trainer_panel_shared_at']);
        });

        Schema::table('instructors', function (Blueprint $table) {
            $table->dropIndex('instructors_pnedu_user_id_idx');
            $table->dropColumn(['portal_access_enabled', 'sms_notifications_enabled', 'pnedu_user_id']);
        });
    }
};
