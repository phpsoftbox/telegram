<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

interface FlowGuardInterface
{
    public function getId(): string;

    /**
     * @param array<string,mixed> $args
     */
    public function evaluate(TransitionContext $context, array $args = []): bool;
}
