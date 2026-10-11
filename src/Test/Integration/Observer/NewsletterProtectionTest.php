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
class NewsletterProtectionTest extends AbstractController
{
    use ChallengeSolverTrait;

    private function postSubscription(?string $altchaPayload): void
    {
        $post = [
            'email' => 'subscriber@example.com',
            'form_key' => Bootstrap::getObjectManager()->get(FormKey::class)->getFormKey(),
        ];
        if ($altchaPayload !== null) {
            $post['altcha'] = $altchaPayload;
        }
        $this->getRequest()->setMethod('POST')->setPostValue($post);
        $this->dispatch('newsletter/subscriber/new');
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/general/difficulty easy
     * @magentoConfigFixture default/altcha/frontend_forms/newsletter 1
     */
    public function testMissingPayloadIsBlocked(): void
    {
        $this->postSubscription(null);

        $this->assertSessionMessages(
            $this->equalTo(['CAPTCHA verification failed. Please try again.']),
            MessageInterface::TYPE_ERROR
        );
    }

    /**
     * @magentoConfigFixture default/altcha/general/enabled 1
     * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
     * @magentoConfigFixture default/altcha/general/difficulty easy
     * @magentoConfigFixture default/altcha/frontend_forms/newsletter 1
     */
    public function testSolvedPayloadSubscribes(): void
    {
        $storeId = (int)Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore()->getId();
        $this->postSubscription($this->solvedAltchaPayload('newsletter', $storeId));

        $this->assertSessionMessages($this->isEmpty(), MessageInterface::TYPE_ERROR);
        $this->assertSessionMessages($this->logicalNot($this->isEmpty()), MessageInterface::TYPE_SUCCESS);
    }
}
