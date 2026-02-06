<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Support;

use PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapRegistryInterface;
use PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapRegistryResolverInterface;

final class FakeFlowMapRegistryResolver implements TelegramFlowMapRegistryResolverInterface
{
    /**
     * @var list<string>
     */
    public array $resolvedBots = [];

    public function __construct(
        private readonly TelegramFlowMapRegistryInterface $registry,
    ) {
    }

    public function resolve(string $bot): TelegramFlowMapRegistryInterface
    {
        $this->resolvedBots[] = $bot;

        return $this->registry;
    }
}
