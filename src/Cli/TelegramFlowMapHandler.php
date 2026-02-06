<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapRegistryResolverInterface;
use PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapSettingsInterface;
use RuntimeException;

use function dirname;
use function file_put_contents;
use function in_array;
use function is_dir;
use function is_string;
use function json_encode;
use function mkdir;
use function preg_replace;
use function rtrim;
use function strtolower;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class TelegramFlowMapHandler implements HandlerInterface
{
    public function __construct(
        private ?TelegramFlowMapRegistryResolverInterface $registries = null,
        private ?TelegramFlowMapSettingsInterface $settings = null,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        if ($this->registries === null) {
            $runner->io()->writeln(
                'Flow map CLI is not configured. Bind TelegramFlowMapRegistryResolverInterface in DI.',
                'error',
            );

            return Response::FAILURE;
        }

        $scope = strtolower(trim((string) $runner->request()->option('scope', '')));
        if (!in_array($scope, ['branch', 'cjm'], true)) {
            $runner->io()->writeln('Опция --scope должна быть branch или cjm.', 'error');

            return Response::FAILURE;
        }

        $id = trim((string) $runner->request()->option('id', ''));
        if ($id === '') {
            $runner->io()->writeln('Опция --id обязательна.', 'error');

            return Response::FAILURE;
        }

        $defaultBot = $this->settings?->defaultBot() ?? 'main';
        $bot        = trim((string) $runner->request()->option('bot', $defaultBot));
        if ($bot === '') {
            $bot = $defaultBot;
        }

        $defaultFormat = $this->settings?->defaultFormat() ?? 'html';
        $format        = strtolower(trim((string) $runner->request()->option('format', $defaultFormat)));
        if (!in_array($format, ['html', 'json'], true)) {
            $runner->io()->writeln('Опция --format должна быть html или json.', 'error');

            return Response::FAILURE;
        }

        $registry = $this->registries->resolve($bot);

        if ($scope === 'branch' && $registry->flowMapBranchDefinitions($id) === []) {
            $runner->io()->writeln('Branch "' . $id . '" не найден.', 'error');

            return Response::FAILURE;
        }
        if ($scope === 'cjm' && $registry->flowMapCjmDefinitions($id) === []) {
            $runner->io()->writeln('CJM "' . $id . '" не найден.', 'error');

            return Response::FAILURE;
        }

        $scopeToken = $scope . ':' . $id;
        if ($format === 'html') {
            $content = $registry->flowMapHtml(
                includeButtons: true,
                chainScope: $scopeToken,
                rankdir: 'TB',
            );
        } else {
            $encoded = json_encode(
                $registry->flowMapScoped(includeButtons: true, chainScope: $scopeToken),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            if (!is_string($encoded)) {
                throw new RuntimeException('Не удалось сериализовать flow map в JSON.');
            }

            $content = $encoded;
        }

        $out = trim((string) $runner->request()->option('out', ''));
        if ($out === '') {
            $out = $this->defaultOutputPath($bot, $id, $format);
        }

        $directory = dirname($out);
        if ($directory !== '' && !is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Не удалось создать директорию: ' . $directory);
        }

        if (file_put_contents($out, $content . "\n") === false) {
            throw new RuntimeException('Не удалось записать файл: ' . $out);
        }

        $runner->io()->writeln('Flow map сохранена: ' . $out, 'success');

        return Response::SUCCESS;
    }

    private function defaultOutputPath(string $bot, string $id, string $format): string
    {
        $baseDir = $this->settings?->defaultOutputDir() ?? 'local/telegram';
        $baseDir = rtrim($baseDir, '/');
        $bot     = $this->normalizeToken($bot);
        $id      = $this->normalizeToken($id);

        return $baseDir . '/' . $bot . '/' . $id . '.' . $format;
    }

    private function normalizeToken(string $value): string
    {
        $value = trim($value);
        $value = (string) preg_replace('/[^a-zA-Z0-9._-]+/', '_', $value);
        $value = trim($value, '_');

        return $value !== '' ? $value : 'scope';
    }
}
