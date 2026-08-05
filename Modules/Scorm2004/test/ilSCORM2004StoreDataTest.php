<?php

declare(strict_types=1);

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

/**
 * Regression tests for the SCORM 2004 "null now_global_status" bug: syncGlobalStatus()
 * used to require a non-nullable int $new_global_status, so a client payload with a
 * missing/null now_global_status (e.g. from the SCORM Offline Player, or a tab closed
 * right after Commit/Terminate) threw an uncaught TypeError under strict_types - after
 * cmi_node had already been persisted, but before sahs_user/ut_lp_marks were touched.
 *
 * @author Uwe Kohnle <support@internetlehrer-gmbh.de>
 */
class ilSCORM2004StoreDataTest extends ilScorm2004BaseTestCase
{
    private const PACKAGE_ID = 42;
    private const USER_ID = 6;
    private const REF_ID = 99;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ILIAS_LOG_ENABLED')) {
            define('ILIAS_LOG_ENABLED', false);
        }
        if (!defined('CLIENT_ID')) {
            define('CLIENT_ID', 1);
        }

        $this->addGlobal_ilDB();
        $this->addGlobal_ilObjDataCache();
        $this->addGlobal_ilAppEventHandler();
        $this->addGlobal_ilSetting();
        $this->addGlobal_objDefinition();
        $this->addGlobal_tree();

        // For a trustworthy status, syncGlobalStatus() calls ilLPStatusWrapper::_updateStatus(),
        // which resolves its logger via $DIC->logger()->trac() - backed by
        // $DIC['ilLoggerFactory']. Stub it so that path doesn't fatal on a plain,
        // unconfigured mock (calling ->debug() on null).
        $componentLogger = $this->createMock(ilLogger::class);
        $loggerFactory = $this->createMock(ilLoggerFactory::class);
        $loggerFactory->method('getComponentLogger')->willReturn($componentLogger);
        $this->setGlobalVariable('ilLoggerFactory', $loggerFactory);

        // ilLPStatusWrapper::_updateStatus() -> ilLPStatusFactory::_getInstance() ->
        // ilObjectLP::getInstance() -> ilObjectLP::isSupportedObjectType() unconditionally
        // asks the plugin system for "robj" plugins with LP support - stub it to "none".
        $pluginSlotInfo = $this->createMock(ilPluginSlotInfo::class);
        $pluginSlotInfo->method('getActivePlugins')->willReturn(new ArrayIterator([]));
        $componentRepository = $this->createMock(ilComponentRepository::class);
        $componentRepository->method('getPluginSlotById')->willReturn($pluginSlotInfo);
        $this->setGlobalVariable('component.repository', $componentRepository);
    }

    private function buildData(): stdClass
    {
        $data = new stdClass();
        $data->saved_global_status = 'incomplete';
        $data->totalTimeCentisec = 12345;
        $data->percentageCompleted = 0;
        return $data;
    }

    public function test_syncGlobalStatus_nullStatus_doesNotThrowAndSkipsStatusColumn(): void
    {
        $data = $this->buildData();

        /** @var ilDBInterface&PHPUnit\Framework\MockObject\MockObject $ilDB */
        $ilDB = $GLOBALS['DIC']['ilDB'];
        $ilDB->expects($this->once())
            ->method('queryF')
            ->with(
                $this->stringContains('UPDATE sahs_user SET sco_total_time_sec=%s, percentage_completed=%s'),
                array('integer', 'integer', 'integer', 'integer'),
                array(123, 0, self::PACKAGE_ID, self::USER_ID)
            );
        // no trustworthy status -> the learning-progress update must never be reached
        $ilDB->expects($this->never())->method('replace');

        ilSCORM2004StoreData::syncGlobalStatus(
            self::USER_ID,
            self::PACKAGE_ID,
            self::REF_ID,
            $data,
            null,
            true
        );
    }

    public function test_syncGlobalStatus_validStatus_updatesStatusColumn(): void
    {
        $data = $this->buildData();

        /** @var ilDBInterface&PHPUnit\Framework\MockObject\MockObject $ilDB */
        $ilDB = $GLOBALS['DIC']['ilDB'];
        $ilDB->expects($this->once())
            ->method('queryF')
            ->with(
                $this->stringContains('UPDATE sahs_user SET sco_total_time_sec=%s, status=%s, percentage_completed=%s'),
                array('integer', 'integer', 'integer', 'integer', 'integer'),
                array(123, 2, 0, self::PACKAGE_ID, self::USER_ID)
            );

        ilSCORM2004StoreData::syncGlobalStatus(
            self::USER_ID,
            self::PACKAGE_ID,
            self::REF_ID,
            $data,
            2,
            true
        );
    }

    public function test_syncGlobalStatus_notAttemptedStatusZero_isNotTreatedAsNull(): void
    {
        $data = $this->buildData();

        /** @var ilDBInterface&PHPUnit\Framework\MockObject\MockObject $ilDB */
        $ilDB = $GLOBALS['DIC']['ilDB'];
        // under the old `!= null` check, an int 0 status would have been loosely-equal to
        // null and incorrectly skipped the sahs_user.status update (and the learning
        // progress update) entirely
        $ilDB->expects($this->once())
            ->method('queryF')
            ->with(
                $this->stringContains('UPDATE sahs_user SET sco_total_time_sec=%s, status=%s, percentage_completed=%s'),
                array('integer', 'integer', 'integer', 'integer', 'integer'),
                array(123, 0, 0, self::PACKAGE_ID, self::USER_ID)
            );

        ilSCORM2004StoreData::syncGlobalStatus(
            self::USER_ID,
            self::PACKAGE_ID,
            self::REF_ID,
            $data,
            0,
            true
        );
    }
}
