<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Webcooking\Altcha\Model\Config;

/**
 * Warns when Google reCAPTCHA is still active for a form also protected by
 * ALTCHA (double CAPTCHA / blocked submissions). Reads the reCAPTCHA config by
 * path only — no code dependency on the ReCaptcha modules, which may be absent.
 */
class RecaptchaConflictNotice extends Field
{
    /**
     * ALTCHA form key => reCAPTCHA type_for config paths.
     * Keys verified identical on Magento 2.4.8 and 2.4.9.
     */
    private const array RECAPTCHA_PATHS = [
        'customer_login' => 'recaptcha_frontend/type_for/customer_login',
        'customer_create' => 'recaptcha_frontend/type_for/customer_create',
        'customer_forgotpassword' => 'recaptcha_frontend/type_for/customer_forgot_password',
        'contact' => 'recaptcha_frontend/type_for/contact',
        'newsletter' => 'recaptcha_frontend/type_for/newsletter',
        'product_review' => 'recaptcha_frontend/type_for/product_review',
        'admin_login' => 'recaptcha_backend/type_for/user_login',
        'admin_forgotpassword' => 'recaptcha_backend/type_for/user_forgot_password',
    ];

    public function render(AbstractElement $element): string
    {
        $conflicts = $this->findConflicts();
        if ($conflicts === []) {
            return '';
        }

        $message = __(
            'Google reCAPTCHA is still enabled for: %1. Disable it for these forms '
            . '(Security > Google reCAPTCHA) before enabling ALTCHA on them, '
            . 'otherwise visitors face two CAPTCHAs or blocked submissions.',
            implode(', ', $conflicts)
        );

        return '<tr><td colspan="4"><div class="message message-warning warning">'
            . $this->_escaper->escapeHtml((string)$message)
            . '</div></td></tr>';
    }

    /**
     * @return string[] ALTCHA form keys with an active reCAPTCHA counterpart
     *                  in the default scope or any store view.
     */
    private function findConflicts(): array
    {
        $config = $this->_scopeConfig;
        $scopes = [[ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null]];
        foreach ($this->_storeManager->getStores() as $store) {
            $scopes[] = [\Magento\Store\Model\ScopeInterface::SCOPE_STORE, (int)$store->getId()];
        }

        $conflicts = [];
        foreach (self::RECAPTCHA_PATHS as $formKey => $recaptchaPath) {
            $group = str_starts_with($recaptchaPath, 'recaptcha_backend/')
                ? Config::GROUP_BACKEND_FORMS
                : Config::GROUP_FRONTEND_FORMS;
            $altchaPath = sprintf('altcha/%s/%s', $group, $formKey);
            foreach ($scopes as [$scope, $scopeId]) {
                if ($config->isSetFlag($altchaPath, $scope, $scopeId)
                    && (string)$config->getValue($recaptchaPath, $scope, $scopeId) !== ''
                ) {
                    $conflicts[] = $formKey;
                    break;
                }
            }
        }

        return $conflicts;
    }
}
