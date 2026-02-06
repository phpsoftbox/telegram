<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime;

use RuntimeException;

use function is_array;
use function str_starts_with;
use function substr;
use function trim;

final class ActionRegistry
{
    public function __construct(
        private readonly array $definitions = [],
    ) {
    }

    /**
     * @return array{type: 'callback'|'url', value: string}
     */
    public function resolve(string $action): array
    {
        $action     = trim($action);
        $definition = $this->definitions[$action] ?? null;
        if (is_array($definition)) {
            $type  = trim((string) ($definition['type'] ?? ''));
            $value = trim((string) ($definition['value'] ?? ''));
            if (($type === 'callback' || $type === 'url') && $value !== '') {
                return [
                    'type'  => $type,
                    'value' => $value,
                ];
            }
        }

        if (str_starts_with($action, 'url:')) {
            $url = trim(substr($action, 4));
            if ($url !== '') {
                return [
                    'type'  => 'url',
                    'value' => $url,
                ];
            }
        }

        throw new RuntimeException('Unknown Telegram button action: ' . $action);
    }
}
