<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

use RuntimeException;

use function class_exists;
use function trim;

final readonly class FlowTransition
{
    /**
     * @param array<string,mixed> $guardArgs
     */
    public function __construct(
        public string $from,
        public string $event,
        public FlowTarget $target,
        public ?string $guardClass = null,
        public array $guardArgs = [],
        public ?string $expectedScreen = null,
    ) {
        $from  = trim($this->from);
        $event = trim($this->event);
        if ($from !== $this->from || $event !== $this->event) {
            throw new RuntimeException('Flow transition values must be trimmed.');
        }

        if ($from === '') {
            throw new RuntimeException('Flow transition "from" must not be empty.');
        }

        if ($event === '') {
            throw new RuntimeException('Flow transition "event" must not be empty.');
        }

        if ($this->guardClass !== null && trim($this->guardClass) === '') {
            throw new RuntimeException('Flow transition guard class must not be empty.');
        }

        if ($this->guardClass !== null && !class_exists($this->guardClass)) {
            throw new RuntimeException('Flow transition guard class does not exist: ' . $this->guardClass);
        }

        if ($this->expectedScreen !== null) {
            $expectedScreen = trim($this->expectedScreen);
            if ($expectedScreen === '') {
                throw new RuntimeException('Expected screen id must not be empty.');
            }

            if ($expectedScreen !== $this->expectedScreen) {
                throw new RuntimeException('Expected screen id must be trimmed.');
            }

            if ($this->target->isScreen() && $expectedScreen !== $this->target->id) {
                throw new RuntimeException('Expected screen id must match target screen id.');
            }
        }
    }

    /**
     * @param array<string,mixed> $guardArgs
     */
    public static function action(
        string $from,
        string $event,
        string $actionId,
        ?string $guardClass = null,
        array $guardArgs = [],
        ?string $expectedScreen = null,
    ): self {
        return new self(
            from: $from,
            event: $event,
            target: FlowTarget::action($actionId),
            guardClass: $guardClass,
            guardArgs: $guardArgs,
            expectedScreen: $expectedScreen,
        );
    }

    /**
     * @param array<string,mixed> $guardArgs
     */
    public static function screen(
        string $from,
        string $event,
        string $screenId,
        ?string $guardClass = null,
        array $guardArgs = [],
        ?string $expectedScreen = null,
    ): self {
        if ($expectedScreen !== null && $expectedScreen !== $screenId) {
            throw new RuntimeException('Expected screen id must match target screen id.');
        }

        return new self(
            from: $from,
            event: $event,
            target: FlowTarget::screen($screenId),
            guardClass: $guardClass,
            guardArgs: $guardArgs,
            expectedScreen: $expectedScreen ?? $screenId,
        );
    }
}
