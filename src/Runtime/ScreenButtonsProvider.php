<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime;

use function is_array;
use function max;
use function trim;

final class ScreenButtonsProvider
{
    public function __construct(
        private readonly array $definitions = [],
    ) {
    }

    /**
     * @return list<ScreenButton>
     */
    public function forScreen(string $screenName): array
    {
        $screenName = $this->normalizeScreenName($screenName);
        if ($screenName === null) {
            return [];
        }

        $items = $this->definitions[$screenName] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $buttons = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label  = trim((string) ($item['label'] ?? ''));
            $action = trim((string) ($item['action'] ?? ''));
            if ($label === '' || $action === '') {
                continue;
            }

            $row      = max(1, (int) ($item['row'] ?? 1));
            $position = max(1, (int) ($item['position'] ?? 1));

            $buttons[] = new ScreenButton(
                label: $label,
                action: $action,
                row: $row,
                position: $position,
            );
        }

        return $buttons;
    }

    private function normalizeScreenName(string $screenName): ?string
    {
        $screenName = trim($screenName);
        if ($screenName === '') {
            return null;
        }

        return $screenName;
    }
}
