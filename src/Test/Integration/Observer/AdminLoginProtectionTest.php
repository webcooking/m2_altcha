<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Integration\Observer;

use Magento\Backend\Model\Auth;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\Plugin\AuthenticationException;
use Magento\TestFramework\Bootstrap as TestBootstrap;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Webcooking\Altcha\Test\Integration\ChallengeSolverTrait;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class AdminLoginProtectionTest extends TestCase
{
    use ChallengeSolverTrait;

    private function login(): void
    {
        $auth = Bootstrap::getObjectManager()->create(Auth::class);
        $auth->login(TestBootstrap::ADMIN_NAME, TestBootstrap::ADMIN_PASSWORD);
        $this->assertTrue($auth->isLoggedIn());
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/general/difficulty easy
     * @magentoConfigFixture default/altcha/backend_forms/admin_login 1
     */
    public function testMissingPayloadBlocksAuthentication(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->login();
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/general/difficulty easy
     * @magentoConfigFixture default/altcha/backend_forms/admin_login 1
     */
    public function testSolvedPayloadBoundToStoreZeroAuthenticates(): void
    {
        $request = Bootstrap::getObjectManager()->get(RequestInterface::class);
        $request->setPostValue('altcha', $this->solvedAltchaPayload('admin_login', 0));

        $this->login();
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     */
    public function testDisabledFlagDoesNotInterfere(): void
    {
        $this->login();
    }
}
