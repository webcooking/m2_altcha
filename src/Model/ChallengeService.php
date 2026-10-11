<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Model;

use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use Magento\Framework\Exception\LocalizedException;
use Webcooking\Altcha\Api\ChallengeServiceInterface;

class ChallengeService implements ChallengeServiceInterface
{
    public const string ALGORITHM = 'SHA-256';

    public const string DATA_KEY_FORM = 'form';
    public const string DATA_KEY_STORE = 'store';

    /**
     * Proof-of-work presets: keyPrefixLength bytes to brute-force (avg 256^n
     * attempts), each attempt costing `cost` chained hashes. Calibrated for
     * the vendored SHA-256 worker; re-measure on low-end mobile when changing.
     *
     * @var array<string, array{cost: int, prefix_length: int}>
     */
    private const array DIFFICULTY_PRESETS = [
        'easy' => ['cost' => 1, 'prefix_length' => 2],
        'balanced' => ['cost' => 4, 'prefix_length' => 2],
        'hard' => ['cost' => 10, 'prefix_length' => 2],
    ];

    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(string $formKey, int $storeId): array
    {
        $secret = $this->config->getSecret();
        if ($secret === '') {
            throw new LocalizedException(__('ALTCHA HMAC secret is not configured.'));
        }

        $preset = self::DIFFICULTY_PRESETS[$this->config->getDifficulty($storeId)]
            ?? self::DIFFICULTY_PRESETS['balanced'];

        $altcha = new Altcha(hmacSignatureSecret: $secret);
        $challenge = $altcha->createChallenge(new CreateChallengeOptions(
            algorithm: new Sha(),
            cost: $preset['cost'],
            keyPrefix: '',
            expiresAt: time() + $this->config->getExpiry($storeId),
            data: [
                self::DATA_KEY_FORM => $formKey,
                self::DATA_KEY_STORE => $storeId,
            ],
            keyPrefixLength: $preset['prefix_length'],
        ));

        return $challenge->toArray();
    }
}
