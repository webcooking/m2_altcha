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
 * Per-form wiring coverage for the generic ValidateOnPredispatch observer:
 * every event name must reach its virtual type with the right form key.
 * The predispatch observer runs before the target action's business logic, so
 * a missing payload is blocked regardless of the rest of the request being
 * otherwise valid — no credentials/product fixtures needed to prove the wiring.
 *
 * @magentoDbIsolation enabled
 * @magentoConfigFixture default/altcha/general/enabled 1
 * @magentoConfigFixture default/altcha/general/secret aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f
 * @magentoConfigFixture default/altcha/general/difficulty easy
 * @magentoConfigFixture default/altcha/frontend_forms/customer_login 1
 * @magentoConfigFixture default/altcha/frontend_forms/customer_create 1
 * @magentoConfigFixture default/altcha/frontend_forms/customer_forgotpassword 1
 * @magentoConfigFixture default/altcha/frontend_forms/product_review 1
 */
class PredispatchFormsProtectionTest extends AbstractController
{
    use ChallengeSolverTrait;

    private const string CAPTCHA_ERROR = 'CAPTCHA verification failed. Please try again.';

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function formProvider(): array
    {
        return [
            'customer login' => ['customer_login', 'customer/account/loginPost', [
                'login' => ['username' => 'nobody@example.com', 'password' => 'wrong-password-123'],
            ]],
            'customer create' => ['customer_create', 'customer/account/createpost', [
                'firstname' => 'John', 'lastname' => 'Doe',
                'email' => 'newcustomer@example.com', 'password' => 'Password-123', 'password_confirmation' => 'Password-123',
            ]],
            'customer forgot password' => ['customer_forgotpassword', 'customer/account/forgotpasswordpost', [
                'email' => 'nobody@example.com',
            ]],
            'product review' => ['product_review', 'review/product/post', [
                'nickname' => 'Rev', 'title' => 'Title', 'detail' => 'A detailed enough review body.',
            ]],
        ];
    }

    private function post(string $route, array $data, ?string $altchaPayload): void
    {
        $data['form_key'] = Bootstrap::getObjectManager()->get(FormKey::class)->getFormKey();
        if ($altchaPayload !== null) {
            $data['altcha'] = $altchaPayload;
        }
        $this->getRequest()->setMethod('POST')->setPostValue($data);
        $this->dispatch($route);
    }

    /**
     * @dataProvider formProvider
     */
    public function testFormIsBlockedWhenPayloadIsMissing(string $formKey, string $route, array $data): void
    {
        $this->post($route, $data, null);

        $this->assertSessionMessages(
            $this->contains(self::CAPTCHA_ERROR),
            MessageInterface::TYPE_ERROR
        );
    }

    /**
     * @dataProvider formProvider
     */
    public function testFormPassesCaptchaGateWhenPayloadIsSolved(string $formKey, string $route, array $data): void
    {
        $storeId = (int)Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore()->getId();
        $this->post($route, $data, $this->solvedAltchaPayload($formKey, $storeId));

        // The CAPTCHA gate must let the request through; the underlying action
        // may still fail for unrelated reasons, but not with the CAPTCHA error.
        $this->assertSessionMessages(
            $this->logicalNot($this->contains(self::CAPTCHA_ERROR)),
            MessageInterface::TYPE_ERROR
        );
    }
}
