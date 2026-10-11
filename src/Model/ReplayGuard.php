<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Model;

use Magento\Framework\App\CacheInterface;

/**
 * Marks solved challenges as consumed so the same payload cannot be submitted
 * twice within its validity window. The check-then-set is serialized by
 * SolutionVerifier behind a shared lock. Defense in depth: the first-order
 * guarantee remains the HMAC signature + strict expiry (a flushed cache only
 * re-opens the remaining TTL window). In multi-node setups the cache backend
 * MUST be shared (e.g. Redis), otherwise a replay can pass on another node.
 */
class ReplayGuard
{
    private const string CACHE_KEY_PREFIX = 'altcha_replay_';

    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function isConsumed(string $signature): bool
    {
        return $this->cache->load($this->cacheKey($signature)) !== false;
    }

    public function consume(string $signature, int $ttl): void
    {
        $this->cache->save('1', $this->cacheKey($signature), [], max(1, $ttl));
    }

    private function cacheKey(string $signature): string
    {
        return self::CACHE_KEY_PREFIX . hash('sha256', $signature);
    }
}
