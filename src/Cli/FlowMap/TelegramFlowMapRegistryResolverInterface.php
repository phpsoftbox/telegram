<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Cli\FlowMap;

interface TelegramFlowMapRegistryResolverInterface
{
    public function resolve(string $bot): TelegramFlowMapRegistryInterface;
}
