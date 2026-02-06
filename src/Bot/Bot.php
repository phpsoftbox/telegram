<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Bot;

use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Support\MessageCleaner;
use PhpSoftBox\Telegram\Update\Update;

final readonly class Bot implements UpdateHandlerInterface
{
    private BotContext $context;

    public function __construct(
        TelegramClient $client,
        private UpdateRouter $router,
        private ?ConversationManager $conversations = null,
        ?MessageCleaner $messageCleaner = null,
    ) {
        $this->context = new BotContext($client, $conversations, $messageCleaner);
    }

    public function handle(Update $update): void
    {
        if ($this->conversations !== null && $this->conversations->handle($update, $this->context)) {
            return;
        }

        $this->router->dispatch($update, $this->context);
    }
}
