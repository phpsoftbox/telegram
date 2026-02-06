<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;

use function rtrim;
use function trim;

final class TelegramSyncHandler implements HandlerInterface
{
    public function __construct(
        private readonly TelegramBotRegistry $bots,
        private readonly Config $config,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $botName = trim((string) $runner->request()->option('bot'));
        $botName = $botName !== '' ? $botName : 'account';

        $bot = $this->bots->bot($botName);
        if ($bot === null) {
            $runner->io()->writeln('Бот не найден: ' . $botName, 'error');
            return Response::FAILURE;
        }

        $commands = $this->commandsFor($botName);
        if ($commands === []) {
            $runner->io()->writeln('Нет команд для бота: ' . $botName, 'error');
            return Response::FAILURE;
        }

        $bot->client()->setMyCommands($commands);
        $runner->io()->writeln('Команды обновлены для бота: ' . $botName, 'success');

        $updateWebhook = (bool) $runner->request()->option('webhook');
        if ($updateWebhook) {
            $adminUrl = (string) ($this->config->get('app.admin_url', '') ?? '');
            $adminUrl = $adminUrl !== '' ? rtrim($adminUrl, '/') : '';
            if ($adminUrl === '') {
                $runner->io()->writeln('APP_ADMIN_URL не задан. Webhook не обновлён.', 'error');
                return Response::FAILURE;
            }

            $webhookUrl = $adminUrl . '/telegram/' . $botName . '/webhook';
            $bot->client()->setWebhook($webhookUrl);
            $runner->io()->writeln('Webhook обновлён: ' . $webhookUrl, 'success');
        }

        return Response::SUCCESS;
    }

    /**
     * @return array<int, array{command:string,description:string}>
     */
    private function commandsFor(string $botName): array
    {
        if ($botName === 'account') {
            return [
                ['command' => 'start', 'description' => 'Показать меню'],
                ['command' => 'confirm', 'description' => 'Подтверждение номера'],
                ['command' => 'reset', 'description' => 'Сброс пароля'],
                ['command' => 'help', 'description' => 'Справка'],
            ];
        }

        return [];
    }
}
