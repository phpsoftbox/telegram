<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

use RuntimeException;

use function array_key_exists;
use function is_string;
use function trim;

final readonly class ScenarioFlowMapBranch
{
    /**
     * @param list<string> $entryEvents
     * @param list<string> $internalEvents
     * @param list<string> $exitEvents
     * @param list<string> $exitScreens
     */
    public function __construct(
        public string $id,
        public string $entryScreen,
        public array $internalEvents = [],
        public array $exitEvents = [],
        public array $exitScreens = [],
        public string $description = '',
        public array $entryEvents = [],
    ) {
        $id = trim($this->id);
        if ($id === '') {
            throw new RuntimeException('Flow map branch id must not be empty.');
        }
        if ($id !== $this->id) {
            throw new RuntimeException('Flow map branch id must be trimmed.');
        }

        $description = trim($this->description);
        if ($description !== $this->description) {
            throw new RuntimeException('Flow map branch description must be trimmed.');
        }

        $entryScreen = trim($this->entryScreen);
        if ($entryScreen !== $this->entryScreen) {
            throw new RuntimeException('Flow map branch entryScreen must be trimmed.');
        }

        $this->assertStringList($this->entryEvents, 'entryEvents');
        $this->assertStringList($this->internalEvents, 'internalEvents');
        $this->assertStringList($this->exitEvents, 'exitEvents');
        $this->assertStringList($this->exitScreens, 'exitScreens');

        if ($entryScreen === '' && $this->entryEvents === []) {
            throw new RuntimeException('Flow map branch must define entryScreen or entryEvents.');
        }
    }

    /**
     * @param list<string> $values
     */
    private function assertStringList(array $values, string $field): void
    {
        $seen = [];
        foreach ($values as $index => $value) {
            if (!is_string($value)) {
                throw new RuntimeException('Flow map branch ' . $field . ' must contain only strings.');
            }

            $normalized = trim($value);
            if ($normalized === '') {
                throw new RuntimeException('Flow map branch ' . $field . ' must not contain empty values.');
            }
            if ($normalized !== $value) {
                throw new RuntimeException('Flow map branch ' . $field . ' values must be trimmed.');
            }
            if (array_key_exists($normalized, $seen)) {
                throw new RuntimeException(
                    'Flow map branch ' . $field . ' contains duplicate value "' . $normalized . '" at index ' . $index . '.',
                );
            }

            $seen[$normalized] = true;
        }
    }
}
