<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * Mints signed ALTCHA challenges, cryptographically bound to a form and store.
 *
 * @api
 */
interface ChallengeServiceInterface
{
    /**
     * Returns the challenge structure expected by the widget: {parameters: {...}, signature: "..."}.
     *
     * @return array<string, mixed>
     * @throws LocalizedException When no HMAC secret is configured.
     */
    public function create(string $formKey, int $storeId): array;
}
