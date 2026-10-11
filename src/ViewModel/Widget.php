<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\ViewModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Webcooking\Altcha\Api\ChallengeServiceInterface;
use Webcooking\Altcha\Model\Config;

class Widget implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ChallengeServiceInterface $challengeService,
        private readonly UrlInterface $url,
        private readonly ResolverInterface $localeResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly Json $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function isEnabledFor(string $formKey): bool
    {
        return $this->config->isEnabledForForm($formKey, $this->getStoreId($formKey));
    }

    /**
     * Value for the widget "challenge" attribute. Storefront forms get the
     * challenge endpoint URL (keeps pages full-page-cache safe: the challenge
     * is fetched client-side at interaction time). Admin forms get an inline
     * JSON challenge minted at page render — the admin login page is pre-auth
     * and never cached, and this avoids exposing any pre-auth admin endpoint.
     */
    public function getChallengeValue(string $formKey): string
    {
        if (!$this->config->isBackendForm($formKey)) {
            return $this->url->getUrl('altcha/challenge', [
                '_query' => ['form' => $formKey],
                '_secure' => true,
            ]);
        }

        try {
            return $this->serializer->serialize(
                $this->challengeService->create($formKey, $this->getStoreId($formKey))
            );
        } catch (LocalizedException $e) {
            $this->logger->error('ALTCHA widget: ' . $e->getMessage());

            return '';
        }
    }

    public function getFieldName(): string
    {
        return $this->config->getFieldName();
    }

    /**
     * Locale in the format used by the vendored i18n bundles (fr_FR => fr-fr).
     */
    public function getLanguage(): string
    {
        return strtolower(str_replace('_', '-', $this->localeResolver->getLocale()));
    }

    /**
     * Widget Configuration overrides, passed through the "configuration"
     * attribute. humanInteractionSignature is disabled: it is only useful with
     * ALTCHA's hosted spam filter and this module never sends it server-side.
     */
    public function getConfigurationJson(string $formKey): string
    {
        $appearance = $this->config->getAppearance($this->getStoreId($formKey));

        return $this->serializer->serialize([
            'hideFooter' => $appearance['hideFooter'],
            'hideLogo' => $appearance['hideLogo'],
            'humanInteractionSignature' => false,
        ]);
    }

    public function getTheme(string $formKey): string
    {
        return $this->config->getAppearance($this->getStoreId($formKey))['theme'];
    }

    /**
     * Backend forms always bind to store 0: in the adminhtml area the store
     * manager resolves the default (or cookie-selected) frontend store view,
     * which would not match what the verifying observer uses.
     */
    private function getStoreId(string $formKey): int
    {
        if ($this->config->isBackendForm($formKey)) {
            return 0;
        }

        return (int)$this->storeManager->getStore()->getId();
    }
}
