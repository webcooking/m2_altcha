<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Integration\Controller;

use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoDbIsolation enabled
 */
class ChallengeTest extends AbstractController
{
    private const string SECRET = 'aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/frontend_forms/contact 1
     */
    public function testEnabledFormReturnsSignedChallengeBoundToFormAndStore(): void
    {
        $this->getRequest()->setParams(['form' => 'contact']);
        $this->dispatch('altcha/challenge/index');

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());
        $header = $this->getResponse()->getHeader('Cache-Control');
        $this->assertStringContainsString('no-store', (string)($header ? $header->getFieldValue() : ''));

        $body = json_decode($this->getResponse()->getBody(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('SHA-256', $body['parameters']['algorithm']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string)$body['signature']);
        $this->assertSame('contact', $body['parameters']['data']['form']);
        $storeId = (int)Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore()->getId();
        $this->assertSame($storeId, $body['parameters']['data']['store']);
        $this->assertGreaterThan(time(), $body['parameters']['expiresAt']);
    }

    /**
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/frontend_forms/contact 1
     */
    public function testMasterSwitchOffReturns404(): void
    {
        $this->getRequest()->setParams(['form' => 'contact']);
        $this->dispatch('altcha/challenge/index');
        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     */
    public function testUnknownFormReturns404(): void
    {
        $this->getRequest()->setParams(['form' => 'nope']);
        $this->dispatch('altcha/challenge/index');
        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/backend_forms/admin_login 1
     */
    public function testBackendFormIsNotServedByTheFrontendEndpoint(): void
    {
        $this->getRequest()->setParams(['form' => 'admin_login']);
        $this->dispatch('altcha/challenge/index');
        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/frontend_forms/contact 1
     */
    public function testArrayFormParamReturns404(): void
    {
        $this->getRequest()->setParams(['form' => ['contact']]);
        $this->dispatch('altcha/challenge/index');
        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
    }
}
