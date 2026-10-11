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
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeParameters;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\Solution;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Magento\Framework\Lock\LockManagerInterface;
use Webcooking\Altcha\Api\Data\VerificationResultInterface;
use Webcooking\Altcha\Api\SolutionVerifierInterface;

class SolutionVerifier implements SolutionVerifierInterface
{
    private const int MAX_PAYLOAD_LENGTH = 8192;
    private const int MAX_JSON_DEPTH = 8;

    /** Short lock name: backends may prepend a prefix within a 64-char limit. */
    private const string REPLAY_LOCK_PREFIX = 'ALTCHA_';
    private const int REPLAY_LOCK_TIMEOUT = 2;

    public function __construct(
        private readonly Config $config,
        private readonly ReplayGuard $replayGuard,
        private readonly LockManagerInterface $lockManager,
        private readonly VerificationResultFactory $resultFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function verify(string $payload, string $formKey, int $storeId): VerificationResultInterface
    {
        $secret = $this->config->getSecret();
        if ($secret === '') {
            return $this->failure(VerificationResultInterface::ERROR_NO_SECRET);
        }

        try {
            $parsed = $this->parsePayload($payload);
            if ($parsed === null) {
                return $this->failure(VerificationResultInterface::ERROR_INVALID_PAYLOAD);
            }
            [$parameters, $signature, $solution] = $parsed;

            if ($parameters->algorithm !== ChallengeService::ALGORITHM) {
                return $this->failure(VerificationResultInterface::ERROR_UNSUPPORTED_ALGORITHM);
            }
            if ($parameters->expiresAt === null) {
                // Challenges minted by this module always carry an expiry.
                return $this->failure(VerificationResultInterface::ERROR_INVALID_PAYLOAD);
            }

            $altcha = new Altcha(hmacSignatureSecret: $secret);
            $result = $altcha->verifySolution(new VerifySolutionOptions(
                new Payload(new Challenge($parameters, $signature), $solution),
                new Sha()
            ));
        } catch (\Throwable) {
            // Attacker-shaped parameter types can make the library throw.
            return $this->failure(VerificationResultInterface::ERROR_INVALID_PAYLOAD);
        }

        if (!$result->verified) {
            if ($result->expired) {
                return $this->failure(VerificationResultInterface::ERROR_EXPIRED);
            }
            if ($result->invalidSignature) {
                return $this->failure(VerificationResultInterface::ERROR_INVALID_SIGNATURE);
            }

            return $this->failure(VerificationResultInterface::ERROR_INVALID_SOLUTION);
        }

        // The signature is verified at this point, so the bound context is authentic.
        $data = $parameters->data ?? [];
        $boundForm = $data[ChallengeService::DATA_KEY_FORM] ?? null;
        $boundStore = $data[ChallengeService::DATA_KEY_STORE] ?? null;
        if ($boundForm !== $formKey || !is_int($boundStore) || $boundStore !== $storeId) {
            return $this->failure(VerificationResultInterface::ERROR_CONTEXT_MISMATCH);
        }

        return $this->consumeOnce($signature, $parameters->expiresAt - time());
    }

    /**
     * Serializes the check-then-consume of a signature behind a shared lock so
     * two concurrent submissions of the same payload cannot both pass. If the
     * lock backend is unavailable, falls back to the unserialized check
     * (replay protection stays defense in depth over signature + expiry).
     */
    private function consumeOnce(string $signature, int $ttl): VerificationResultInterface
    {
        $lockName = self::REPLAY_LOCK_PREFIX . substr(hash('sha256', $signature), 0, 32);
        $locked = null;
        try {
            $locked = $this->lockManager->lock($lockName, self::REPLAY_LOCK_TIMEOUT);
        } catch (\Throwable) {
            // Lock infrastructure failure: proceed with the unserialized path.
        }

        if ($locked === false) {
            // The only possible holder is a concurrent verification of the
            // exact same payload: one of the two is a replay by definition.
            return $this->failure(VerificationResultInterface::ERROR_REPLAY);
        }

        try {
            if ($this->replayGuard->isConsumed($signature)) {
                return $this->failure(VerificationResultInterface::ERROR_REPLAY);
            }
            $this->replayGuard->consume($signature, $ttl);
        } finally {
            if ($locked === true) {
                try {
                    $this->lockManager->unlock($lockName);
                } catch (\Throwable) {
                    // Held locks expire with the connection; nothing to do.
                }
            }
        }

        return $this->resultFactory->create(['valid' => true]);
    }

    /**
     * @return array{0: ChallengeParameters, 1: string, 2: Solution}|null
     */
    private function parsePayload(string $payload): ?array
    {
        if ($payload === '' || strlen($payload) > self::MAX_PAYLOAD_LENGTH) {
            return null;
        }

        $json = base64_decode($payload, true);
        if ($json === false) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $parametersData = $decoded['challenge']['parameters'] ?? null;
        $signature = $decoded['challenge']['signature'] ?? null;
        $counter = $decoded['solution']['counter'] ?? null;
        $derivedKey = $decoded['solution']['derivedKey'] ?? null;

        if (!is_array($parametersData)
            || !is_string($signature) || $signature === ''
            || !is_int($counter) || $counter < 0
            || !is_string($derivedKey) || $derivedKey === '' || !ctype_xdigit($derivedKey)
        ) {
            return null;
        }

        return [
            ChallengeParameters::fromArray($parametersData),
            $signature,
            new Solution($counter, strtolower($derivedKey), 0.0),
        ];
    }

    private function failure(string $errorCode): VerificationResultInterface
    {
        return $this->resultFactory->create(['valid' => false, 'errorCode' => $errorCode]);
    }
}
