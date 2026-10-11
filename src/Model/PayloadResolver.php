<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Model;

use Magento\Framework\App\RequestInterface;

class PayloadResolver
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function resolve(RequestInterface $request): string
    {
        $value = $request->getParam($this->config->getFieldName());

        return is_string($value) ? $value : '';
    }
}
