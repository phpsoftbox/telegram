<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Flow\FlowGuardInterface;
use PhpSoftBox\Telegram\Flow\FlowTarget;
use RuntimeException;

use function class_exists;
use function is_a;
use function trim;

final readonly class ScenarioEntryPoint
{
    /**
     * @param array<string,mixed> $guardArgs
     */
    public function __construct(
        public string $name,
        public FlowTarget $target,
        public ?string $guardClass = null,
        public array $guardArgs = [],
        public int $priority = 100,
        public string $description = '',
    ) {
        $name = trim($this->name);
        if ($name !== $this->name) {
            throw new RuntimeException('Entry point name must be trimmed.');
        }

        if ($name === '') {
            throw new RuntimeException('Entry point name must not be empty.');
        }

        $description = trim($this->description);
        if ($description !== $this->description) {
            throw new RuntimeException('Entry point description must be trimmed.');
        }

        if ($this->guardClass !== null) {
            $guardClass = trim($this->guardClass);
            if ($guardClass === '') {
                throw new RuntimeException('Entry point guard class must not be empty.');
            }

            if ($guardClass !== $this->guardClass) {
                throw new RuntimeException('Entry point guard class must be trimmed.');
            }

            if (!class_exists($guardClass)) {
                throw new RuntimeException('Entry point guard class does not exist: ' . $guardClass);
            }

            if (!is_a($guardClass, FlowGuardInterface::class, true)) {
                throw new RuntimeException(
                    'Entry point guard class "' . $guardClass . '" must implement ' . FlowGuardInterface::class . '.',
                );
            }
        }
    }
}
