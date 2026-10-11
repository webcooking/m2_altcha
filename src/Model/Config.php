<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const string XML_PATH_ENABLED = 'altcha/general/enabled';
    public const string XML_PATH_SECRET = 'altcha/general/secret';
    public const string XML_PATH_DIFFICULTY = 'altcha/general/difficulty';
    public const string XML_PATH_EXPIRY = 'altcha/general/expiry';
    public const string XML_PATH_HIDE_FOOTER = 'altcha/appearance/hidefooter';
    public const string XML_PATH_HIDE_LOGO = 'altcha/appearance/hidelogo';
    public const string XML_PATH_THEME = 'altcha/appearance/theme';

    public const string GROUP_FRONTEND_FORMS = 'frontend_forms';
    public const string GROUP_BACKEND_FORMS = 'backend_forms';

    public const string FIELD_NAME = 'altcha';

    private const int EXPIRY_MIN = 60;
    private const int EXPIRY_MAX = 3600;
    private const int EXPIRY_DEFAULT = 600;

    /**
     * @param array<string, string> $forms Form key => system.xml group (see etc/di.xml)
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly array $forms = []
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * HMAC secret; empty string when not configured. Global (default scope) so
     * challenges verify across stores and admin. Decryption is done by the
     * config metadata processor via the backend_model declared in etc/config.xml
     * — do not decrypt again here.
     */
    public function getSecret(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_SECRET);
    }

    public function getDifficulty(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_DIFFICULTY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getExpiry(?int $storeId = null): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_EXPIRY, ScopeInterface::SCOPE_STORE, $storeId);
        if ($value < self::EXPIRY_MIN || $value > self::EXPIRY_MAX) {
            return self::EXPIRY_DEFAULT;
        }

        return $value;
    }

    public function getFieldName(): string
    {
        return self::FIELD_NAME;
    }

    /**
     * @return array{hideFooter: bool, hideLogo: bool, theme: string}
     */
    public function getAppearance(?int $storeId = null): array
    {
        return [
            'hideFooter' => $this->scopeConfig->isSetFlag(self::XML_PATH_HIDE_FOOTER, ScopeInterface::SCOPE_STORE, $storeId),
            'hideLogo' => $this->scopeConfig->isSetFlag(self::XML_PATH_HIDE_LOGO, ScopeInterface::SCOPE_STORE, $storeId),
            'theme' => (string)$this->scopeConfig->getValue(self::XML_PATH_THEME, ScopeInterface::SCOPE_STORE, $storeId),
        ];
    }

    public function isKnownForm(string $formKey): bool
    {
        return isset($this->forms[$formKey]);
    }

    public function isBackendForm(string $formKey): bool
    {
        return ($this->forms[$formKey] ?? '') === self::GROUP_BACKEND_FORMS;
    }

    /**
     * Single gate used by rendering and validation: master switch on, per-form
     * flag on, and a secret available. Backend form flags are global; storefront
     * flags are store-scoped.
     */
    public function isEnabledForForm(string $formKey, ?int $storeId = null): bool
    {
        $group = $this->forms[$formKey] ?? null;
        if ($group === null) {
            return false;
        }

        $path = sprintf('altcha/%s/%s', $group, $formKey);
        if ($group === self::GROUP_BACKEND_FORMS) {
            $flagEnabled = $this->scopeConfig->isSetFlag($path);
            $enabled = $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
        } else {
            $flagEnabled = $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
            $enabled = $this->isEnabled($storeId);
        }

        return $enabled && $flagEnabled && $this->getSecret() !== '';
    }
}
