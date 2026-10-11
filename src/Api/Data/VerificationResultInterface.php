<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Api\Data;

/**
 * Outcome of an ALTCHA payload verification.
 *
 * @api
 */
interface VerificationResultInterface
{
    public const string ERROR_NONE = '';
    public const string ERROR_INVALID_PAYLOAD = 'INVALID_PAYLOAD';
    public const string ERROR_UNSUPPORTED_ALGORITHM = 'UNSUPPORTED_ALGORITHM';
    public const string ERROR_INVALID_SIGNATURE = 'INVALID_SIGNATURE';
    public const string ERROR_INVALID_SOLUTION = 'INVALID_SOLUTION';
    public const string ERROR_EXPIRED = 'EXPIRED';
    public const string ERROR_CONTEXT_MISMATCH = 'CONTEXT_MISMATCH';
    public const string ERROR_REPLAY = 'REPLAY';
    public const string ERROR_NO_SECRET = 'NO_SECRET';

    public function isValid(): bool;

    public function getErrorCode(): string;
}
