<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

use PhpSoftBox\Telegram\Update\Update;

use function is_string;
use function random_int;
use function str_repeat;
use function str_replace;
use function strlen;
use function trim;

final class OtpStep implements ConversationStepInterface
{
    private readonly string $codeKey;

    public function __construct(
        private readonly string $key,
        private readonly string $prompt,
        private readonly int $length = 6,
        private readonly ?string $errorMessage = null,
    ) {
        $this->codeKey = $this->key . '_code';
    }

    public function key(): string
    {
        return $this->key;
    }

    public function prompt(ConversationContext $context): ?string
    {
        $code = $context->data()[$this->codeKey] ?? null;
        if (!is_string($code) || $code === '') {
            $code = $this->generateCode($this->length);
            $context->set($this->codeKey, $code);
        }

        return str_replace('{code}', $code, $this->prompt);
    }

    public function handle(Update $update, ConversationContext $context): StepResult
    {
        $text = $update->text();
        if ($text === null) {
            return StepResult::reject($this->errorMessage ?? 'Введите код.');
        }

        $code = $context->data()[$this->codeKey] ?? null;
        if (!is_string($code) || $code === '') {
            return StepResult::reject($this->errorMessage ?? 'Код недоступен, попробуйте снова.');
        }

        $value = trim($text);
        if ($value !== $code) {
            return StepResult::reject($this->errorMessage ?? 'Неверный код, попробуйте ещё раз.');
        }

        $messageId = $update->message()?->messageId();
        if ($messageId !== null) {
            $context->deleteMessage($messageId);
        }

        return StepResult::accept($value);
    }

    private function generateCode(int $length): string
    {
        $length = $length > 0 ? $length : 6;
        $max    = (10 ** $length) - 1;
        $code   = (string) random_int(0, $max);

        if (strlen($code) < $length) {
            $code = str_repeat('0', $length - strlen($code)) . $code;
        }

        return $code;
    }
}
