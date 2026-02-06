<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

use function array_key_exists;
use function in_array;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

final class ScenarioFlowMapDefinitionsValidator
{
    /**
     * @param list<ScenarioFlowMapBranch> $branches
     * @param list<ScenarioFlowMapCjm> $cjms
     * @return array{errors:list<string>,warnings:list<string>}
     */
    public function validate(ScenarioFlowMap $map, array $branches = [], array $cjms = []): array
    {
        $errors   = [];
        $warnings = [];

        $screenIds = [];
        $events    = [];
        foreach ($map->nodes as $node) {
            if ($node->type !== 'screen') {
                continue;
            }

            $screenId = $this->screenIdFromNode($node->id);
            if ($screenId === null) {
                continue;
            }
            $screenIds[$screenId] = true;
        }
        foreach ($map->edges as $edge) {
            $event = trim((string) ($edge->event ?? ''));
            if ($event === '') {
                continue;
            }
            $events[$event] = true;
        }

        $branchesById = [];
        foreach ($branches as $branch) {
            if (array_key_exists($branch->id, $branchesById)) {
                $errors[] = 'Flow map branch id "' . $branch->id . '" is duplicated.';

                continue;
            }
            $branchesById[$branch->id] = $branch;

            if ($branch->entryScreen !== '' && !array_key_exists($branch->entryScreen, $screenIds)) {
                $errors[] = 'Branch "' . $branch->id . '" references unknown entry screen "' . $branch->entryScreen . '".';
            }

            foreach ($branch->entryEvents as $event) {
                if (!array_key_exists($event, $events)) {
                    $errors[] = 'Branch "' . $branch->id . '" references unknown entry event "' . $event . '".';
                }
            }

            foreach ($branch->internalEvents as $event) {
                if (!array_key_exists($event, $events)) {
                    $errors[] = 'Branch "' . $branch->id . '" references unknown internal event "' . $event . '".';
                }
                if (in_array($event, $branch->exitEvents, true)) {
                    $warnings[] = 'Branch "' . $branch->id . '" has event "' . $event . '" in both internalEvents and exitEvents.';
                }
            }
            foreach ($branch->exitEvents as $event) {
                if (!array_key_exists($event, $events)) {
                    $errors[] = 'Branch "' . $branch->id . '" references unknown exit event "' . $event . '".';
                }
            }
            foreach ($branch->exitScreens as $screenId) {
                if (!array_key_exists($screenId, $screenIds)) {
                    $errors[] = 'Branch "' . $branch->id . '" references unknown exit screen "' . $screenId . '".';
                }
                if ($screenId === $branch->entryScreen) {
                    $warnings[] = 'Branch "' . $branch->id . '" has exit screen "' . $screenId . '" equal to entry screen.';
                }
            }
        }

        $cjmsById = [];
        foreach ($cjms as $cjm) {
            if (array_key_exists($cjm->id, $cjmsById)) {
                $errors[] = 'Flow map CJM id "' . $cjm->id . '" is duplicated.';

                continue;
            }
            $cjmsById[$cjm->id] = $cjm;

            if ($cjm->branches === []) {
                $warnings[] = 'CJM "' . $cjm->id . '" does not contain branches.';
            }

            foreach ($cjm->branches as $branchId) {
                if (!array_key_exists($branchId, $branchesById)) {
                    $errors[] = 'CJM "' . $cjm->id . '" references unknown branch "' . $branchId . '".';
                }
            }
        }

        return [
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    private function screenIdFromNode(string $nodeId): ?string
    {
        $prefix = 'screen:';
        if (!str_starts_with($nodeId, $prefix)) {
            return null;
        }

        $screenId = trim(substr($nodeId, strlen($prefix)));
        if ($screenId === '') {
            return null;
        }

        return $screenId;
    }
}
