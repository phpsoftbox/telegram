<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Api\TelegramResponse;
use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Support\MessageCleaner;
use PhpSoftBox\Telegram\Update\Update;

use function is_array;
use function strtolower;
use function trim;

final class ConversationManager
{
    /** @var array<string, ConversationDefinition> Зарегистрированные сценарии. */
    private array $definitions = [];

    private readonly BotContext $botContext;

    public function __construct(
        private readonly ConversationStoreInterface $store,
        private readonly TelegramClient $client,
        private readonly MessageCleaner $messageCleaner,
    ) {
        $this->botContext = new BotContext($client, $this, $messageCleaner);
    }

    public function register(ConversationDefinition $definition): void
    {
        $this->definitions[$definition->name()] = $definition;
    }

    public function start(string $name, Update $update): bool
    {
        $chatId = $update->chatId();
        if ($chatId === null || !isset($this->definitions[$name])) {
            return false;
        }

        $state = new ConversationState($name, (string) $chatId);

        $this->store->save($state);

        $this->sendPrompt($this->definitions[$name], $state, $this->botContext);

        return true;
    }

    public function handle(Update $update, ?BotContext $context = null): bool
    {
        $chatId = $update->chatId();
        if ($chatId === null) {
            return false;
        }

        $state = $this->store->get((string) $chatId);
        if ($state === null) {
            return false;
        }

        $definition = $this->definitions[$state->name()] ?? null;
        if ($definition === null) {
            return false;
        }

        $context             = $context ?? $this->botContext;
        $conversationContext = new ConversationContext($state, $context);

        if ($this->isCancel($update, $definition)) {
            $state->markCancelled();
            $this->store->delete($state->chatId());

            if ($definition->cancelMessage() !== null) {
                $context->sendMessage($state->chatId(), $definition->cancelMessage());
            }

            if ($definition->cancelHandler() !== null) {
                ($definition->cancelHandler())($conversationContext);
            }

            return true;
        }

        $step = $definition->step($state->stepIndex());
        if ($step === null) {
            $this->finish($definition, $conversationContext);

            return true;
        }

        $result = $step->handle($update, $conversationContext);
        if (!$result->accepted()) {
            if ($result->message() !== null) {
                $context->sendMessage($state->chatId(), $result->message());
            }

            $this->sendPrompt($definition, $state, $context, $step);

            return true;
        }

        $state->setData($step->key(), $result->value());
        $state->incrementStep();
        $this->store->save($state);

        $nextStep = $definition->step($state->stepIndex());
        if ($nextStep !== null) {
            $this->sendPrompt($definition, $state, $context, $nextStep);

            return true;
        }

        $this->finish($definition, $conversationContext);

        return true;
    }

    private function finish(ConversationDefinition $definition, ConversationContext $context): void
    {
        $context->state()->markFinished();
        $this->store->delete($context->state()->chatId());

        if ($definition->finishMessage() !== null) {
            $context->sendMessage($definition->finishMessage());
        }

        if ($definition->finishHandler() !== null) {
            ($definition->finishHandler())($context);
        }
    }

    private function sendPrompt(
        ConversationDefinition $definition,
        ConversationState $state,
        BotContext $context,
        ?ConversationStepInterface $step = null,
    ): void {
        $step = $step ?? $definition->step($state->stepIndex());
        if ($step === null) {
            return;
        }

        $prompt = $step->prompt(new ConversationContext($state, $context));
        if ($prompt === null) {
            return;
        }

        if ($definition->cleanupMessages() && $this->messageCleaner !== null && $state->messageIds() !== []) {
            $this->messageCleaner->clean($state->chatId(), $state->messageIds());
            $state->clearMessageIds();
        }

        $response  = $this->client->sendMessage($state->chatId(), $prompt);
        $messageId = $this->extractMessageId($response);
        if ($messageId !== null) {
            $state->addMessageId($messageId);
        }

        $this->store->save($state);
    }

    private function extractMessageId(TelegramResponse $response): ?int
    {
        $result = $response->result();
        if (!is_array($result)) {
            return null;
        }

        return isset($result['message_id']) ? (int) $result['message_id'] : null;
    }

    private function isCancel(Update $update, ConversationDefinition $definition): bool
    {
        $text = $update->text();
        if ($text === null) {
            return false;
        }

        $value = strtolower(trim($text));
        foreach ($definition->cancelKeywords() as $keyword) {
            if ($value === strtolower(trim($keyword))) {
                return true;
            }
        }

        return false;
    }
}
