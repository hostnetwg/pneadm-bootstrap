<?php

namespace App\Models\Concerns;

trait NormalizesUserEmail
{
    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = strtolower(trim($email));

        return $normalized === '' ? null : $normalized;
    }

    public static function buildEmailUniqueSlot(?string $email, mixed $deletedAt): ?string
    {
        $email = static::normalizeEmail($email);
        if ($email === null) {
            return null;
        }

        if ($deletedAt === null) {
            return $email;
        }

        if ($deletedAt instanceof \DateTimeInterface) {
            return $email.'#'.$deletedAt->format('Y-m-d H:i:s');
        }

        return $email.'#'.trim((string) $deletedAt);
    }

    protected static function bootNormalizesUserEmail(): void
    {
        $sync = function (self $model): void {
            if ($model->isDirty('email')) {
                $model->email = static::normalizeEmail($model->email);
            }

            $model->email_unique_slot = static::buildEmailUniqueSlot(
                $model->email,
                $model->deleted_at
            );
        };

        // saving — przed insertem/updatem
        static::saving($sync);

        // creating — zapas tuż przed INSERT (np. gdy starszy kod / cache nie odpalił saving poprawnie)
        static::creating($sync);

        static::restored(function (self $model): void {
            $model->email_unique_slot = static::buildEmailUniqueSlot($model->email, null);
        });
    }
}
