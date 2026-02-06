<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;

use function array_keys;
use function is_array;
use function trim;

final class TelegramSyncResetHandler implements HandlerInterface
{
    public function __construct(
        private readonly TelegramBotRegistry $bots,
        private readonly Config $config,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $botName = trim((string) $runner->request()->option('bot'));

        if ($botName !== '') {
            return $this->resetBot($runner, $botName)
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
            if (!$this->resetBot($runner, $name)) {
                $failed = true;
            }
        }

        return $failed ? Response::FAILURE : Response::SUCCESS;
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

    private function resetBot(RunnerInterface $runner, string $botName): bool
    {
        $bot = $this->bots->bot($botName);
        if ($bot === null) {
            $runner->io()->writeln('Бот не найден: ' . $botName, 'error');

            return false;
        }

        $bot->client()->deleteMyCommands();
        $runner->io()->writeln('Команды сброшены для бота: ' . $botName, 'success');

        return true;
    }
}
