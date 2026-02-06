<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

use RuntimeException;

use function trim;

final readonly class ScenarioFlowMapEdge
{
    /**
     * @param array<string, mixed> $guardArgs
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $kind,
        public string $label = '',
        public ?string $event = null,
        public ?string $targetType = null,
        public ?string $targetId = null,
        public ?string $guardClass = null,
        public array $guardArgs = [],
        public ?string $expectedScreen = null,
    ) {
        $from = trim($this->from);
        $to   = trim($this->to);
        $kind = trim($this->kind);
        if ($from === '' || $to === '' || $kind === '') {
            throw new RuntimeException('Flow map edge "from", "to" and "kind" must not be empty.');
        }
        if ($from !== $this->from || $to !== $this->to || $kind !== $this->kind) {
            throw new RuntimeException('Flow map edge "from", "to" and "kind" must be trimmed.');
        }

        $label = trim($this->label);
        if ($label !== $this->label) {
            throw new RuntimeException('Flow map edge label must be trimmed.');
        }

        if ($this->event !== null) {
            $event = trim($this->event);
            if ($event === '') {
                throw new RuntimeException('Flow map edge event must not be empty when provided.');
            }
            if ($event !== $this->event) {
                throw new RuntimeException('Flow map edge event must be trimmed.');
            }
        }

        if ($this->targetType !== null) {
            $targetType = trim($this->targetType);
            if ($targetType === '') {
                throw new RuntimeException('Flow map edge target type must not be empty when provided.');
            }
            if ($targetType !== $this->targetType) {
                throw new RuntimeException('Flow map edge target type must be trimmed.');
            }
        }

        if ($this->targetId !== null) {
            $targetId = trim($this->targetId);
            if ($targetId === '') {
                throw new RuntimeException('Flow map edge target id must not be empty when provided.');
            }
            if ($targetId !== $this->targetId) {
                throw new RuntimeException('Flow map edge target id must be trimmed.');
            }
        }

        if ($this->guardClass !== null) {
            $guardClass = trim($this->guardClass);
            if ($guardClass === '') {
                throw new RuntimeException('Flow map edge guard class must not be empty when provided.');
            }
            if ($guardClass !== $this->guardClass) {
                throw new RuntimeException('Flow map edge guard class must be trimmed.');
            }
        }

        if ($this->expectedScreen !== null) {
            $expectedScreen = trim($this->expectedScreen);
            if ($expectedScreen === '') {
                throw new RuntimeException('Flow map edge expected screen must not be empty when provided.');
            }
            if ($expectedScreen !== $this->expectedScreen) {
                throw new RuntimeException('Flow map edge expected screen must be trimmed.');
            }
        }
    }
}
