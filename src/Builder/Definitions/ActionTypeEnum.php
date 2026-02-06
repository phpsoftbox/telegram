<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Definitions;

enum ActionTypeEnum: string
{
    case CALLBACK = 'callback';
    case URL      = 'url';
}
