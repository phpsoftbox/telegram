<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Update\Update;

use function array_key_exists;

final readonly class TransitionContext
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public Update $update,
        public BotContext $botContext,
        public array $payload = [],
    ) {
    }

    public function payload(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->payload) ? $this->payload[$key] : $default;
    }
}
