<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime;

use function array_filter;
use function array_key_exists;
use function array_values;
use function in_array;
use function is_int;
use function ksort;
use function max;
use function trim;
use function usort;

use const PHP_INT_MAX;

final class ButtonGroupMenuBuilder
{
    /**
     * @var list<array{
     *   kind:'action'|'callback'|'url',
     *   name:string,
     *   label:string,
     *   row:int,
     *   position:?int,
     *   action?:string,
     *   value?:string
     * }>
     */
    private array $items = [];

    /**
     * @param list<ButtonGroupButton> $baseButtons
     */
    public function __construct(
        private readonly ActionRegistry $actions,
        array $baseButtons = [],
    ) {
        foreach ($baseButtons as $button) {
            $this->items[] = [
                'kind'     => 'action',
                'name'     => $button->name,
                'label'    => $button->label,
                'row'      => max(1, $button->row),
                'position' => $button->position > 0 ? $button->position : 1,
                'action'   => $button->action,
            ];
        }
    }

    public function withRowOffset(int $rowOffset): self
    {
        if ($rowOffset === 0) {
            return $this;
        }

        if ($rowOffset < 0) {
            return $this;
        }

        foreach ($this->items as $index => $item) {
            $this->items[$index]['row'] = $item['row'] + $rowOffset;
        }

        return $this;
    }

    public function withoutNames(string ...$names): self
    {
        $normalized = [];
        foreach ($names as $name) {
            $value = trim($name);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        if ($normalized === []) {
            return $this;
        }

        $this->items = array_values(array_filter(
            $this->items,
            static fn (array $item): bool => !in_array($item['name'], $normalized, true),
        ));

        return $this;
    }

    public function withoutActions(string ...$actions): self
    {
        $normalized = [];
        foreach ($actions as $action) {
            $value = trim($action);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        if ($normalized === []) {
            return $this;
        }

        $this->items = array_values(array_filter(
            $this->items,
            static function (array $item) use ($normalized): bool {
                $action = trim((string) ($item['action'] ?? ''));
                if ($action === '') {
                    return true;
                }

                return !in_array($action, $normalized, true);
            },
        ));

        return $this;
    }

    public function appendCallback(
        string $name,
        string $label,
        string $callbackData,
        int $row,
        ?int $position = null,
    ): self {
        $name         = trim($name);
        $label        = trim($label);
        $callbackData = trim($callbackData);
        if ($name === '' || $label === '' || $callbackData === '') {
            return $this;
        }

        $this->items[] = [
            'kind'     => 'callback',
            'name'     => $name,
            'label'    => $label,
            'row'      => max(1, $row),
            'position' => is_int($position) && $position > 0 ? $position : null,
            'value'    => $callbackData,
        ];

        return $this;
    }

    public function appendUrl(
        string $name,
        string $label,
        string $url,
        int $row,
        ?int $position = null,
    ): self {
        $name  = trim($name);
        $label = trim($label);
        $url   = trim($url);
        if ($name === '' || $label === '' || $url === '') {
            return $this;
        }

        $this->items[] = [
            'kind'     => 'url',
            'name'     => $name,
            'label'    => $label,
            'row'      => max(1, $row),
            'position' => is_int($position) && $position > 0 ? $position : null,
            'value'    => $url,
        ];

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function build(): array
    {
        if ($this->items === []) {
            return [];
        }

        usort(
            $this->items,
            static function (array $a, array $b): int {
                $aPosition = $a['position'] ?? PHP_INT_MAX;
                $bPosition = $b['position'] ?? PHP_INT_MAX;

                return [$a['row'], $aPosition] <=> [$b['row'], $bPosition];
            },
        );

        $rows             = [];
        $maxPositionByRow = [];

        foreach ($this->items as $item) {
            $row      = max(1, (int) $item['row']);
            $position = $item['position'];
            if (!is_int($position) || $position < 1) {
                $position = ($maxPositionByRow[$row] ?? 0) + 1;
            }
            $maxPositionByRow[$row] = max($maxPositionByRow[$row] ?? 0, $position);

            $resolved = $this->resolveItem($item);
            if ($resolved === null) {
                continue;
            }

            if (!array_key_exists($row, $rows)) {
                $rows[$row] = [];
            }
            if (!array_key_exists($position, $rows[$row])) {
                $rows[$row][$position] = $resolved;
            }
        }

        if ($rows === []) {
            return [];
        }

        ksort($rows);
        $inlineRows = [];
        foreach ($rows as $row) {
            ksort($row);
            $inlineRows[] = array_values($row);
        }

        return [
            'reply_markup' => [
                'inline_keyboard' => $inlineRows,
            ],
        ];
    }

    /**
     * @param array{
     *   kind:'action'|'callback'|'url',
     *   name:string,
     *   label:string,
     *   row:int,
     *   position:?int,
     *   action?:string,
     *   value?:string
     * } $item
     * @return array{text:string,callback_data?:string,url?:string}|null
     */
    private function resolveItem(array $item): ?array
    {
        $kind  = $item['kind'];
        $label = trim($item['label']);
        if ($label === '') {
            return null;
        }

        if ($kind === 'action') {
            $actionName = trim((string) ($item['action'] ?? ''));
            if ($actionName === '') {
                return null;
            }

            $resolved = $this->actions->resolve($actionName);
            if ($resolved['type'] === 'url') {
                return [
                    'text' => $label,
                    'url'  => $resolved['value'],
                ];
            }

            return [
                'text'          => $label,
                'callback_data' => $resolved['value'],
            ];
        }

        $value = trim((string) ($item['value'] ?? ''));
        if ($value === '') {
            return null;
        }

        if ($kind === 'url') {
            return [
                'text' => $label,
                'url'  => $value,
            ];
        }

        return [
            'text'          => $label,
            'callback_data' => $value,
        ];
    }
}
