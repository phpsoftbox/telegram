<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use RuntimeException;

use function trim;

final readonly class ActionHandlerDefinition
{
    public function __construct(
        public string $action,
        public string $handlerClass,
        public string $method = '__invoke',
    ) {
        $action = trim($this->action);
        if ($action !== $this->action || $action === '') {
            throw new RuntimeException('Action handler action must be a non-empty trimmed string.');
        }

        $handlerClass = trim($this->handlerClass);
        if ($handlerClass !== $this->handlerClass || $handlerClass === '') {
            throw new RuntimeException('Action handler class must be a non-empty trimmed string.');
        }

        $method = trim($this->method);
        if ($method !== $this->method || $method === '') {
            throw new RuntimeException('Action handler method must be a non-empty trimmed string.');
        }
    }
}
