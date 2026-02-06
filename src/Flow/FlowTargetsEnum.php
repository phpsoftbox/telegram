<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

enum FlowTargetsEnum: string
{
    case SCREEN = 'screen';
    case ACTION = 'action';
}
