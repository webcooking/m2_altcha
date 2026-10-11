<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Test\Integration\Setup;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Webcooking\Altcha\Model\Config;

/**
 * Proves the secret lifecycle end-to-end: the data patch stores an ENCRYPTED
 * value in core_config_data, and Config::getSecret() receives the PLAINTEXT
 * through Magento's config metadata processor (backend_model declared in
 * etc/config.xml) — no manual decryption in the module.
 */
class GenerateSecretPatchTest extends TestCase
{
    public function testSecretIsStoredEncryptedAndReadDecrypted(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $connection = $objectManager->get(ResourceConnection::class)->getConnection();
        $select = $connection->select()
            ->from($connection->getTableName('core_config_data'), ['value'])
            ->where('path = ?', Config::XML_PATH_SECRET)
            ->where('scope = ?', 'default');
        $rawStoredValue = (string)$connection->fetchOne($select);
        $this->assertNotSame('', $rawStoredValue, 'The data patch must have written a secret.');

        $plaintext = $objectManager->get(Config::class)->getSecret();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            $plaintext,
            'getSecret() must return the generated 64-hex plaintext.'
        );

        $this->assertNotSame($plaintext, $rawStoredValue, 'The stored value must not be plaintext.');
        $this->assertSame(
            $plaintext,
            (string)$objectManager->get(EncryptorInterface::class)->decrypt($rawStoredValue),
            'The stored value must be the encrypted form of what getSecret() returns.'
        );
    }
}
