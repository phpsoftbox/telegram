<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Support;

use PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapSettingsInterface;

final readonly class FakeFlowMapSettings implements TelegramFlowMapSettingsInterface
{
    public function __construct(
        private string $defaultBot = 'main',
        private string $defaultOutputDir = 'local/telegram',
        private string $defaultFormat = 'html',
    ) {
    }

    public function defaultBot(): string
    {
        return $this->defaultBot;
    }

    public function defaultOutputDir(): string
    {
        return $this->defaultOutputDir;
    }

    public function defaultFormat(): string
    {
        return $this->defaultFormat;
    }
}
