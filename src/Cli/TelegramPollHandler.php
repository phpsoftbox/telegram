<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;
use PhpSoftBox\Telegram\Update\MessageTypeEnum;
use PhpSoftBox\Telegram\Update\Update;
use Throwable;

use function count;
use function implode;
use function is_array;
use function mb_strlen;
use function mb_substr;
use function pcntl_fork;
use function pcntl_wait;
use function pcntl_wexitstatus;
use function pcntl_wifexited;
use function sleep;
use function sprintf;
use function str_replace;
use function trim;
use function function_exists;

final class TelegramPollHandler implements HandlerInterface
{
    public function __construct(
        private readonly TelegramBotRegistry $bots,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $botName = (string) $runner->request()->option('bot', '');
        $timeout = (int) $runner->request()->option('timeout', 25);
        $sleep = (int) $runner->request()->option('sleep', 1);
        $once = (bool) $runner->request()->option('once', false);
        $debug = (bool) $runner->request()->option('debug', false);
        $all = (bool) $runner->request()->option('all', false);
        $initialOffset = (int) $runner->request()->option('offset', 0);

        $bots = $this->resolveBots($runner, $botName, $all);
        if ($bots === []) {
            return Response::FAILURE;
        }

        if ($all) {
            return $this->runParallel($runner, $bots, $timeout, $sleep, $once, $debug, $initialOffset);
        }

        $name = (string) array_key_first($bots);
        $bot = $bots[$name];

        return $this->pollSingleBot($runner, $name, $bot, $timeout, $sleep, $once, $debug, $initialOffset);
    }

    /**
     * @param array<string, \PhpSoftBox\Telegram\Bot\TelegramBot> $bots
     * @return array<string, \PhpSoftBox\Telegram\Bot\TelegramBot>
     */
    private function resolveBots(RunnerInterface $runner, string $botName, bool $all): array
    {
        if ($all) {
            $names = $this->bots->names();
            if ($names === []) {
                $runner->io()->writeln('Боты не найдены.', 'error');
                return [];
            }

            $result = [];
            foreach ($names as $name) {
                $bot = $this->bots->bot($name);
                if ($bot !== null) {
                    $result[$name] = $bot;
                }
            }

            if ($result === []) {
                $runner->io()->writeln('Боты не найдены.', 'error');
            }

            return $result;
        }

        $bot = $this->bots->bot($botName);
        if ($bot === null) {
            $available = $this->bots->names();
            $list = $available !== [] ? implode(', ', $available) : 'нет';
            $runner->io()->writeln(sprintf('Бот "%s" не найден. Доступные: %s', $botName !== '' ? $botName : 'default', $list), 'error');
            return [];
        }

        $name = $botName !== '' ? $botName : $this->bots->defaultName();

        return [$name => $bot];
    }

    private function runParallel(
        RunnerInterface $runner,
        array $bots,
        int $timeout,
        int $sleep,
        bool $once,
        bool $debug,
        int $initialOffset,
    ): int {
        if (!$this->supportsFork()) {
            $runner->io()->writeln('Опция --all требует расширение pcntl.', 'error');
            return Response::FAILURE;
        }

        if ($debug) {
            $runner->io()->writeln('Запуск Telegram polling (pcntl)...', 'info');
        }

        $children = [];
        $failed = false;

        foreach ($bots as $name => $bot) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $runner->io()->writeln(sprintf('Не удалось создать процесс для бота "%s".', $name), 'error');
                $failed = true;
                continue;
            }

            if ($pid === 0) {
                $code = $this->pollSingleBot($runner, $name, $bot, $timeout, $sleep, $once, $debug, $initialOffset);
                exit($code);
            }

            $children[$pid] = $name;
        }

        foreach (array_keys($children) as $pid) {
            $status = 0;
            $result = pcntl_wait($status);
            if ($result > 0 && pcntl_wifexited($status)) {
                $exitCode = pcntl_wexitstatus($status);
                if ($exitCode !== Response::SUCCESS) {
                    $failed = true;
                }
            }
        }

        return $failed ? Response::FAILURE : Response::SUCCESS;
    }

    private function pollSingleBot(
        RunnerInterface $runner,
        string $name,
        object $bot,
        int $timeout,
        int $sleep,
        bool $once,
        bool $debug,
        int $initialOffset,
    ): int {
        $offset = $initialOffset;

        if ($debug) {
            $runner->io()->writeln(sprintf('[%s] Запуск polling...', $name), 'info');
        }

        do {
            $payload = [
                'timeout' => $timeout > 0 ? $timeout : 0,
            ];
            if ($offset > 0) {
                $payload['offset'] = $offset;
            }

            if ($debug) {
                $runner->io()->writeln(sprintf('[%s] getUpdates (offset=%d, timeout=%d)', $name, $offset, $payload['timeout']));
            }

            try {
                $response = $bot->client()->request('getUpdates', $payload);
            } catch (Throwable $exception) {
                $runner->io()->writeln(sprintf('[%s] Ошибка Telegram: %s', $name, $exception->getMessage()), 'error');

                if ($once) {
                    return Response::FAILURE;
                }
                continue;
            }

            $updates = $response->result();
            if (is_array($updates)) {
                if ($debug) {
                    $runner->io()->writeln(sprintf('[%s] Получено обновлений: %d', $name, count($updates)));
                }
                foreach ($updates as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $update = Update::fromArray($item);
                    if ($debug) {
                        $this->printUpdateDebug($runner, $update, $name);
                    }
                    $bot->handler()->handle($update);

                    $updateId = $update->updateId();
                    if ($updateId !== null && $updateId + 1 > $offset) {
                        $offset = $updateId + 1;
                    }
                }
            }

            if ($once) {
                break;
            }

            if ($sleep > 0) {
                if ($debug) {
                    $runner->io()->writeln(sprintf('[%s] Сон: %d сек.', $name, $sleep));
                }
                sleep($sleep);
            }
        } while (true);

        return Response::SUCCESS;
    }

    private function supportsFork(): bool
    {
        return function_exists('pcntl_fork') && function_exists('pcntl_wait');
    }

    private function printUpdateDebug(RunnerInterface $runner, Update $update, string $botName): void
    {
        $type = $update->type();
        $text = $update->text();

        $summary = [
            'update_id=' . ($update->updateId() ?? 'n/a'),
            'type=' . $this->formatType($type),
        ];

        if ($text !== null && $text !== '') {
            $summary[] = 'text="' . $this->trimText($text) . '"';
        }

        $runner->io()->writeln(sprintf('[%s] Обработка: %s', $botName, implode(', ', $summary)));
    }

    private function trimText(string $text): string
    {
        $text = str_replace(["\n", "\r"], ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) > 80) {
            return mb_substr($text, 0, 77) . '...';
        }

        return $text;
    }

    private function formatType(MessageTypeEnum $type): string
    {
        return $type === MessageTypeEnum::UNKNOWN ? 'unknown' : $type->value;
    }
}
