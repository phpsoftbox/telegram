<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Support;

use PhpSoftBox\CliApp\Io\IoInterface;
use PhpSoftBox\CliApp\Io\ProgressInterface;
use PhpSoftBox\CliApp\Request\Request;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;

use function array_key_last;

final class FakeCliRunner implements RunnerInterface
{
    /**
     * @var list<string>
     */
    private array $messages = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $options = [],
    ) {
    }

    public function run(string $command, array $argv): Response
    {
        return new Response(Response::SUCCESS);
    }

    public function runSubCommand(string $command, array $argv): Response
    {
        return new Response(Response::SUCCESS);
    }

    public function request(): Request
    {
        return new Request(params: [], options: $this->options);
    }

    public function io(): IoInterface
    {
        return new class ($this) implements IoInterface {
            public function __construct(
                private readonly FakeCliRunner $runner,
            ) {
            }

            public function ask(string $question, ?string $default = null): string
            {
                return $default ?? '';
            }

            public function confirm(string $question, bool $default = false): bool
            {
                return $default;
            }

            public function secret(string $question): string
            {
                return '';
            }

            public function writeln(string $message, string $style = 'info'): void
            {
                $this->runner->appendMessage('[' . $style . '] ' . $message);
            }

            public function table(array $headers, array $rows): void
            {
            }

            public function progress(int $max): ProgressInterface
            {
                return new class () implements ProgressInterface {
                    public function advance(int $step = 1): void
                    {
                    }

                    public function finish(): void
                    {
                    }
                };
            }
        };
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public function lastMessage(): string
    {
        $message = $this->messages[array_key_last($this->messages)] ?? '';

        return (string) $message;
    }

    public function appendMessage(string $message): void
    {
        $this->messages[] = $message;
    }
}
