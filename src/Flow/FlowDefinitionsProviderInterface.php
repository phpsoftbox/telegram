<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

interface FlowDefinitionsProviderInterface
{
    /**
     * @return list<FlowTransition>
     */
    public function transitions(): array;
}
