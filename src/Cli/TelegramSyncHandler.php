<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;

use function array_is_list;
use function array_keys;
use function getenv;
use function is_array;
use function rtrim;
use function sprintf;
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
        $botName          = trim((string) $runner->request()->option('bot'));
        $updateWebhook    = (bool) $runner->request()->option('webhook');
        $webhookIpAddress = trim((string) $runner->request()->option('webhook-ip-address'));
        if ($webhookIpAddress === '') {
            $webhookIpAddress = trim((string) ($this->config->get('telegram.webhook_ip_address', '') ?? ''));
        }
        if ($webhookIpAddress === '') {
            $webhookIpAddress = trim((string) (getenv('TELEGRAM_WEBHOOK_IP_ADDRESS') ?: ''));
        }

        if ($botName !== '') {
            return $this->syncBot($runner, $botName, $updateWebhook, $webhookIpAddress)
                ? Response::SUCCESS
                : Response::FAILURE;
        }

        $botNames = $this->allBotNames();
        if ($botNames === []) {
            $runner->io()->writeln('В конфиге не найдено ни одного бота (telegram.bots).', 'error');

            return Response::FAILURE;
        }

        $failed = false;
        foreach ($botNames as $name) {
            if (!$this->syncBot($runner, $name, $updateWebhook, $webhookIpAddress)) {
                $failed = true;
            }
        }

        if ($failed) {
            return Response::FAILURE;
        }

        return Response::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function allBotNames(): array
    {
        $bots = $this->config->get('telegram.bots');
        if (!is_array($bots) || $bots === []) {
            return [];
        }

        $names = [];
        foreach (array_keys($bots) as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $names[] = $name;
        }

        return $names;
    }

    private function syncBot(
        RunnerInterface $runner,
        string $botName,
        bool $updateWebhook,
        string $webhookIpAddress,
    ): bool {
        $bot = $this->bots->bot($botName);
        if ($bot === null) {
            $runner->io()->writeln('Бот не найден: ' . $botName, 'error');

            return false;
        }

        $commands = $this->configuredCommandsFor($botName);
        if ($commands === []) {
            $runner->io()->writeln('Нет команд для бота: ' . $botName . ' (ожидается telegram.commands.' . $botName . ')', 'error');

            return false;
        }

        $bot->client()->setMyCommands($commands);
        $runner->io()->writeln('Команды обновлены для бота: ' . $botName, 'success');
        $profile = $this->configuredProfileFor($botName);
        if ($profile['description'] !== '') {
            $bot->client()->setMyDescription($profile['description']);
            $runner->io()->writeln('Описание бота обновлено: ' . $botName, 'success');
        }
        if ($profile['short_description'] !== '') {
            $bot->client()->setMyShortDescription($profile['short_description']);
            $runner->io()->writeln('Короткое описание бота обновлено: ' . $botName, 'success');
        }

        if ($updateWebhook) {
            $adminUrl = (string) ($this->config->get('app.admin_url', '') ?? '');
            $adminUrl = $adminUrl !== '' ? rtrim($adminUrl, '/') : '';
            if ($adminUrl === '') {
                $runner->io()->writeln('APP_ADMIN_URL не задан. Webhook не обновлён.', 'error');

                return false;
            }

            $webhookUrl     = sprintf('%s/telegram/%s/webhook', $adminUrl, $botName);
            $webhookOptions = [];
            if ($webhookIpAddress !== '') {
                $webhookOptions['ip_address'] = $webhookIpAddress;
            }

            $bot->client()->setWebhook($webhookUrl, $webhookOptions);
            $runner->io()->writeln('Webhook обновлён: ' . $webhookUrl, 'success');
            if ($webhookIpAddress !== '') {
                $runner->io()->writeln('Webhook ip_address: ' . $webhookIpAddress, 'info');
            }
        }

        return true;
    }

    /**
     * @return array<int, array{command:string,description:string}>
     */
    private function configuredCommandsFor(string $botName): array
    {
        $raw = $this->config->get('telegram.commands.' . $botName);
        if (!is_array($raw) || !array_is_list($raw)) {
            return [];
        }

        $commands = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $command     = trim((string) ($item['command'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));

            if ($command === '' || $description === '') {
                continue;
            }

            $commands[] = [
                'command'     => $command,
                'description' => $description,
            ];
        }

        return $commands;
    }

    /**
     * @return array{description: string, short_description: string}
     */
    private function configuredProfileFor(string $botName): array
    {
        $raw = $this->config->get('telegram.profiles.' . $botName);
        if (!is_array($raw)) {
            return ['description' => '', 'short_description' => ''];
        }

        return [
            'description'       => trim((string) ($raw['description'] ?? '')),
            'short_description' => trim((string) ($raw['short_description'] ?? '')),
        ];
    }
}
