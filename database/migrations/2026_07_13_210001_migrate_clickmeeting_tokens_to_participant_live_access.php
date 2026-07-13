<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('participant_live_access')) {
            return;
        }

        if (! Schema::hasColumn('form_orders', 'pnedu_clickmeeting_token')) {
            return;
        }

        $orders = DB::table('form_orders as fo')
            ->join('form_order_participants as fop', function ($join) {
                $join->on('fop.form_order_id', '=', 'fo.id')
                    ->where('fop.is_primary', true)
                    ->whereNotNull('fop.participant_id');
            })
            ->where(function ($q) {
                $q->whereNotNull('fo.pnedu_clickmeeting_status')
                    ->orWhereNotNull('fo.pnedu_clickmeeting_token');
            })
            ->select([
                'fo.id as form_order_id',
                'fo.product_id as course_id',
                'fop.participant_id',
                'fo.pnedu_clickmeeting_status',
                'fo.pnedu_clickmeeting_message',
                'fo.pnedu_clickmeeting_synced_at',
                'fo.pnedu_clickmeeting_token',
            ])
            ->get();

        $now = now();

        foreach ($orders as $row) {
            $exists = DB::table('participant_live_access')
                ->where('participant_id', $row->participant_id)
                ->exists();

            if ($exists) {
                continue;
            }

            $expiresAt = null;
            if ($row->course_id) {
                $courseEnd = DB::table('courses')->where('id', $row->course_id)->value('end_date');
                if ($courseEnd) {
                    $expiresAt = $courseEnd;
                }
            }

            $eventId = null;
            if ($row->course_id) {
                $eventId = DB::table('course_online_details')
                    ->where('course_id', $row->course_id)
                    ->value('clickmeeting_event_id');
            }

            DB::table('participant_live_access')->insert([
                'participant_id' => $row->participant_id,
                'course_id' => $row->course_id,
                'form_order_id' => $row->form_order_id,
                'platform' => 'clickmeeting',
                'clickmeeting_event_id' => $eventId,
                'access_type' => $row->pnedu_clickmeeting_token ? 3 : null,
                'token' => $row->pnedu_clickmeeting_token,
                'status' => $row->pnedu_clickmeeting_status,
                'message' => $row->pnedu_clickmeeting_message,
                'synced_at' => $row->pnedu_clickmeeting_synced_at,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn('pnedu_clickmeeting_token');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('form_orders', 'pnedu_clickmeeting_token')) {
            Schema::table('form_orders', function (Blueprint $table) {
                $table->string('pnedu_clickmeeting_token', 64)
                    ->nullable()
                    ->after('pnedu_clickmeeting_message');
            });
        }

        if (! Schema::hasTable('participant_live_access')) {
            return;
        }

        $rows = DB::table('participant_live_access')
            ->whereNotNull('form_order_id')
            ->get(['form_order_id', 'token']);

        foreach ($rows as $row) {
            DB::table('form_orders')
                ->where('id', $row->form_order_id)
                ->update(['pnedu_clickmeeting_token' => $row->token]);
        }
    }
};
