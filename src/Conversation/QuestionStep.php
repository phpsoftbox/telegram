<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

use PhpSoftBox\Telegram\Update\Update;

final class QuestionStep implements ConversationStepInterface
{
    /** @var callable|null Парсер значения шага. */
    private $parser;

    /** @var callable|null Валидатор значения шага. */
    private $validator;

    public function __construct(
        private readonly string $key,
        private readonly string $question,
        ?callable $parser = null,
        ?callable $validator = null,
        private readonly ?string $errorMessage = null,
    ) {
        $this->parser    = $parser;
        $this->validator = $validator;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function prompt(ConversationContext $context): ?string
    {
        return $this->question;
    }

    public function handle(Update $update, ConversationContext $context): StepResult
    {
        $value = $this->parser !== null
            ? ($this->parser)($update, $context)
            : $update->message()?->value();

        if ($value === null) {
            return StepResult::reject($this->errorMessage ?? 'Invalid input.');
        }

        if ($this->validator !== null && !($this->validator)($value, $update, $context)) {
            return StepResult::reject($this->errorMessage ?? 'Invalid input.');
        }

        return StepResult::accept($value);
    }
}
