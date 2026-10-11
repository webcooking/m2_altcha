<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Unit\Model;

use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Algorithm\ShaAlgorithm;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webcooking\Altcha\Api\Data\VerificationResultInterface;
use Webcooking\Altcha\Model\Config;
use Webcooking\Altcha\Model\ReplayGuard;
use Webcooking\Altcha\Model\SolutionVerifier;
use Webcooking\Altcha\Model\VerificationResult;
use Webcooking\Altcha\Model\VerificationResultFactory;

class SolutionVerifierTest extends TestCase
{
    private const string SECRET = 'bb0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    private function verifier(
        string $secret = self::SECRET,
        ?ReplayGuard $replayGuard = null,
        ?LockManagerInterface $lockManager = null
    ): SolutionVerifier {
        $config = $this->createStub(Config::class);
        $config->method('getSecret')->willReturn($secret);

        if ($replayGuard === null) {
            $replayGuard = $this->createStub(ReplayGuard::class);
            $replayGuard->method('isConsumed')->willReturn(false);
        }
        if ($lockManager === null) {
            $lockManager = $this->createStub(LockManagerInterface::class);
            $lockManager->method('lock')->willReturn(true);
            $lockManager->method('unlock')->willReturn(true);
        }

        $factory = $this->createStub(VerificationResultFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (array $data = []) => new VerificationResult(
                $data['valid'] ?? false,
                $data['errorCode'] ?? VerificationResultInterface::ERROR_NONE
            )
        );

        return new SolutionVerifier($config, $replayGuard, $lockManager, $factory);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>} base64 payload + decoded array
     */
    private function solvedPayload(
        string $form = 'contact',
        int $store = 1,
        int $expiresIn = 600,
        string $secret = self::SECRET
    ): array {
        $altcha = new Altcha(hmacSignatureSecret: $secret);
        $challenge = $altcha->createChallenge(new CreateChallengeOptions(
            algorithm: new Sha(),
            cost: 1,
            keyPrefix: '',
            expiresAt: time() + $expiresIn,
            data: ['form' => $form, 'store' => $store],
            keyPrefixLength: 1,
        ));
        $solution = $altcha->solveChallenge(new SolveChallengeOptions($challenge, new Sha()));
        $decoded = [
            'challenge' => $challenge->toArray(),
            'solution' => ['counter' => $solution->counter, 'derivedKey' => $solution->derivedKey, 'time' => 0],
        ];

        return [base64_encode(json_encode($decoded)), $decoded];
    }

    private static function encode(array $decoded): string
    {
        return base64_encode(json_encode($decoded));
    }

    public function testValidPayloadVerifiesAndConsumesReplayGuard(): void
    {
        [$payload] = $this->solvedPayload();
        $guard = $this->createMock(ReplayGuard::class);
        $guard->method('isConsumed')->willReturn(false);
        $guard->expects($this->once())->method('consume');

        $result = $this->verifier(replayGuard: $guard)->verify($payload, 'contact', 1);
        $this->assertTrue($result->isValid());
        $this->assertSame('', $result->getErrorCode());
    }

    public function testFormContextMismatchIsRejected(): void
    {
        [$payload] = $this->solvedPayload(form: 'newsletter');
        $result = $this->verifier()->verify($payload, 'customer_login', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_CONTEXT_MISMATCH, $result->getErrorCode());
    }

    public function testStoreContextMismatchIsRejected(): void
    {
        [$payload] = $this->solvedPayload(store: 2);
        $result = $this->verifier()->verify($payload, 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_CONTEXT_MISMATCH, $result->getErrorCode());
    }

    public function testTamperedContextIsRejectedBySignature(): void
    {
        [, $decoded] = $this->solvedPayload(form: 'newsletter');
        $decoded['challenge']['parameters']['data']['form'] = 'customer_login';
        $result = $this->verifier()->verify(self::encode($decoded), 'customer_login', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_INVALID_SIGNATURE, $result->getErrorCode());
    }

    public function testSplicedExpiryIsRejectedBySignature(): void
    {
        [, $decoded] = $this->solvedPayload(expiresIn: -10);
        $decoded['challenge']['parameters']['expiresAt'] = time() + 999999;
        $result = $this->verifier()->verify(self::encode($decoded), 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_INVALID_SIGNATURE, $result->getErrorCode());
    }

    public function testExpiredChallengeIsRejected(): void
    {
        [$payload] = $this->solvedPayload(expiresIn: -10);
        $result = $this->verifier()->verify($payload, 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_EXPIRED, $result->getErrorCode());
    }

    public function testMissingExpiryIsRejected(): void
    {
        $altcha = new Altcha(hmacSignatureSecret: self::SECRET);
        $challenge = $altcha->createChallenge(new CreateChallengeOptions(
            algorithm: new Sha(),
            cost: 1,
            keyPrefix: '',
            data: ['form' => 'contact', 'store' => 1],
            keyPrefixLength: 1,
        ));
        $solution = $altcha->solveChallenge(new SolveChallengeOptions($challenge, new Sha()));
        $payload = self::encode([
            'challenge' => $challenge->toArray(),
            'solution' => ['counter' => $solution->counter, 'derivedKey' => $solution->derivedKey, 'time' => 0],
        ]);

        $result = $this->verifier()->verify($payload, 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_INVALID_PAYLOAD, $result->getErrorCode());
    }

    public function testUnsupportedAlgorithmIsRejected(): void
    {
        $altcha = new Altcha(hmacSignatureSecret: self::SECRET);
        $challenge = $altcha->createChallenge(new CreateChallengeOptions(
            algorithm: new Sha(ShaAlgorithm::SHA512),
            cost: 1,
            keyPrefix: '',
            expiresAt: time() + 600,
            data: ['form' => 'contact', 'store' => 1],
            keyPrefixLength: 1,
        ));
        $solution = $altcha->solveChallenge(new SolveChallengeOptions($challenge, new Sha(ShaAlgorithm::SHA512)));
        $payload = self::encode([
            'challenge' => $challenge->toArray(),
            'solution' => ['counter' => $solution->counter, 'derivedKey' => $solution->derivedKey, 'time' => 0],
        ]);

        $result = $this->verifier()->verify($payload, 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_UNSUPPORTED_ALGORITHM, $result->getErrorCode());
    }

    public function testForeignSecretSignatureIsRejected(): void
    {
        [$payload] = $this->solvedPayload(
            secret: 'cc0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f'
        );
        $result = $this->verifier()->verify($payload, 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_INVALID_SIGNATURE, $result->getErrorCode());
    }

    public function testTamperedSolutionIsRejected(): void
    {
        [, $decoded] = $this->solvedPayload();
        $decoded['solution']['counter']++;
        $result = $this->verifier()->verify(self::encode($decoded), 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_INVALID_SOLUTION, $result->getErrorCode());
    }

    public function testReplayIsRejected(): void
    {
        [$payload] = $this->solvedPayload();
        $guard = $this->createMock(ReplayGuard::class);
        $guard->method('isConsumed')->willReturn(true);
        $guard->expects($this->never())->method('consume');

        $result = $this->verifier(replayGuard: $guard)->verify($payload, 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_REPLAY, $result->getErrorCode());
    }

    public function testLockContentionIsTreatedAsReplay(): void
    {
        [$payload] = $this->solvedPayload();
        $lock = $this->createStub(LockManagerInterface::class);
        $lock->method('lock')->willReturn(false);
        $guard = $this->createMock(ReplayGuard::class);
        $guard->expects($this->never())->method('consume');

        $result = $this->verifier(replayGuard: $guard, lockManager: $lock)->verify($payload, 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_REPLAY, $result->getErrorCode());
    }

    public function testLockInfrastructureFailureFallsBackToUnserializedPath(): void
    {
        [$payload] = $this->solvedPayload();
        $lock = $this->createStub(LockManagerInterface::class);
        $lock->method('lock')->willThrowException(new \RuntimeException('lock backend down'));

        $result = $this->verifier(lockManager: $lock)->verify($payload, 'contact', 1);
        $this->assertTrue($result->isValid());
    }

    public function testMissingSecretFailsWithNoSecret(): void
    {
        [$payload] = $this->solvedPayload();
        $result = $this->verifier(secret: '')->verify($payload, 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_NO_SECRET, $result->getErrorCode());
    }

    #[DataProvider('malformedPayloadProvider')]
    public function testMalformedPayloadsAreRejectedWithoutException(string $payload): void
    {
        $result = $this->verifier()->verify($payload, 'contact', 1);
        $this->assertFalse($result->isValid());
        $this->assertSame(VerificationResultInterface::ERROR_INVALID_PAYLOAD, $result->getErrorCode());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedPayloadProvider(): array
    {
        return [
            'empty' => [''],
            'not base64' => ['%%%not-base64%%%'],
            'not json' => [base64_encode('hello world')],
            'json scalar' => [base64_encode('"str"')],
            'missing challenge' => [base64_encode('{"solution":{"counter":1,"derivedKey":"ab"}}')],
            'missing solution' => [base64_encode('{"challenge":{"parameters":{},"signature":"ab"}}')],
            'counter not int' => [base64_encode(
                '{"challenge":{"parameters":{"algorithm":"SHA-256"},"signature":"ab"},'
                . '"solution":{"counter":"1","derivedKey":"ab"}}'
            )],
            'derivedKey not hex' => [base64_encode(
                '{"challenge":{"parameters":{"algorithm":"SHA-256"},"signature":"ab"},'
                . '"solution":{"counter":1,"derivedKey":"zz!!"}}'
            )],
            'oversized' => [str_repeat('A', 8193)],
            'deep json' => [base64_encode(str_repeat('{"a":', 20) . '1' . str_repeat('}', 20))],
        ];
    }
}
