<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Controller\Challenge;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Webcooking\Altcha\Api\ChallengeServiceInterface;
use Webcooking\Altcha\Model\Config;
use Webcooking\Altcha\Model\RateLimiter;

/**
 * Public GET endpoint returning a fresh signed challenge for a given form.
 * Read-only (mints a token, changes no state) so it is exempt from form-key
 * CSRF validation; responses are never cacheable.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly ChallengeServiceInterface $challengeService,
        private readonly RateLimiter $rateLimiter,
        private readonly RemoteAddress $remoteAddress,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, max-age=0', true);

        $storeId = (int)$this->storeManager->getStore()->getId();
        $formParam = $this->request->getParam('form');
        $formKey = is_string($formParam) ? $formParam : '';

        if (!$this->config->isKnownForm($formKey)
            || $this->config->isBackendForm($formKey)
            || !$this->config->isEnabledForForm($formKey, $storeId)
        ) {
            $result->setHttpResponseCode(404);

            return $result->setData(['error' => 'Unknown or disabled form.']);
        }

        if (!$this->rateLimiter->isAllowed((string)$this->remoteAddress->getRemoteAddress())) {
            $result->setHttpResponseCode(429);

            return $result->setData(['error' => 'Too many requests.']);
        }

        try {
            return $result->setData($this->challengeService->create($formKey, $storeId));
        } catch (\Throwable $e) {
            // Keep the endpoint contract uniform: JSON 503, never an HTML error page.
            $this->logger->error('ALTCHA challenge endpoint: ' . $e->getMessage());
            $result->setHttpResponseCode(503);

            return $result->setData(['error' => 'Challenge service unavailable.']);
        }
    }
}
