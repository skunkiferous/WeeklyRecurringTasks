<?php
namespace Kanboard\Plugin\WeeklyRecurringTasks\Action;
use Kanboard\Action\Base;
use Kanboard\Model\TagModel;
use Kanboard\Model\TaskModel;
use Kanboard\Model\TaskTagModel;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\TaskProjectDuplicationModel;

/**
 * Automatically Clone Tasks with the DAILY/WEEKLY/BIWEEKLY/DAY-OF-WEEK-IN-CAPITAL tag.
 * 
 * DAY-OF-WEEK-IN-CAPITAL: MONDAY/TUESDAY/WEDNESDAY/THURSDAY/FRIDAY/SATURDAY/SUNDAY
 *
 * @package action
 * @author  Sebastien Diot
 */
class WeeklyRecurringTask extends Base
{
    /**
     * Get automatic action description
     *
     * @access public
     * @return string
     */
    public function getDescription()
    {
        return t('Automatically clone Tasks with the DAILY/WEEKLY/BIWEEKLY/DAY-OF-WEEK-IN-CAPITAL tag');
    }
    /**
     * Get the list of compatible events
     *
     * @access public
     * @return array
     */
    public function getCompatibleEvents()
    {
        return array(
            TaskModel::EVENT_DAILY_CRONJOB,
        );
    }
    /**
     * Get the required parameter for the action (defined by the user)
     *
     * @access public
     * @return array
     */
    public function getActionRequiredParameters()
    {
        return array();
    }
    /**
     * Get the required parameter for the event
     *
     * @access public
     * @return string[]
     */
    public function getEventRequiredParameters()
    {
        return array();
    }
    /**
     * Check if the event data meet the action condition
     *
     * @access public
     * @param  array   $data   Event data dictionary
     * @return bool
     */
    public function hasRequiredCondition(array $data)
    {
        return true;
    }
    /**
     * Get currently due (yesterday to tomorrow) tasks query
     *
     * @access private
     * @param  integer  $project_id
     * @param  string  $tag
     * @return array
     */
    private function getDueTasks($project_id, $tag)
    {
		$tag_id = $this->tagModel->getIdByName($project_id, $tag);
        if ($tag_id == 0) {
			// $tag not found in project $project_id
            return array();
        }
		// The (yesterday to tomorrow) range enables us to duplicate correctly, even if the server was down for one day.
        return $this->db->table(TaskModel::TABLE)
                    ->columns(
                        TaskModel::TABLE.'.id',
                        TaskModel::TABLE.'.project_id',
                        TaskModel::TABLE.'.date_due',
                        TaskModel::TABLE.'.title'
                    )
                    ->join(TaskTagModel::TABLE, 'task_id', 'id')
                    ->eq(TaskTagModel::TABLE.'.tag_id', $tag_id)
                    ->eq(TaskModel::TABLE.'.project_id', $project_id)
                    /*->eq(TaskModel::TABLE.'.is_active', 0)*/
                    ->gte(TaskModel::TABLE.'.date_due', strtotime("-1 day"))
                    ->lte(TaskModel::TABLE.'.date_due', strtotime("+1 day"))
					->findAll();
    }
    /**
     * Check if the task was duplicated already
     *
     * @access private
     * @param  integer  $project_id
     * @param  string  $title
     * @param  integer  $date_due
     * @return bool
     */
    private function wasDuplicated($project_id, $title, $date_due)
    {
        return $this->db->table(TaskModel::TABLE)->eq('project_id', $project_id)->eq('title', $title)->eq('date_due', $date_due)->exists();
    }
    /**
     * Process all relevant tasks of one project.
     *
     * @access private
     * @param  integer  $project_id
     * @param  string  $tag
     * @param  string  $delay
     * @return bool
     */
	private function processProject($project_id, $tag, $delay)
	{
		$result = true;
		$due_tasks = $this->getDueTasks($project_id, $tag);
		foreach ($due_tasks as $task) {
			$task_id = $task['id'];
			$task_title = $task['title'];
			$task_date_due = $task['date_due'];
			$new_due_date = strtotime($delay, $task_date_due);
			$duplicated = $this->wasDuplicated($project_id, $task_title, $new_due_date);
			if (!$duplicated) {
				$new_task_id = $this->taskProjectDuplicationModel->duplicateToProject($task_id, $project_id);
				if ($new_task_id !== false) {
					if (!$this->taskModificationModel->update(array('id' => $new_task_id, 'is_active' => 1, 'date_due' => $new_due_date))) {
						error_log('Failed to update duplicated task: ID=' . $new_task_id . ', TITLE=' . $task_title . ', PROJECT=' . $project_id);
					}
				} else {
					error_log('Failed to duplicate task: ID=' . $task_id . ', TITLE=' . $task_title . ', PROJECT=' . $project_id);
					$result = false;
					Break;
				}
			}
		}
		return $result;
	}
    /**
     * Returns true for week-end dates.
     *
     * @access private
     * @param  integer  $date
     * @return bool
     */
	private function isWeekend($date) {
		return (date('N', strtotime($date)) >= 6);
	}	
    /**
     * Execute the action
     *
     * @access public
     * @param  array   $data   Event data dictionary
     * @return bool            True if the action was executed or false when not executed
     */
    public function doAction(array $data)
    {
		$result = true;
		foreach ($this->projectModel->getAllByStatus(ProjectModel::ACTIVE) as $project) {
			$project_name = $project['name'];
			$project_id = $project['id'];
			$result = $this->processProject($project_id, "DAILY", "+1 day");
			if (!$result) {
				Break;
			}
			$result = $this->processProject($project_id, "WEEKLY", "+7 day");
			if (!$result) {
				Break;
			}
			$result = $this->processProject($project_id, "BIWEEKLY", "+14 day");
			if (!$result) {
				Break;
			}
			// Now, for the day-specific tags...
			$today = strtoupper(date("l", time()));
			var_dump($today);
			$result = $this->processProject($project_id, $today, "+7 day");
			if (!$result) {
				Break;
			}
		}
		return $result;
    }
}
