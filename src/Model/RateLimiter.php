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
 * Best-effort fixed-window IP rate limit for the challenge endpoint (the cache
 * counter is not atomic; the proof-of-work asymmetry remains the primary
 * anti-abuse mechanism). Behind a proxy/CDN the web server must be configured
 * to restore the real client IP, otherwise all clients share the proxy's IP.
 */
class RateLimiter
{
    private const string CACHE_KEY_PREFIX = 'altcha_ratelimit_';
    private const int WINDOW_SECONDS = 60;
    private const int MAX_REQUESTS_PER_WINDOW = 60;

    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function isAllowed(string $ip): bool
    {
        if ($ip === '') {
            return true;
        }

        $key = $this->cacheKey($ip);
        $count = (int)$this->cache->load($key) + 1;
        $this->cache->save((string)$count, $key, [], self::WINDOW_SECONDS);

        return $count <= self::MAX_REQUESTS_PER_WINDOW;
    }

    private function cacheKey(string $ip): string
    {
        // IPv6 clients rotate within their /64: aggregate to keep the limit
        // meaningful. IPv4-mapped addresses (::ffff:a.b.c.d) must be keyed by
        // the embedded IPv4, or every such client would share one bucket.
        if (str_contains($ip, ':')) {
            $packed = @inet_pton($ip);
            if ($packed !== false && strlen($packed) === 16) {
                if (substr($packed, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
                    $ip = (string)inet_ntop(substr($packed, 12));
                } else {
                    $ip = bin2hex(substr($packed, 0, 8)) . '/64';
                }
            }
        }

        return self::CACHE_KEY_PREFIX . hash('sha256', $ip . '|' . intdiv(time(), self::WINDOW_SECONDS));
    }
}
