<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** Domyślny kanał SMS — tylko log (bez zewnętrznego API). */
final class LogSmsChannel implements SmsChannelInterface
{
    public function send(string $to, string $message): bool
    {
        Log::info('SMS (log channel)', ['to' => $to, 'message' => $message]);

        return true;
    }
}
