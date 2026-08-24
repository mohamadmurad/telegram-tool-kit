<?php

namespace TelegramKit\Support;

use Illuminate\Contracts\Cache\Repository;

/**
 * Collapses bursts of identical log entries into a single Telegram
 * message per fixed-length window. The first occurrence in a window is
 * sent immediately; later occurrences in the same window are suppressed
 * and counted. The occurrence that opens the next window is told how
 * many were suppressed in the window it's replacing, so the caller can
 * append e.g. "(+46 similar in the last 60s)".
 */
class Debouncer
{
    private const CACHE_PREFIX = 'telegramkit:debounce:';

    public function __construct(
        private readonly Repository $cache,
        private readonly int $windowSeconds,
    ) {}

    public function register(string $fingerprint): DebounceResult
    {
        if ($this->windowSeconds <= 0) {
            return new DebounceResult(true);
        }

        $key = self::CACHE_PREFIX.md5($fingerprint);
        $bucket = $this->cache->get($key);
        $now = time();

        if ($bucket === null || ($now - $bucket['first_at']) >= $this->windowSeconds) {
            $suppressed = max(0, ($bucket['count'] ?? 1) - 1);

            $this->cache->put($key, ['count' => 1, 'first_at' => $now], $this->windowSeconds);

            return new DebounceResult(true, $suppressed);
        }

        $bucket['count']++;
        $this->cache->put($key, $bucket, $this->windowSeconds);

        return new DebounceResult(false);
    }
}
