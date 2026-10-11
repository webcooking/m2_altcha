<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Observer;

use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Webcooking\Altcha\Api\Data\VerificationResultInterface;
use Webcooking\Altcha\Model\Config;
use Webcooking\Altcha\Model\FormValidator;

/**
 * Generic CAPTCHA gate for standard POST actions, configured per form through
 * DI virtual types (see etc/di.xml). On failure: error message, no dispatch,
 * and a safe redirect — either a fixed internal route, or the framework's
 * referer resolution which falls back to the base URL for external referers.
 */
class ValidateOnPredispatch implements ObserverInterface
{
    public function __construct(
        private readonly FormValidator $formValidator,
        private readonly Config $config,
        private readonly ActionFlag $actionFlag,
        private readonly ManagerInterface $messageManager,
        private readonly RedirectInterface $redirect,
        private readonly UrlInterface $url,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly string $formKey,
        private readonly ?string $redirectRoute = null,
        private readonly ?string $requiredParam = null
    ) {
    }

    public function execute(Observer $observer): void
    {
        $controller = $observer->getControllerAction();
        if (!$controller instanceof ActionInterface) {
            return;
        }
        $request = $controller->getRequest();

        if ($this->requiredParam !== null && $request->getParam($this->requiredParam) === null) {
            return;
        }

        // Backend forms bind to store 0 — must match the store id the widget minted with.
        $storeId = $this->config->isBackendForm($this->formKey)
            ? 0
            : (int)$this->storeManager->getStore()->getId();
        $result = $this->formValidator->validate($this->formKey, $request, $storeId);

        if ($result->isValid()) {
            return;
        }

        if ($result->getErrorCode() === VerificationResultInterface::ERROR_NO_SECRET) {
            // Fail-open: a missing secret must never lock legitimate visitors out.
            $this->logger->error(sprintf(
                'ALTCHA: no HMAC secret configured, skipping validation for form "%s".',
                $this->formKey
            ));

            return;
        }

        $this->logger->info(sprintf(
            'ALTCHA: blocked submission for form "%s" (%s).',
            $this->formKey,
            $result->getErrorCode()
        ));

        $this->messageManager->addErrorMessage(
            (string)__('CAPTCHA verification failed. Please try again.')
        );
        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);

        $redirectUrl = $this->redirectRoute !== null
            ? $this->url->getUrl($this->redirectRoute)
            : $this->redirect->getRedirectUrl();
        $controller->getResponse()->setRedirect($redirectUrl);
    }
}
