<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Model;

use Webcooking\Altcha\Api\Data\VerificationResultInterface;

class VerificationResult implements VerificationResultInterface
{
    public function __construct(
        private readonly bool $valid,
        private readonly string $errorCode = self::ERROR_NONE
    ) {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
