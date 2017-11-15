<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Class with definitions Logs table model
 *
 */

class Model_Log extends Model_Common {
	
	private $INTERVAL = "10 MINUTE";
	protected $tableName = "logs";
	protected $fieldNames = array("log_id","user_id", "test_id", "log_date", "log_time", "remote_ip");

	public function getLogsByUser($user_id)
	{
		return $this->getEntityBy($this->fieldNames[1], $user_id);
	}
	
	/**
	 * Special security check function
	 * 
	 * @param int $user_id
	 * @param int $test_id
	 * @return boolean is user can pass the test now
	 * true - user cannot make the test because he made a test recently ($INTERVAL constant)
	 * false - user can make the test
	 */
	protected function isUserMadeTest($user_id, $test_id)
	{
		$query = "SELECT COUNT(*) AS count FROM {$this->tableName} WHERE {$this->fieldNames[1]} = {$user_id}
				AND {$this->fieldNames[2]} = {$test_id}
				AND CONCAT({$this->fieldNames[3]}, ' ', {$this->fieldNames[4]}) > DATE_SUB(NOW(), INTERVAL {$this->INTERVAL})";
		$count = DB::query(Database::SELECT, $query)->execute()->get('count');
		
		if ($count > 0) {
			return true;
		}
		else {
			return false;
		}
	}
	
	public function startTest($user_id, $test_id, $remote_ip = "0.0.0.0")
	{
		$group_id = null;
		$subject_id = null;
		
		// get attempts count using information from Result
		$user_attempts = Model::factory("Result")->countTestPassesByStudent($user_id, $test_id);
		
		// get group_id from Student Entity
		$student = Model::factory("Student")->getRecord($user_id);
		foreach ($student as $_student)
		{
			$group_id = $_student->group_id;
		}
		unset($student);
		
		// get subject_id, attempts from Test Entity
		// checking attempts
		$test = Model::factory("Test")->getRecord($test_id);
		foreach ($test as $_test)
		{
			$subject_id = $_test->subject_id;
			if ($user_attempts >= $_test->attempts)
			{
				throw new HTTP_Exception_403("You cannot make the test due to used all attempts");
			}
			if (intval($_test->enabled) === 0)
			{
				throw new HTTP_Exception_403("You cannot make the test due that this test is disabled");
			}
		}
		unset($test);
		
		$timetables_count = Model::factory("TimeTable")->getTimeTableForGroupAndSubject($group_id, $subject_id, $check_time = true)->count();
		if ($timetables_count == 0)
		{
			throw new HTTP_Exception_403("You cannot make the test due to your schedule");
		}
		
		// security check
		if ($this->isUserMadeTest($user_id, $test_id)) 
		{
			$this->errorMessage = "Error. User made test recently";
			return strval($this->errorMessage);
		}
		else
		{
			$values = array(
					$this->fieldNames[0] => 0,
					$this->fieldNames[1] => $user_id,
					$this->fieldNames[2] => $test_id,
					$this->fieldNames[3] => date("Y-m-d"),
					$this->fieldNames[4] => date("H:i:s"),
					$this->fieldNames[5] => $remote_ip
			);
			
			Session::instance()->set("startTime", $values[$this->fieldNames[4]]);
			$insertQuery = DB::insert($this->tableName, $this->fieldNames)
			->values($values);
			try
			{
				list($insert_id, $aff_rows) = $insertQuery->execute();
			} catch (Database_Exception $error) {
				$this->errorMessage = "error ".$error->getCode();
				return strval($this->errorMessage);
			}
			if ($aff_rows > 0) return intval($insert_id);
			if ($aff_rows <= 0) return false;
		}
	}
	
}
