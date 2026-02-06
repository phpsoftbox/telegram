<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Cli\FlowMap;

interface TelegramFlowMapSettingsInterface
{
    public function defaultBot(): string;

    public function defaultOutputDir(): string;

    public function defaultFormat(): string;
}
