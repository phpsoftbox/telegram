<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Router;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Update\MessageTypeEnum;
use PhpSoftBox\Telegram\Update\Update;

use function ltrim;
use function strpos;
use function substr;
use function trim;

final class UpdateRouter
{
    /** @var array<string, callable> Команды бота. */
    private array $commands = [];

    /** @var array<string, list<callable>> Обработчики по типу сообщения. */
    private array $typeHandlers = [];

    /** @var list<callable> Обработчики текстовых сообщений. */
    private array $textHandlers = [];

    /** @var callable|null Обработчик по умолчанию. */
    private $fallback = null;

    public function command(string $name, callable $handler): self
    {
        $this->commands[$name] = $handler;

        return $this;
    }

    public function onText(callable $handler): self
    {
        $this->textHandlers[] = $handler;

        return $this;
    }

    public function onType(MessageTypeEnum $type, callable $handler): self
    {
        $this->typeHandlers[$type->value][] = $handler;

        return $this;
    }

    public function fallback(callable $handler): self
    {
        $this->fallback = $handler;

        return $this;
    }

    public function dispatch(Update $update, BotContext $context): bool
    {
        $text = $update->text();
        if ($text !== null && $text !== '') {
            $command = $this->parseCommand($text);
            if ($command !== null && isset($this->commands[$command])) {
                ($this->commands[$command])($update, $context);

                return true;
            }

            if ($this->textHandlers !== []) {
                foreach ($this->textHandlers as $handler) {
                    $handler($update, $context);
                }

                return true;
            }
        }

        $type     = $update->type();
        $handlers = $this->typeHandlers[$type->value] ?? [];
        if ($handlers !== []) {
            foreach ($handlers as $handler) {
                $handler($update, $context);
            }

            return true;
        }

        if ($this->fallback !== null) {
            ($this->fallback)($update, $context);

            return true;
        }

        return false;
    }

    private function parseCommand(string $text): ?string
    {
        $text = trim($text);
        if ($text === '' || $text[0] !== '/') {
            return null;
        }

        $spacePos = strpos($text, ' ');
        $command  = $spacePos === false ? $text : substr($text, 0, $spacePos);
        $command  = ltrim($command, '/');

        if ($command === '') {
            return null;
        }

        $atPos = strpos($command, '@');
        if ($atPos !== false) {
            $command = substr($command, 0, $atPos);
        }

        return $command !== '' ? $command : null;
    }
}
