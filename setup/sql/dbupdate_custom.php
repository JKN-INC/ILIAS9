<#1>
<?php

// Active Record
require_once('./Services/ActiveRecord/class.ActiveRecord.php');

global $ilDB;

// Rubric
require_once('./Services/Tracking/classes/rubric/class.ilRubricConfig.php');
rubricConfig::installDB();

// Rubric Label
require_once('./Services/Tracking/classes/rubric/class.ilRubricLabelConfig.php');
rubricLabelConfig::installDB();

// Rubric Group
require_once('./Services/Tracking/classes/rubric/class.ilRubricGroupConfig.php');
rubricGroupConfig::installDB();

// Rubric Criteria
require_once('./Services/Tracking/classes/rubric/class.ilRubricCriteriaConfig.php');
rubricCriteriaConfig::installDB();

// Rubric Behaviors
require_once('./Services/Tracking/classes/rubric/class.ilRubricBehaviorConfig.php');
rubricBehaviorConfig::installDB();

// Rubric Data
require_once('./Services/Tracking/classes/rubric/class.ilRubricDataConfig.php');
rubricDataConfig::installDB();

// Remove deprecated columns when present.
if ($ilDB->tableExists('rubric_label') && $ilDB->tableColumnExists('rubric_label', 'weight')) {
	$ilDB->dropTableColumn('rubric_label', 'weight');
}

if ($ilDB->tableExists('rubric_behavior') && $ilDB->tableColumnExists('rubric_behavior', 'rubric_label_id')) {
	$ilDB->dropTableColumn('rubric_behavior', 'rubric_label_id');
}

// Add rubric_weight table
require_once('./Services/Tracking/classes/rubric/class.ilRubricWeightConfig.php');
rubricWeightConfig::installDB();

// Rename rubric_data.behavior_comment to rubric_data.criteria_comment
if (
	$ilDB->tableExists('rubric_data')
	&& $ilDB->tableColumnExists('rubric_data', 'behavior_comment')
	&& !$ilDB->tableColumnExists('rubric_data', 'criteria_comment')
) {
	$ilDB->renameTableColumn('rubric_data', 'behavior_comment', 'criteria_comment');
}

// Rename rubric_data.rubric_behavior_id to rubric_data.rubric_criteria_id
if (
	$ilDB->tableExists('rubric_data')
	&& $ilDB->tableColumnExists('rubric_data', 'rubric_behavior_id')
	&& !$ilDB->tableColumnExists('rubric_data', 'rubric_criteria_id')
) {
	$ilDB->renameTableColumn('rubric_data', 'rubric_behavior_id', 'rubric_criteria_id');
}

// Remove rubric_data.rubric_label_id
if ($ilDB->tableExists('rubric_data') && $ilDB->tableColumnExists('rubric_data', 'rubric_label_id')) {
	$ilDB->dropTableColumn('rubric_data', 'rubric_label_id');
}

// Add rubric_data.criteria_point
if ($ilDB->tableExists('rubric_data') && !$ilDB->tableColumnExists('rubric_data', 'criteria_point')) {
	$ilDB->addTableColumn('rubric_data', 'criteria_point', array('type' => 'integer', 'length' => 3));
}

if ($ilDB->tableExists('rubric_behavior') && $ilDB->tableColumnExists('rubric_behavior', 'description')) {
	$ilDB->modifyTableColumn('rubric_behavior', 'description', array('type' => 'text', 'length' => 1000));
}

if ($ilDB->tableExists('rubric') && !$ilDB->tableColumnExists('rubric', 'locked')) {
	$ilDB->addTableColumn('rubric', 'locked', array('type' => 'timestamp'));
}

if ($ilDB->tableExists('rubric_weight') && $ilDB->tableColumnExists('rubric_weight', 'weight_min')) {
	$ilDB->modifyTableColumn('rubric_weight', 'weight_min', array('type' => 'float'));
}

if ($ilDB->tableExists('rubric_weight') && $ilDB->tableColumnExists('rubric_weight', 'weight_max')) {
	$ilDB->modifyTableColumn('rubric_weight', 'weight_max', array('type' => 'float'));
}

