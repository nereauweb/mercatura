<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

final class CaughtExceptionLogger
{
    public static function error(string $message, Throwable $e, array $context = []): void
    {
        Log::error($message, array_merge([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], $context));
    }
}
