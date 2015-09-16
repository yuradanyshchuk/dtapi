<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Class with definitions Answer table model
 *
 */

class Model_Answer extends Model_Common {
	
	protected $tableName = "answers";
	protected $fieldNames = array("answer_id","question_id", "true_answer", "answer_text", "attachment");
	
	public function getAnswersByQuestion($question_id)
	{
		return $this->getEntityBy($this->fieldNames[1], $question_id);
	}
	
	public function countTrueAnswersByQuestion($question_id)
	{
		$query = "SELECT COUNT({$this->fieldNames[0]}) AS count 
				FROM {$this->tableName} 
				WHERE {$this->fieldNames[2]} = 1
				AND {$this->fieldNames[1]} = {$question_id}";
		$count = DB::query(Database::SELECT, $query)->execute()->get('count');
		return $count;
	}
	
	public function checkAnswers($question_id, $answer_ids)
	{
		$true_answers_unumber = 0;
		$true_answers_number = 0; // by default;
		if (!is_array($answer_ids))
		{
			throw new Kohana_Exception("Error input parameters");
		}
		else
		{
			// get question type
			$question_type = Model::factory("Question")->getQuestionTypeById($question_id);
			if ($question_type == 2)
			{
				$true_answers_number = $this->countTrueAnswersByQuestion($question_id);
			}
			
			// get answers
			$answers = $this->getRecordsByIds($answer_ids);
			
			foreach ($answers as $answer)
			{
				// check if incorect answer is present, so it's bad :-)
				if ($answer->true_answer == 0)
				{
					return false;
				}
				else 
				{
					$true_answers_unumber++;
				}
			}
			
			// final check
			// simple choice
			if (($question_type == 1) && ($true_answers_unumber > 0))
			{
				return true;
			}
			// multi choice
			if (($question_type == 2) && ($true_answers_unumber == $true_answers_number))
			{
				return true;
			}
			
			return false;
		} // else (no exception)
	}
	
}
