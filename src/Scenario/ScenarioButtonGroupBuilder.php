<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Scenario\Id\ButtonPresetIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\TransitionIdInterface;

final class ScenarioButtonGroupBuilder
{
    public function __construct(
        private readonly ScenarioBuilder $scenario,
        private readonly string $groupName,
    ) {
    }

    public function button(
        ButtonPresetIdInterface $preset,
        int $row = 1,
        ?int $position = null,
        ?string $name = null,
        ?string $label = null,
        ?TransitionIdInterface $transition = null,
    ): self {
        $this->scenario->addButtonGroupButton($this->groupName, [
            'preset'     => $preset,
            'row'        => $row,
            'position'   => $position,
            'name'       => $name,
            'label'      => $label,
            'transition' => $transition,
        ]);

        return $this;
    }

    public function presetButton(
        ButtonPresetIdInterface $preset,
        int $row = 1,
        ?int $position = null,
        ?string $name = null,
        ?string $label = null,
        ?TransitionIdInterface $transition = null,
    ): self {
        return $this->button($preset, $row, $position, $name, $label, $transition);
    }

    public function done(): ScenarioBuilder
    {
        return $this->scenario;
    }
}
