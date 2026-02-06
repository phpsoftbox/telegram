<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

use RuntimeException;

use function trim;

final readonly class ScenarioFlowMapNode
{
    public function __construct(
        public string $id,
        public string $type,
        public string $label,
    ) {
        $id = trim($this->id);
        if ($id === '') {
            throw new RuntimeException('Flow map node id must not be empty.');
        }
        if ($id !== $this->id) {
            throw new RuntimeException('Flow map node id must be trimmed.');
        }

        $type = trim($this->type);
        if ($type === '') {
            throw new RuntimeException('Flow map node type must not be empty.');
        }
        if ($type !== $this->type) {
            throw new RuntimeException('Flow map node type must be trimmed.');
        }

        $label = trim($this->label);
        if ($label !== $this->label) {
            throw new RuntimeException('Flow map node label must be trimmed.');
        }
    }
}
