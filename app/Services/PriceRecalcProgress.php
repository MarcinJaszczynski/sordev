<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PriceRecalcProgress
{
    // Cache key per user
    public static function key(int $userId): string
    {
        return "price_recalc:user:{$userId}";
    }

    public static function start(int $userId, int $total): void
    {
        $data = [
            'total' => $total,
            'processed' => 0,
            'errors' => 0,
            'started_at' => time(),
            'finished' => false,
        ];
        // keep for 1 day
        Cache::put(self::key($userId), $data, 86400);
    }

    public static function increment(int $userId, int $by = 1): void
    {
        $key = self::key($userId);
        $data = Cache::get($key, null);
        if (!is_array($data)) return;
        $data['processed'] = ($data['processed'] ?? 0) + $by;
        Cache::put($key, $data, 86400);
    }

    public static function addError(int $userId, int $by = 1): void
    {
        $key = self::key($userId);
        $data = Cache::get($key, null);
        if (!is_array($data)) return;
        $data['errors'] = ($data['errors'] ?? 0) + $by;
        Cache::put($key, $data, 86400);
    }

    public static function finish(int $userId): void
    {
        $key = self::key($userId);
        $data = Cache::get($key, null);
        if (!is_array($data)) return;
        $data['finished'] = true;
        $data['finished_at'] = time();
        Cache::put($key, $data, 86400);
    }

    public static function get(int $userId): array
    {
        return Cache::get(self::key($userId), [
            'total' => 0,
            'processed' => 0,
            'errors' => 0,
            'started_at' => null,
            'finished' => false,
        ]);
    }

    public static function reset(int $userId): void
    {
        Cache::forget(self::key($userId));
    }
}
