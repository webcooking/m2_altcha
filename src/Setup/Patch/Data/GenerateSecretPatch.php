<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Setup\Patch\Data;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Webcooking\Altcha\Model\Config;

/**
 * Guarantees an HMAC secret exists before any challenge can be minted or
 * verified. The value is encrypted explicitly with the EncryptorInterface:
 * a direct config write bypasses the field's backend model and would
 * otherwise store the secret in plain text.
 */
class GenerateSecretPatch implements DataPatchInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly EncryptorInterface $encryptor,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    public function apply(): self
    {
        $existing = (string)$this->scopeConfig->getValue(Config::XML_PATH_SECRET);
        if ($existing === '') {
            $this->configWriter->save(
                Config::XML_PATH_SECRET,
                $this->encryptor->encrypt(bin2hex(random_bytes(32)))
            );
            $this->cacheTypeList->invalidate(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
