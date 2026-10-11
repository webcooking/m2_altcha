<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Encrypted;

/**
 * Encrypted HMAC secret that regenerates itself when saved empty, so clearing
 * the field rotates the key and the module never runs without one.
 */
class Secret extends Encrypted
{
    public function beforeSave()
    {
        $value = (string)$this->getValue();
        if ($value === '') {
            $this->setValue(bin2hex(random_bytes(32)));
        }

        return parent::beforeSave();
    }
}
