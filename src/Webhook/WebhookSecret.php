<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Webhook;

use function hash_hmac;

/**
 * Секрет webhook (`secret_token` в setWebhook, заголовок `X-Telegram-Bot-Api-Secret-Token` во входящем запросе).
 *
 * По умолчанию выводится из токена бота: вычислить его может только владелец токена, а отдельная настройка не нужна.
 * Результат — 64 символа [0-9a-f], что укладывается в ограничения Telegram (1–256 символов A-Z, a-z, 0-9, _ и -).
 */
final class WebhookSecret
{
    private const string CONTEXT = 'phpsoftbox-telegram-webhook';

    public static function fromBotToken(string $token): string
    {
        return hash_hmac('sha256', self::CONTEXT, $token);
    }
}
