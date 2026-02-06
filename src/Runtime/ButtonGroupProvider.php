<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime;

use function is_array;
use function max;
use function trim;

final class ButtonGroupProvider
{
    public function __construct(
        private readonly array $definitions = [],
    ) {
    }

    /**
     * @return list<ButtonGroupButton>
     */
    public function forGroup(string $groupName): array
    {
        $groupName = $this->normalizeGroupName($groupName);
        if ($groupName === null) {
            return [];
        }

        $items = $this->definitions[$groupName] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $buttons = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name   = trim((string) ($item['name'] ?? ''));
            $label  = trim((string) ($item['label'] ?? ''));
            $action = trim((string) ($item['action'] ?? ''));
            if ($name === '' || $label === '' || $action === '') {
                continue;
            }

            $row      = max(1, (int) ($item['row'] ?? 1));
            $position = max(1, (int) ($item['position'] ?? 1));

            $buttons[] = new ButtonGroupButton(
                name: $name,
                label: $label,
                action: $action,
                row: $row,
                position: $position,
            );
        }

        return $buttons;
    }

    private function normalizeGroupName(string $groupName): ?string
    {
        $groupName = trim($groupName);
        if ($groupName === '') {
            return null;
        }

        return $groupName;
    }
}
