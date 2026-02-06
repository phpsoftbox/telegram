<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

use RuntimeException;

use function array_key_exists;
use function is_string;
use function trim;

final readonly class ScenarioFlowMapCjm
{
    /**
     * @param list<string> $branches
     */
    public function __construct(
        public string $id,
        public array $branches = [],
        public string $description = '',
    ) {
        $id = trim($this->id);
        if ($id === '') {
            throw new RuntimeException('Flow map CJM id must not be empty.');
        }
        if ($id !== $this->id) {
            throw new RuntimeException('Flow map CJM id must be trimmed.');
        }

        $description = trim($this->description);
        if ($description !== $this->description) {
            throw new RuntimeException('Flow map CJM description must be trimmed.');
        }

        $this->assertStringList($this->branches, 'branches');
    }

    /**
     * @param list<string> $values
     */
    private function assertStringList(array $values, string $field): void
    {
        $seen = [];
        foreach ($values as $index => $value) {
            if (!is_string($value)) {
                throw new RuntimeException('Flow map CJM ' . $field . ' must contain only strings.');
            }

            $normalized = trim($value);
            if ($normalized === '') {
                throw new RuntimeException('Flow map CJM ' . $field . ' must not contain empty values.');
            }
            if ($normalized !== $value) {
                throw new RuntimeException('Flow map CJM ' . $field . ' values must be trimmed.');
            }
            if (array_key_exists($normalized, $seen)) {
                throw new RuntimeException(
                    'Flow map CJM ' . $field . ' contains duplicate value "' . $normalized . '" at index ' . $index . '.',
                );
            }

            $seen[$normalized] = true;
        }
    }
}
