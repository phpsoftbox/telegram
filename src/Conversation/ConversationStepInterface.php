<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

use PhpSoftBox\Telegram\Update\Update;

interface ConversationStepInterface
{
    public function key(): string;

    public function prompt(ConversationContext $context): ?string;

    public function handle(Update $update, ConversationContext $context): StepResult;
}
