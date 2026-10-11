<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Unit\Model;

use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeParameters;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Webcooking\Altcha\Model\ChallengeService;
use Webcooking\Altcha\Model\Config;

class ChallengeServiceTest extends TestCase
{
    private const string SECRET = 'aa0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    private function service(string $secret = self::SECRET, string $difficulty = 'easy', int $expiry = 600): ChallengeService
    {
        $config = $this->createStub(Config::class);
        $config->method('getSecret')->willReturn($secret);
        $config->method('getDifficulty')->willReturn($difficulty);
        $config->method('getExpiry')->willReturn($expiry);

        return new ChallengeService($config);
    }

    public function testCreateReturnsWidgetChallengeStructure(): void
    {
        $before = time();
        $challenge = $this->service()->create('contact', 3);

        $this->assertArrayHasKey('parameters', $challenge);
        $this->assertArrayHasKey('signature', $challenge);
        $this->assertSame('SHA-256', $challenge['parameters']['algorithm']);
        $this->assertSame('contact', $challenge['parameters']['data']['form']);
        $this->assertSame(3, $challenge['parameters']['data']['store']);
        $this->assertGreaterThanOrEqual($before + 598, $challenge['parameters']['expiresAt']);
        $this->assertLessThanOrEqual(time() + 600, $challenge['parameters']['expiresAt']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string)$challenge['signature']);
        $this->assertNotSame('', $challenge['parameters']['salt']);
        $this->assertNotSame('', $challenge['parameters']['nonce']);
    }

    public function testMintedChallengeIsSolvableAndVerifiable(): void
    {
        $challengeData = $this->service()->create('newsletter', 1);

        $altcha = new Altcha(hmacSignatureSecret: self::SECRET);
        $challenge = new Challenge(
            ChallengeParameters::fromArray($challengeData['parameters']),
            $challengeData['signature']
        );
        $solution = $altcha->solveChallenge(new SolveChallengeOptions($challenge, new Sha()));
        $this->assertNotNull($solution, 'minted challenge must be solvable');

        $result = $altcha->verifySolution(new VerifySolutionOptions(
            new Payload($challenge, $solution),
            new Sha()
        ));
        $this->assertTrue($result->verified);
    }

    public function testUnknownDifficultyFallsBackToBalanced(): void
    {
        $challenge = $this->service(difficulty: 'bogus')->create('contact', 1);
        // balanced preset uses cost=4
        $this->assertSame(4, $challenge['parameters']['cost']);
    }

    public function testMissingSecretThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->service(secret: '')->create('contact', 1);
    }
}
