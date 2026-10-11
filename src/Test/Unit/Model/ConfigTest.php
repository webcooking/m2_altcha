<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Webcooking\Altcha\Model\Config;

class ConfigTest extends TestCase
{
    private const array FORMS = [
        'contact' => 'frontend_forms',
        'admin_login' => 'backend_forms',
    ];

    private function config(array $flags, string $secret = 'sec'): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path) => $flags[$path] ?? false
        );
        // The config metadata processor already returns the decrypted secret
        // (backend_model declared in etc/config.xml).
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $path === Config::XML_PATH_SECRET ? $secret : null
        );

        return new Config($scopeConfig, self::FORMS);
    }

    public function testUnknownFormIsNeverEnabled(): void
    {
        $config = $this->config([
            Config::XML_PATH_ENABLED => true,
            'altcha/frontend_forms/unknown' => true,
        ]);
        $this->assertFalse($config->isEnabledForForm('unknown', 1));
    }

    public function testEnabledFormRequiresMasterSwitchFlagAndSecret(): void
    {
        $allOn = [
            Config::XML_PATH_ENABLED => true,
            'altcha/frontend_forms/contact' => true,
        ];
        $this->assertTrue($this->config($allOn)->isEnabledForForm('contact', 1));
        $this->assertFalse($this->config($allOn, secret: '')->isEnabledForForm('contact', 1));
        $this->assertFalse($this->config([
            Config::XML_PATH_ENABLED => false,
            'altcha/frontend_forms/contact' => true,
        ])->isEnabledForForm('contact', 1));
        $this->assertFalse($this->config([
            Config::XML_PATH_ENABLED => true,
            'altcha/frontend_forms/contact' => false,
        ])->isEnabledForForm('contact', 1));
    }

    public function testBackendFormUsesBackendGroupPath(): void
    {
        $config = $this->config([
            Config::XML_PATH_ENABLED => true,
            'altcha/backend_forms/admin_login' => true,
        ]);
        $this->assertTrue($config->isEnabledForForm('admin_login', 0));
        $this->assertTrue($config->isBackendForm('admin_login'));
        $this->assertFalse($config->isBackendForm('contact'));
        $this->assertTrue($config->isKnownForm('contact'));
        $this->assertFalse($config->isKnownForm('nope'));
    }

    public function testSecretIsReturnedAsProvidedByScopeConfig(): void
    {
        $config = $this->config([], secret: 'plain-secret');
        $this->assertSame('plain-secret', $config->getSecret());
    }

    public function testExpiryOutOfBoundsFallsBackToDefault(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('999999');
        $config = new Config($scopeConfig, []);
        $this->assertSame(600, $config->getExpiry());
    }
}
