<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;
use Throwable;

use function implode;
use function is_array;
use function is_bool;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function trim;

final class TelegramWebhookHandler implements HandlerInterface
{
    public function __construct(
        private readonly TelegramBotRegistry $bots,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $botName = (string) $runner->request()->option('bot', '');
        $url = (string) $runner->request()->option('url', '');
        $baseUrl = (string) $runner->request()->option('base-url', '');
        $path = (string) $runner->request()->option('path', '');
        $showInfo = (bool) $runner->request()->option('info', false);
        $debug = (bool) $runner->request()->option('debug', false);

        $bot = $this->bots->bot($botName);
        if ($bot === null) {
            $available = $this->bots->names();
            $list = $available !== [] ? implode(', ', $available) : 'нет';
            $runner->io()->writeln(sprintf('Бот "%s" не найден. Доступные: %s', $botName !== '' ? $botName : 'default', $list), 'error');
            return Response::FAILURE;
        }

        if ($showInfo) {
            try {
                $info = $bot->client()->getWebhookInfo()->result();
            } catch (Throwable $exception) {
                $runner->io()->writeln(sprintf('Ошибка Telegram: %s', $exception->getMessage()), 'error');
                return Response::FAILURE;
            }

            $runner->io()->writeln('Webhook info:', 'comment');
            $runner->io()->writeln($this->stringifyResult($info));

            return Response::SUCCESS;
        }

        $webhookUrl = $this->resolveWebhookUrl($botName, $url, $baseUrl, $path);
        if ($webhookUrl === '') {
            $runner->io()->writeln('Не задан URL для webhook.', 'error');
            return Response::FAILURE;
        }

        if ($debug) {
            $runner->io()->writeln(sprintf('Установка webhook: %s', $webhookUrl));
        }

        try {
            $bot->client()->setWebhook($webhookUrl);
        } catch (Throwable $exception) {
            $runner->io()->writeln(sprintf('Ошибка Telegram: %s', $exception->getMessage()), 'error');
            return Response::FAILURE;
        }

        $runner->io()->writeln('Webhook установлен: ' . $webhookUrl, 'success');

        return Response::SUCCESS;
    }

    private function resolveWebhookUrl(string $botName, string $url, string $baseUrl, string $path): string
    {
        $url = trim($url);
        if ($url !== '') {
            return $url;
        }

        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return '';
        }

        $path = trim($path);
        if ($path === '') {
            $path = '/telegram/{bot}/webhook';
        }

        if (!str_contains($path, '{bot}')) {
            $path = rtrim($path, '/') . '/{bot}/webhook';
        }

        $bot = $botName !== '' ? $botName : $this->bots->defaultName();
        $path = str_replace('{bot}', $bot, $path);

        return $baseUrl . $path;
    }

    private function stringifyResult(mixed $result): string
    {
        if (is_array($result)) {
            $lines = [];
            foreach ($result as $key => $value) {
                $lines[] = sprintf('%s: %s', (string) $key, $this->stringifyResult($value));
            }

            return implode("\n", $lines);
        }

        if (is_bool($result)) {
            return $result ? 'true' : 'false';
        }

        if ($result === null) {
            return 'null';
        }

        return (string) $result;
    }
}
