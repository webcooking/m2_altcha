<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Integration;

use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeParameters;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Magento\TestFramework\Helper\Bootstrap;
use Webcooking\Altcha\Api\ChallengeServiceInterface;

/**
 * Mints a real challenge through the module's service and solves it with the
 * official library — the payload a genuine widget would post.
 */
trait ChallengeSolverTrait
{
    private function solvedAltchaPayload(string $formKey, int $storeId): string
    {
        $objectManager = Bootstrap::getObjectManager();
        $challengeData = $objectManager->get(ChallengeServiceInterface::class)->create($formKey, $storeId);

        $secret = $objectManager->get(\Webcooking\Altcha\Model\Config::class)->getSecret();
        $altcha = new Altcha(hmacSignatureSecret: $secret);
        $solution = $altcha->solveChallenge(new SolveChallengeOptions(
            new Challenge(
                ChallengeParameters::fromArray($challengeData['parameters']),
                $challengeData['signature'] ?? null
            ),
            new Sha()
        ));
        if ($solution === null) {
            self::fail('Unable to solve the minted challenge.');
        }

        return base64_encode(json_encode([
            'challenge' => $challengeData,
            'solution' => ['counter' => $solution->counter, 'derivedKey' => $solution->derivedKey, 'time' => 0],
        ], JSON_THROW_ON_ERROR));
    }
}
