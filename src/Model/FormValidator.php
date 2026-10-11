<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Model;

use Magento\Framework\App\RequestInterface;
use Webcooking\Altcha\Api\Data\VerificationResultInterface;
use Webcooking\Altcha\Api\SolutionVerifierInterface;

class FormValidator
{
    public function __construct(
        private readonly Config $config,
        private readonly PayloadResolver $payloadResolver,
        private readonly SolutionVerifierInterface $solutionVerifier,
        private readonly VerificationResultFactory $resultFactory
    ) {
    }

    /**
     * Validates the ALTCHA payload of a submitted form. Returns a valid result
     * when protection is disabled for the form (nothing to enforce).
     */
    public function validate(string $formKey, RequestInterface $request, int $storeId): VerificationResultInterface
    {
        if (!$this->config->isEnabledForForm($formKey, $storeId)) {
            return $this->resultFactory->create(['valid' => true]);
        }

        return $this->solutionVerifier->verify(
            $this->payloadResolver->resolve($request),
            $formKey,
            $storeId
        );
    }
}
