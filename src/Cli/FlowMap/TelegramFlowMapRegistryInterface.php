<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Cli\FlowMap;

use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMap;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapBranch;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapCjm;

interface TelegramFlowMapRegistryInterface
{
    public function flowMapComponent(bool $includeButtons = true): ScenarioFlowMap;

    /**
     * @return array{
     *   nodes:list<array{id:string,type:string,label:string}>,
     *   edges:list<array{
     *     from:string,
     *     to:string,
     *     kind:string,
     *     label:string,
     *     event:?string,
     *     target_type:?string,
     *     target_id:?string,
     *     guard_class:?string,
     *     guard_args:array<string,mixed>,
     *     expected_screen:?string
     *   }>
     * }
     */
    public function flowMapScoped(bool $includeButtons = true, string $chainScope = 'all'): array;

    public function flowMapHtml(bool $includeButtons = true, string $chainScope = 'all', string $rankdir = 'TB'): string;

    /**
     * @return list<ScenarioFlowMapBranch>
     */
    public function flowMapBranchDefinitions(?string $branchId = null): array;

    /**
     * @return list<ScenarioFlowMapCjm>
     */
    public function flowMapCjmDefinitions(?string $cjmId = null): array;
}