if ($ilDB->tableExists('rubric_data') && $ilDB->tableColumnExists('rubric_data', 'criteria_point')) {
	$ilDB->modifyTableColumn('rubric_data', 'criteria_point', array('type' => 'float'));
}

if ($ilDB->tableExists('rubric') && !$ilDB->tableColumnExists('rubric', 'complete')) {
	$ilDB->addTableColumn('rubric', 'complete', array('type' => 'integer', 'length' => 1));
}

if ($ilDB->tableExists('rubric') && !$ilDB->tableColumnExists('rubric', 'grading_locked')) {
	$ilDB->addTableColumn('rubric', 'grading_locked', array('type' => 'timestamp'));
}

if ($ilDB->tableExists('rubric') && !$ilDB->tableColumnExists('rubric', 'grading_locked_by')) {
	$ilDB->addTableColumn('rubric', 'grading_locked_by', array('type' => 'integer', 'length' => 4));
}

require_once('./Services/Tracking/classes/rubric/class.ilRubricGradeHistoryConfig.php');
$rubricHistory = new rubricGradeHistoryConfig();
$rubricHistory->installDB();

if ($ilDB->tableExists('rubric') && $ilDB->tableColumnExists('rubric', 'grading_locked')) {
	$ilDB->dropTableColumn('rubric', 'grading_locked');
}

if ($ilDB->tableExists('rubric') && $ilDB->tableColumnExists('rubric', 'grading_locked_by')) {
	$ilDB->dropTableColumn('rubric', 'grading_locked_by');
}

// Add new lock table after removing legacy lock columns.
require_once('./Services/Tracking/classes/rubric/class.ilRubricGradeLockConfig.php');
$rubricLock = new rubricGradeLockConfig();
$rubricLock->installDB();

?>
<#2>
<?php

$ilDB->query("ALTER TABLE obj_members ADD COLUMN IF NOT EXISTS `failed` TINYINT(4) DEFAULT NULL");

?>

<#21>
<?php

// Reporting views: user_search and user_course_data become views over the ILIAS tables, with the same rows and
// columns as the tables /var/scripts/ReportingTables used to rebuild every 2 hours (stop that cron in the same
// release), minus the unused UDF_* columns. udf_search and user_course_data_extended are unused and dropped.
// Text columns are converted to utf8mb4_unicode_ci like the tables were (the ILIAS columns are utf8mb3_general_ci):
// Redash queries apply COLLATE utf8mb4_unicode_ci to them and UNION them across portals.
global $ilDB;

foreach (['user_course_data_extended', 'udf_search', 'user_course_data', 'user_search'] as $table) {
	$res = $ilDB->queryF(
		"SELECT table_type FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
		['text'],
		[$table]
	);
	$row = $ilDB->fetchAssoc($res);
	if ($row && $row['table_type'] === 'BASE TABLE') {
		$ilDB->manipulate('DROP TABLE ' . $ilDB->quoteIdentifier($table));
	}
}

// user_course_data looks up the latest event_archive row of each enrolment by (usr_id, obj_id); without an index
// starting with those columns that is a full table scan per enrolment (prod has eva_usr_obj_id, old copies may not)
$res = $ilDB->query(
	"SELECT 1 FROM information_schema.statistics a JOIN information_schema.statistics b"
	. " ON b.table_schema = a.table_schema AND b.table_name = a.table_name AND b.index_name = a.index_name"
	. " WHERE a.table_schema = DATABASE() AND a.table_name = 'event_archive'"
	. " AND a.seq_in_index = 1 AND a.column_name = 'usr_id' AND b.seq_in_index = 2 AND b.column_name = 'obj_id'"
);
if (!$ilDB->fetchAssoc($res)) {
	$ilDB->manipulate('ALTER TABLE event_archive ADD INDEX eva (usr_id, obj_id)');
}

