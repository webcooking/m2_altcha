<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Api;

use Webcooking\Altcha\Api\Data\VerificationResultInterface;

/**
 * Verifies an ALTCHA payload submitted by the widget (base64 JSON), including
 * signature, expiry, form/store context binding and replay protection.
 *
 * @api
 */
interface SolutionVerifierInterface
{
    public function verify(string $payload, string $formKey, int $storeId): VerificationResultInterface;
}
