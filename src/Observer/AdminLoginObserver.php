<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\Plugin\AuthenticationException;
use Psr\Log\LoggerInterface;
use Webcooking\Altcha\Api\Data\VerificationResultInterface;
use Webcooking\Altcha\Model\FormValidator;

/**
 * Blocks admin authentication when the ALTCHA payload is missing or invalid.
 * Fires on admin_user_authenticate_before, which only carries username/user:
 * the payload is read from the injected HTTP request. Admin challenges are
 * bound to store 0.
 */
class AdminLoginObserver implements ObserverInterface
{
    public const string FORM_KEY = 'admin_login';
    public const int ADMIN_STORE_ID = 0;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly FormValidator $formValidator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws AuthenticationException
     */
    public function execute(Observer $observer): void
    {
        $result = $this->formValidator->validate(self::FORM_KEY, $this->request, self::ADMIN_STORE_ID);

        if ($result->isValid()) {
            return;
        }

        if ($result->getErrorCode() === VerificationResultInterface::ERROR_NO_SECRET) {
            // Fail-open: a configuration problem must never lock admins out.
            $this->logger->error('ALTCHA: no HMAC secret configured, skipping admin login validation.');

            return;
        }

        $this->logger->info(sprintf('ALTCHA: blocked admin login (%s).', $result->getErrorCode()));

        throw new AuthenticationException(
            __('Incorrect CAPTCHA. Please try again.')
        );
    }
}
