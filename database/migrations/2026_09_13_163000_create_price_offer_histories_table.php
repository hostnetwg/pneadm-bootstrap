<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_offer_histories', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->decimal('offered_price', 10, 2);
            $table->string('kind', 16);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->boolean('excluded_from_omnibus')->default(false);
            $table->string('excluded_reason', 500)->nullable();
            $table->unsignedBigInteger('excluded_by')->nullable();
            $table->timestamp('excluded_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'effective_from'], 'idx_poh_subject_from');
            $table->index(['subject_type', 'subject_id', 'effective_to'], 'idx_poh_subject_to');
        });

        $now = now('UTC');

        foreach (DB::table('product_prices')->whereNull('deleted_at')->get() as $price) {
            $promoActive = (bool) $price->is_promotion
                && $price->promotion_price !== null
                && ($price->promotion_starts_at === null || $price->promotion_starts_at <= $now)
                && ($price->promotion_ends_at === null || $price->promotion_ends_at >= $now);

            DB::table('price_offer_histories')->insert([
                'subject_type' => 'product_price',
                'subject_id' => $price->id,
                'offered_price' => $promoActive ? $price->promotion_price : $price->price,
                'kind' => $promoActive ? 'promotional' : 'regular',
                'effective_from' => $price->created_at ?? $now,
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('course_price_variants')->whereNull('deleted_at')->get() as $variant) {
            $promoActive = (bool) $variant->is_promotion
                && $variant->promotion_type !== 'disabled'
                && $variant->promotion_price !== null
                && (
                    $variant->promotion_type === 'unlimited'
                    || (
                        $variant->promotion_type === 'time_limited'
                        && $variant->promotion_start
                        && $variant->promotion_end
                        && $variant->promotion_start <= $now
                        && $variant->promotion_end >= $now
                    )
                );

            DB::table('price_offer_histories')->insert([
                'subject_type' => 'course_price_variant',
                'subject_id' => $variant->id,
                'offered_price' => $promoActive ? $variant->promotion_price : $variant->price,
                'kind' => $promoActive ? 'promotional' : 'regular',
                'effective_from' => $variant->created_at ?? $now,
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_offer_histories');
    }
};
