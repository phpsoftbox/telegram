<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapRegistryResolverInterface;
use PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapSettingsInterface;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapBranch;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapCjm;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapDefinitionsValidator;

use function array_key_exists;
use function array_values;
use function implode;
use function in_array;
use function strtolower;
use function trim;

final readonly class TelegramFlowMapValidateHandler implements HandlerInterface
{
    public function __construct(
        private ?TelegramFlowMapRegistryResolverInterface $registries = null,
        private ?TelegramFlowMapSettingsInterface $settings = null,
        private ScenarioFlowMapDefinitionsValidator $validator = new ScenarioFlowMapDefinitionsValidator(),
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        if ($this->registries === null) {
            $runner->io()->writeln(
                'Flow map validation CLI is not configured. Bind TelegramFlowMapRegistryResolverInterface in DI.',
                'error',
            );

            return Response::FAILURE;
        }

        $scope = strtolower(trim((string) $runner->request()->option('scope', '')));
        if (!in_array($scope, ['branch', 'cjm'], true)) {
            $runner->io()->writeln('Опция --scope должна быть branch или cjm.', 'error');

            return Response::FAILURE;
        }

        $id     = trim((string) $runner->request()->option('id', ''));
        $strict = (bool) $runner->request()->option('strict', false);

        $defaultBot = $this->settings?->defaultBot() ?? 'main';
        $bot        = trim((string) $runner->request()->option('bot', $defaultBot));
        if ($bot === '') {
            $bot = $defaultBot;
        }

        $registry = $this->registries->resolve($bot);

        $allBranches = $registry->flowMapBranchDefinitions();
        $allCjms     = $registry->flowMapCjmDefinitions();

        if ($scope === 'branch') {
            $branches = $id !== '' ? $registry->flowMapBranchDefinitions($id) : $allBranches;
            if ($id !== '' && $branches === []) {
                $runner->io()->writeln('Branch "' . $id . '" не найден.', 'error');

                return Response::FAILURE;
            }

            $this->printBranches($runner, $branches);
            if ($branches === []) {
                $runner->io()->writeln('Branch definitions are not configured.', 'warning');

                return Response::SUCCESS;
            }

            $report = $this->validator->validate(
                map: $registry->flowMapComponent(includeButtons: true),
                branches: $branches,
                cjms: [],
            );
        } else {
            $cjms = $id !== '' ? $registry->flowMapCjmDefinitions($id) : $allCjms;
            if ($id !== '' && $cjms === []) {
                $runner->io()->writeln('CJM "' . $id . '" не найден.', 'error');

                return Response::FAILURE;
            }

            $this->printCjms($runner, $cjms);
            if ($cjms === []) {
                $runner->io()->writeln('CJM definitions are not configured.', 'warning');

                return Response::SUCCESS;
            }

            $branches = $id !== ''
                ? $this->branchesForSelectedCjms($allBranches, $cjms)
                : $allBranches;

            $report = $this->validator->validate(
                map: $registry->flowMapComponent(includeButtons: true),
                branches: $branches,
                cjms: $cjms,
            );
        }

        foreach ($report['warnings'] as $warning) {
            $runner->io()->writeln('WARNING: ' . $warning, 'warning');
        }
        foreach ($report['errors'] as $error) {
            $runner->io()->writeln('ERROR: ' . $error, 'error');
        }

        if ($report['errors'] !== []) {
            return Response::FAILURE;
        }
        if ($strict && $report['warnings'] !== []) {
            return Response::FAILURE;
        }

        $runner->io()->writeln('Flow map definitions validation passed.', 'success');

        return Response::SUCCESS;
    }

    /**
     * @param list<ScenarioFlowMapBranch> $branches
     * @param list<ScenarioFlowMapCjm> $cjms
     * @return list<ScenarioFlowMapBranch>
     */
    private function branchesForSelectedCjms(array $branches, array $cjms): array
    {
        $branchById = [];
        foreach ($branches as $branch) {
            $branchById[$branch->id] = $branch;
        }

        $selected = [];
        foreach ($cjms as $cjm) {
            foreach ($cjm->branches as $branchId) {
                if (!array_key_exists($branchId, $branchById)) {
                    continue;
                }

                $selected[$branchId] = $branchById[$branchId];
            }
        }

        return array_values($selected);
    }

    /**
     * @param list<ScenarioFlowMapBranch> $branches
     */
    private function printBranches(RunnerInterface $runner, array $branches): void
    {
        $ids = [];
        foreach ($branches as $branch) {
            $ids[] = $branch->id;
        }

        $runner->io()->writeln(
            'Configured branches: ' . ($ids !== [] ? implode(', ', $ids) : '-'),
        );
    }

    /**
     * @param list<ScenarioFlowMapCjm> $cjms
     */
    private function printCjms(RunnerInterface $runner, array $cjms): void
    {
        $ids = [];
        foreach ($cjms as $cjm) {
            $ids[] = $cjm->id;
        }

        $runner->io()->writeln(
            'Configured CJMs: ' . ($ids !== [] ? implode(', ', $ids) : '-'),
        );
    }
}
