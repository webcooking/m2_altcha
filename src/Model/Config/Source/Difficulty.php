<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Difficulty implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'easy', 'label' => __('Easy (fastest, least protection)')],
            ['value' => 'balanced', 'label' => __('Balanced (recommended)')],
            ['value' => 'hard', 'label' => __('Hard (slow on low-end devices)')],
        ];
    }
}
