<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Definitions;

final readonly class ActionDefinition
{
    public function __construct(
        public string $name,
        public ActionTypeEnum $type,
        public string $value,
    ) {
    }
}
