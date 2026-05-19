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

    protected static function bootNormalizesUserEmail(): void
    {
        static::saving(function (self $model): void {
            if ($model->isDirty('email')) {
                $model->email = static::normalizeEmail($model->email);
            }
        });
    }
}
