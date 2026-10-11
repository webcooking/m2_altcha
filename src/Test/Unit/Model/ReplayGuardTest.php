<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\TestCase;
use Webcooking\Altcha\Model\ReplayGuard;

class ReplayGuardTest extends TestCase
{
    public function testIsConsumedReflectsCacheState(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnOnConsecutiveCalls(false, '1');
        $guard = new ReplayGuard($cache);

        $this->assertFalse($guard->isConsumed('sig'));
        $this->assertTrue($guard->isConsumed('sig'));
    }

    public function testConsumeStoresHashedKeyWithTtl(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('save')
            ->with(
                '1',
                'altcha_replay_' . hash('sha256', 'sig'),
                [],
                120
            );

        (new ReplayGuard($cache))->consume('sig', 120);
    }

    public function testConsumeClampsNonPositiveTtl(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('save')
            ->with('1', $this->anything(), [], 1);

        (new ReplayGuard($cache))->consume('sig', -5);
    }
}
