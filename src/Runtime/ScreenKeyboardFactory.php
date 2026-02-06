<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime;

use function array_values;
use function usort;

final readonly class ScreenKeyboardFactory
{
    public function __construct(
        private ScreenButtonsProvider $buttons,
        private ActionRegistry $actions,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inlineMenu(string $screenName): array
    {
        $definitions = $this->buttons->forScreen($screenName);
        if ($definitions === []) {
            return [];
        }

        usort(
            $definitions,
            static fn (ScreenButton $a, ScreenButton $b): int => [$a->row, $a->position] <=> [$b->row, $b->position],
        );

        $rows = [];
        foreach ($definitions as $button) {
            $resolved = $this->actions->resolve($button->action);
            $rowKey   = $button->row > 0 ? $button->row : 1;

            if (!isset($rows[$rowKey])) {
                $rows[$rowKey] = [];
            }

            if ($resolved['type'] === 'url') {
                $rows[$rowKey][] = [
                    'text' => $button->label,
                    'url'  => $resolved['value'],
                ];

                continue;
            }

            $rows[$rowKey][] = [
                'text'          => $button->label,
                'callback_data' => $resolved['value'],
            ];
        }

        return [
            'reply_markup' => [
                'inline_keyboard' => array_values($rows),
            ],
        ];
    }
}
