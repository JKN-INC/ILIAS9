<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\Setup;
use ILIAS\Refinery;

class ilOrgUnitSetupAgent implements Setup\Agent
{
    protected \ILIAS\Refinery\Factory $refinery;

    public function __construct(
        Refinery\Factory $refinery
    ) {
        $this->refinery = $refinery;
    }

    public function hasConfig(): bool
    {
        return false;
    }

    public function getArrayToConfigTransformation(): Refinery\Transformation
    {
        throw new LogicException("Agent has no config");
    }

    public function getInstallObjective(Setup\Config $config = null): Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    public function getUpdateObjective(Setup\Config $config = null): Setup\Objective
    {
        return new Setup\ObjectiveCollection(
            'OrgUnit',
            true,
            ...$this->getLearningProgressContextObjectives()
        );
    }

    /**
     * Position based access to learning progress for types that have
     * orgunit_permissions="1" in their module.xml but no core context.
     * @return Setup\Objective[]
     */
    protected function getLearningProgressContextObjectives(): array
    {
        $objectives = [];
        foreach ([
            ilOrgUnitOperationContext::CONTEXT_SAHS,
            ilOrgUnitOperationContext::CONTEXT_CRSR
        ] as $context) {
            $objectives[] = new ilOrgUnitOperationContextRegisteredObjective(
                $context,
                ilOrgUnitOperationContext::CONTEXT_OBJECT
            );
            $objectives[] = new ilOrgUnitOperationRegisteredObjective(
                ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
                'Read the learning progress of other users',
                $context
            );
        }
        return $objectives;
    }

    public function getBuildArtifactObjective(): Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    public function getStatusObjective(Setup\Metrics\Storage $storage): Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    public function getMigrations(): array
    {
        return [];
    }

    public function getNamedObjectives(?Setup\Config $config = null): array
    {
        return [
            'removeDeletedUsersFromOrgUnits' => new Setup\ObjectiveConstructor(
                'clean assignments of deleted users',
                static fn(): Setup\Objective => new ilOrgUnitRemoveDeletedUsersObjective()
            )
        ];
    }
}
