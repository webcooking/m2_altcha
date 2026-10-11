<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Integration\Layout;

use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoDbIsolation enabled
 */
class WidgetInjectionTest extends AbstractController
{
    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/frontend_forms/customer_login 1
     */
    public function testWidgetIsRenderedOnCustomerLoginWhenEnabled(): void
    {
        $this->dispatch('customer/account/login');
        $body = $this->getResponse()->getBody();

        $this->assertStringContainsString('<altcha-widget', $body);
        $this->assertStringContainsString('altcha/challenge', $body);
        $this->assertStringContainsString('name="altcha"', $body);
        $this->assertStringContainsString('altcha-loader.min.js', $body);
    }

    /**
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/frontend_forms/customer_login 1
     */
    public function testWidgetIsAbsentWhenMasterSwitchIsOff(): void
    {
        $this->dispatch('customer/account/login');
        $this->assertStringNotContainsString('<altcha-widget', $this->getResponse()->getBody());
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     */
    public function testWidgetIsAbsentWhenFormFlagIsOff(): void
    {
        $this->dispatch('customer/account/login');
        $this->assertStringNotContainsString('<altcha-widget', $this->getResponse()->getBody());
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/frontend_forms/newsletter 1
     */
    public function testNewsletterWidgetIsRenderedInsideSubscribeFormOnHomePage(): void
    {
        $this->dispatch('cms/index/index');
        $body = $this->getResponse()->getBody();

        $this->assertStringContainsString('<altcha-widget', $body);
        // The widget must be INSIDE the subscribe form so its hidden input submits with it.
        $formPos = strpos($body, 'id="newsletter-validate-detail"');
        $widgetPos = strpos($body, '<altcha-widget');
        $this->assertNotFalse($formPos);
        $this->assertNotFalse($widgetPos);
        $formEnd = strpos($body, '</form>', $formPos);
        $this->assertGreaterThan($formPos, $widgetPos);
        $this->assertLessThan($formEnd, $widgetPos);
    }
}
