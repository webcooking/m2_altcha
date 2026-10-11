<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Integration\Observer;

use Magento\Framework\Event\ConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Webcooking\Altcha\Observer\AdminLoginObserver;
use Webcooking\Altcha\Observer\ValidateOnPredispatch;

/**
 * Deterministic per-form wiring proof for all eight protected forms: each event
 * must resolve, in the right area, to the right observer instance carrying the
 * right form key. Catches the silent-failure class where a typo in an event
 * name or a virtual type's form key would leave a form unprotected — including
 * admin forgot-password, which is awkward to exercise over pre-auth HTTP.
 */
class ObserverWiringTest extends TestCase
{
    private function assertEventObserver(string $event, string $observerName, string $expectedInstance): void
    {
        $config = Bootstrap::getObjectManager()->get(ConfigInterface::class);
        $observers = $config->getObservers($event);

        $this->assertArrayHasKey($observerName, $observers, "Event $event must register $observerName");
        $this->assertSame($expectedInstance, ltrim((string)$observers[$observerName]['instance'], '\\'));
        $this->assertNotSame('true', (string)($observers[$observerName]['disabled'] ?? 'false'));
    }

    private function assertVirtualTypeFormKey(string $virtualType, string $expectedFormKey): void
    {
        $instance = Bootstrap::getObjectManager()->get($virtualType);
        $this->assertInstanceOf(ValidateOnPredispatch::class, $instance);

        $property = new \ReflectionProperty(ValidateOnPredispatch::class, 'formKey');
        $this->assertSame($expectedFormKey, $property->getValue($instance));
    }

    /**
     * @magentoAppArea frontend
     */
    public function testFrontendFormsAreWired(): void
    {
        $map = [
            // event, observer name, virtual type, form key
            ['controller_action_predispatch_customer_account_loginPost', 'webcooking_altcha_customer_login', \Webcooking\Altcha\Observer\Virtual\ValidateCustomerLogin::class, 'customer_login'],
            ['controller_action_predispatch_customer_account_createpost', 'webcooking_altcha_customer_create', \Webcooking\Altcha\Observer\Virtual\ValidateCustomerCreate::class, 'customer_create'],
            ['controller_action_predispatch_customer_account_forgotpasswordpost', 'webcooking_altcha_customer_forgotpassword', \Webcooking\Altcha\Observer\Virtual\ValidateCustomerForgotPassword::class, 'customer_forgotpassword'],
            ['controller_action_predispatch_contact_index_post', 'webcooking_altcha_contact', \Webcooking\Altcha\Observer\Virtual\ValidateContact::class, 'contact'],
            ['controller_action_predispatch_newsletter_subscriber_new', 'webcooking_altcha_newsletter', \Webcooking\Altcha\Observer\Virtual\ValidateNewsletter::class, 'newsletter'],
            ['controller_action_predispatch_review_product_post', 'webcooking_altcha_product_review', \Webcooking\Altcha\Observer\Virtual\ValidateProductReview::class, 'product_review'],
        ];
        foreach ($map as [$event, $observerName, $virtualType, $formKey]) {
            $this->assertEventObserver($event, $observerName, $virtualType);
            $this->assertVirtualTypeFormKey($virtualType, $formKey);
        }
    }

    /**
     * @magentoAppArea adminhtml
     */
    public function testAdminFormsAreWired(): void
    {
        $this->assertEventObserver(
            'admin_user_authenticate_before',
            'webcooking_altcha_admin_login',
            AdminLoginObserver::class
        );
        $this->assertSame('admin_login', AdminLoginObserver::FORM_KEY);

        $this->assertEventObserver(
            'controller_action_predispatch_adminhtml_auth_forgotpassword',
            'webcooking_altcha_admin_forgotpassword',
            \Webcooking\Altcha\Observer\Virtual\ValidateAdminForgotPassword::class
        );
        $this->assertVirtualTypeFormKey(
            \Webcooking\Altcha\Observer\Virtual\ValidateAdminForgotPassword::class,
            'admin_forgotpassword'
        );
    }
}
