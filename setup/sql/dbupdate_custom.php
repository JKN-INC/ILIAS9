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