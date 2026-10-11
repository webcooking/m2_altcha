<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Integration\Observer;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Message\MessageInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;
use Webcooking\Altcha\Test\Integration\ChallengeSolverTrait;

/**
 * @magentoDbIsolation enabled
 */
class ContactFormProtectionTest extends AbstractController
{
    use ChallengeSolverTrait;

    private function postContact(?string $altchaPayload): void
    {
        $post = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'comment' => 'Integration test message, long enough to be valid.',
            'hideit' => '',
            'form_key' => Bootstrap::getObjectManager()->get(FormKey::class)->getFormKey(),
        ];
        if ($altchaPayload !== null) {
            $post['altcha'] = $altchaPayload;
        }
        $this->getRequest()->setMethod('POST')->setPostValue($post);
        $this->dispatch('contact/index/post');
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/general/difficulty easy
     * @magentoConfigFixture default/altcha/frontend_forms/contact 1
     */
    public function testMissingPayloadIsBlockedWithMessageAndRedirect(): void
    {
        $this->postContact(null);

        $this->assertRedirect($this->stringContains('contact/index'));
        $this->assertSessionMessages(
            $this->equalTo(['CAPTCHA verification failed. Please try again.']),
            MessageInterface::TYPE_ERROR
        );
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/general/difficulty easy
     * @magentoConfigFixture default/altcha/frontend_forms/contact 1
     */
    public function testSolvedPayloadPasses(): void
    {
        $storeId = (int)Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore()->getId();
        $this->postContact($this->solvedAltchaPayload('contact', $storeId));

        $this->assertSessionMessages($this->isEmpty(), MessageInterface::TYPE_ERROR);
        $this->assertRedirect($this->stringContains('contact/index'));
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/general/difficulty easy
     * @magentoConfigFixture default/altcha/frontend_forms/contact 1
     */
    public function testReplayedPayloadIsBlocked(): void
    {
        $storeId = (int)Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore()->getId();
        $payload = $this->solvedAltchaPayload('contact', $storeId);

        $this->postContact($payload);
        $this->assertSessionMessages($this->isEmpty(), MessageInterface::TYPE_ERROR);

        // Second submission of the exact same payload must be rejected.
        $this->resetRequest();
        $this->postContact($payload);
        $this->assertSessionMessages(
            $this->equalTo(['CAPTCHA verification failed. Please try again.']),
            MessageInterface::TYPE_ERROR
        );
    }

    /**
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     */
    public function testDisabledProtectionDoesNotInterfere(): void
    {
        $this->postContact(null);

        $this->assertSessionMessages($this->isEmpty(), MessageInterface::TYPE_ERROR);
        $this->assertRedirect($this->stringContains('contact/index'));
    }
}
