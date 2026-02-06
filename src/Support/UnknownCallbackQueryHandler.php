<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Support;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Update\Update;
use Throwable;

use function trim;

final readonly class UnknownCallbackQueryHandler
{
    public function __construct(
        private string $message = '',
        private bool $showAlert = false,
    ) {
    }

    public function __invoke(Update $update, BotContext $context): void
    {
        $callbackQueryId = trim((string) ($update->callbackQueryId() ?? ''));
        if ($callbackQueryId === '') {
            return;
        }

        $options = [];
        if (trim($this->message) !== '') {
            $options['text'] = trim($this->message);
        }

        if ($this->showAlert) {
            $options['show_alert'] = true;
        }

        try {
            $context->answerCallbackQuery($callbackQueryId, $options);
        } catch (Throwable) {
            // Fallback handler must never break update processing.
        }
    }
}
