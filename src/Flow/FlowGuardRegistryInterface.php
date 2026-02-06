<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

interface FlowGuardRegistryInterface
{
    public function get(string $guardClass): FlowGuardInterface;
}