// one row per user and org unit assignment (and one for users without one); badge = UDF 'Badge Number'
$ilDB->manipulate(<<<'SQL'
CREATE OR REPLACE SQL SECURITY INVOKER VIEW user_search AS
SELECT
  u.usr_id,
  CONVERT(u.title USING utf8mb4) COLLATE utf8mb4_unicode_ci AS title,
  u.time_limit_owner,
  CONVERT(od.title USING utf8mb4) COLLATE utf8mb4_unicode_ci AS organization,
  CONVERT(IF(ua.position_id = '2', 'Superior', 'Employee') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS org_role,
  od.obj_id AS org_obj_id,
  CONVERT(u.login USING utf8mb4) COLLATE utf8mb4_unicode_ci AS login,
  CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_unicode_ci AS email,
  CONVERT(u.firstname USING utf8mb4) COLLATE utf8mb4_unicode_ci AS firstname,
  CONVERT(u.lastname USING utf8mb4) COLLATE utf8mb4_unicode_ci AS lastname,
  CONVERT(u.country USING utf8mb4) COLLATE utf8mb4_unicode_ci AS country,
  CONVERT(u.department USING utf8mb4) COLLATE utf8mb4_unicode_ci AS department,
  u.active,
  CONVERT(b.value USING utf8mb4) COLLATE utf8mb4_unicode_ci AS badge
FROM usr_data u
LEFT JOIN il_orgu_ua ua ON ua.user_id = u.usr_id
LEFT JOIN object_reference obr ON obr.ref_id = ua.orgu_id
LEFT JOIN object_data od ON od.obj_id = obr.obj_id
LEFT JOIN udf_definition bd ON bd.field_name = 'Badge Number'
LEFT JOIN udf_text b ON b.field_id = bd.field_id AND b.usr_id = u.usr_id
WHERE u.usr_id > 0 AND u.login <> 'anonymous'
SQL);

// one row per course member role assignment (course admins and tutors left out) and org unit row of user_search
$ilDB->manipulate(<<<'SQL'
CREATE OR REPLACE SQL SECURITY INVOKER VIEW user_course_data AS
SELECT
  us.usr_id,
  us.time_limit_owner,
  CONVERT(obj.title USING utf8mb4) COLLATE utf8mb4_unicode_ci AS title,
  obj.obj_id,
  us.login,
  us.email,
  us.firstname,
  us.lastname,
  us.country,
  us.department,
  us.active,
  us.badge,
  CONVERT(CAST(EXTRACTVALUE(po.content, "//PluggedProperty[@Name='price']") + 0.00 AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS price,
  us.organization,
  ut.status_changed,
  IFNULL(ut.status, 0) AS user_status,
  ea.create_date AS enrollment_date,
  CASE ut.status WHEN 2 THEN FROM_UNIXTIME(mem.origin_ts) WHEN 3 THEN ut.status_changed END AS completion_date
FROM object_data obj
JOIN object_reference ref ON ref.obj_id = obj.obj_id AND ref.deleted IS NULL
JOIN rbac_fa fa ON fa.parent = ref.ref_id AND fa.assign = 'y'
JOIN rbac_ua ua ON ua.rol_id = fa.rol_id
JOIN object_data rol ON rol.obj_id = fa.rol_id
JOIN user_search us ON us.usr_id = ua.usr_id
LEFT JOIN ut_lp_marks ut ON ut.obj_id = obj.obj_id AND ut.usr_id = us.usr_id
LEFT JOIN obj_members mem ON mem.usr_id = us.usr_id AND mem.obj_id = obj.obj_id
-- course price from the priceinput page plugin
LEFT JOIN page_object po ON po.parent_id = obj.obj_id AND po.lang = '-' AND po.content LIKE '%PluggedProperty%'
-- enrollment date: the user's latest event in the course, if that is addParticipant
LEFT JOIN event_archive ea ON ea.usr_id = us.usr_id AND ea.obj_id = obj.obj_id AND ea.event = 'addParticipant'
  AND ea.id = (SELECT MAX(e2.id) FROM event_archive e2 WHERE e2.usr_id = ea.usr_id AND e2.obj_id = ea.obj_id)
WHERE obj.type = 'crs'
  AND rol.title NOT LIKE 'il_crs_admin%'
  AND rol.title NOT LIKE 'il_crs_tutor%'
SQL);

?>