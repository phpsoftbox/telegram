<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

use PhpSoftBox\Telegram\Api\TelegramResponse;
use PhpSoftBox\Telegram\Update\Update;

use function is_array;

final class ContactStep implements ConversationStepInterface
{
    public function __construct(
        private readonly string $key,
        private readonly string $question,
        private readonly string $buttonText = 'Поделиться номером',
        private readonly bool $requireSelf = true,
        private readonly ?string $errorMessage = null,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function prompt(ConversationContext $context): ?string
    {
        $response = $context->bot()->client()->sendMessage($context->chatId(), $this->question, [
            'reply_markup' => [
                'keyboard' => [
                    [['text' => $this->buttonText, 'request_contact' => true]],
                ],
                'resize_keyboard'   => true,
                'one_time_keyboard' => true,
            ],
        ]);

        $messageId = $this->extractMessageId($response);
        if ($messageId !== null) {
            $context->state()->addMessageId($messageId);
        }

        return null;
    }

    public function handle(Update $update, ConversationContext $context): StepResult
    {
        $message = $update->message();
        $contact = $message?->contact();
        $phone   = $message?->contactPhone();

        if (!is_array($contact) || $phone === null || $phone === '') {
            return StepResult::reject($this->errorMessage ?? 'Нужно отправить контакт.');
        }

        if ($this->requireSelf) {
            $contactUserId = $message?->contactUserId();
            $fromId        = $message?->fromId();
            if ($contactUserId === null || $fromId === null || $contactUserId !== $fromId) {
                return StepResult::reject($this->errorMessage ?? 'Поделитесь своим номером телефона.');
            }
        }

        $messageId = $message?->messageId();
        if ($messageId !== null) {
            $context->deleteMessage($messageId);
        }

        return StepResult::accept($phone);
    }

    private function extractMessageId(TelegramResponse $response): ?int
    {
        $result = $response->result();
        if (!is_array($result)) {
            return null;
        }

        return isset($result['message_id']) ? (int) $result['message_id'] : null;
    }
}
