<?php

namespace App\Support;

use Illuminate\Http\Client\Response;

class RspoApiResponse
{
    /**
     * API RSPO (MEN) zwraca czasem HTTP 403 mimo poprawnego JSON w body.
     */
    public static function hasUsableBody(Response $response): bool
    {
        if ($response->successful()) {
            return true;
        }

        if ($response->status() !== 403) {
            return false;
        }

        $body = ltrim($response->body());

        return $body !== '' && in_array($body[0], ['{', '['], true);
    }
}
